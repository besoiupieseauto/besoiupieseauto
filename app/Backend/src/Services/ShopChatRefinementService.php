<?php

declare(strict_types=1);

namespace Besoiu\Services;

/**
 * Rafinare rezultate căutare — preț, brand, categorie, vehicul (pe lista existentă).
 */
final class ShopChatRefinementService
{
    /**
     * @param array<string, mixed> $sessionState
     */
    public function isRefinementMessage(string $message, array $sessionState): bool
    {
        require_once dirname(__DIR__, 3) . '/robot/widget_catalog_search.php';
        require_once dirname(__DIR__, 3) . '/robot/public_shop_chat.php';

        if (widget_catalog_is_new_search_message($message)) {
            return false;
        }

        $last = is_array($sessionState['last_products'] ?? null) ? $sessionState['last_products'] : [];
        if (count($last) < 2) {
            return false;
        }

        $lower = mb_strtolower(trim($message), 'UTF-8');
        if ($lower === '') {
            return false;
        }

        if (public_shop_chat_detect_order_intent($message)) {
            return false;
        }

        $patterns = [
            '/\b(sub|peste|pana|până|max|min|mai\s+(ieftin|scump)|doar|numai|filtreaza|filtrează|sorteaza|sortează)\b/u',
            '/\b(bosch|fag|trw|febi|ridex|skf|ntn|koyo)\b/ui',
            '/\b(frana|frână|disc|placute|plăcuțe|filtru|ulei|suspensie|rulment)\b/ui',
            '/\b(pentru|merge\s+(la|pe)|compatibil)\b/u',
            '/\b(primul|al\s+doilea|ultimul|nr\.?\s*\d)\b/u',
            '/^\s*(\d{1,2})\s*$/u',
        ];

        foreach ($patterns as $rx) {
            if (preg_match($rx, $lower)) {
                return true;
            }
        }

        return mb_strlen($message) < 35 && !public_shop_chat_looks_like_product_query($message);
    }

    /**
     * @param list<array<string, mixed>> $products
     * @param array<string, mixed> $sessionState
     * @return array{products:list<array<string,mixed>>,filters:list<array<string,mixed>>,reply_hint:string}
     */
    public function refine(string $message, array $products, array &$sessionState): array
    {
        ShopChatSessionService::normalize($sessionState);
        $base = $products !== [] ? $products : (is_array($sessionState['last_products'] ?? null) ? $sessionState['last_products'] : []);
        $inStock = array_values(array_filter($base, static fn ($p) => is_array($p) && !empty($p['in_stock'])));
        $filters = [];
        $lower = mb_strtolower(trim($message), 'UTF-8');

        if (preg_match('/\b(sub|pana\s+la|până\s+la|max)\s*(\d+)/u', $lower, $m)) {
            $max = (float) $m[2];
            $inStock = array_values(array_filter($inStock, static fn ($p) => self::priceNum($p) <= $max));
            $filters[] = ['type' => 'price_max', 'value' => $max];
        }
        if (preg_match('/\b(peste|min)\s*(\d+)/u', $lower, $m)) {
            $min = (float) $m[2];
            $inStock = array_values(array_filter($inStock, static fn ($p) => self::priceNum($p) >= $min));
            $filters[] = ['type' => 'price_min', 'value' => $min];
        }
        if (preg_match('/\b(mai\s+ieftin|cel\s+mai\s+ieftin)\b/u', $lower)) {
            usort($inStock, static fn ($a, $b) => self::priceNum($a) <=> self::priceNum($b));
            $inStock = array_slice($inStock, 0, max(3, (int) ceil(count($inStock) / 2)));
            $filters[] = ['type' => 'sort', 'value' => 'price_asc'];
        }
        if (preg_match('/\b(mai\s+scump|premium)\b/u', $lower)) {
            usort($inStock, static fn ($a, $b) => self::priceNum($b) <=> self::priceNum($a));
            $inStock = array_slice($inStock, 0, max(3, (int) ceil(count($inStock) / 2)));
            $filters[] = ['type' => 'sort', 'value' => 'price_desc'];
        }

        foreach (['bosch', 'fag', 'trw', 'febi', 'ridex', 'skf'] as $brand) {
            if (str_contains($lower, $brand)) {
                $inStock = array_values(array_filter(
                    $inStock,
                    static fn ($p) => str_contains(mb_strtolower((string) ($p['name'] ?? ''), 'UTF-8'), $brand)
                        || str_contains(mb_strtolower((string) ($p['brand'] ?? ''), 'UTF-8'), $brand)
                ));
                $filters[] = ['type' => 'brand', 'value' => strtoupper($brand)];
                break;
            }
        }

        foreach ([
            'frana' => ['frana', 'frână', 'disc', 'tambur', 'placute', 'plăcuțe'],
            'filtru' => ['filtru', 'filtre'],
            'ulei' => ['ulei', 'lubrif'],
            'rulment' => ['rulment'],
            'suspensie' => ['suspensie', 'amortizor', 'pivot'],
        ] as $cat => $words) {
            foreach ($words as $w) {
                if (str_contains($lower, $w)) {
                    $inStock = array_values(array_filter(
                        $inStock,
                        static fn ($p) => str_contains(mb_strtolower((string) ($p['name'] ?? ''), 'UTF-8'), $w)
                            || str_contains(mb_strtolower((string) ($p['pCategory'] ?? ''), 'UTF-8'), $w)
                    ));
                    $filters[] = ['type' => 'category', 'value' => $cat];
                    break 2;
                }
            }
        }

        if (preg_match('/\b(pentru|merge\s+(la|pe)|compatibil\s+cu)\s+(.+)/ui', $message, $vm)) {
            $vehicleLabel = trim($vm[2] ?? $vm[1] ?? '');
            if ($vehicleLabel !== '') {
                ShopChatSessionService::setVehicle(['label' => $vehicleLabel, 'tokens' => self::tokenize($vehicleLabel)], $sessionState);
                $inStock = ShopChatVehicleService::filterProducts($inStock, ShopChatSessionService::getVehicle($sessionState) ?? []);
                $filters[] = ['type' => 'vehicle', 'value' => $vehicleLabel];
            }
        }

        $vehicle = ShopChatSessionService::getVehicle($sessionState);
        if ($vehicle !== null && preg_match('/\b(la\s+acest\s+auto|acest\s+auto|masina\s+mea)\b/u', $lower)) {
            $inStock = ShopChatVehicleService::filterProducts($inStock, $vehicle);
            $filters[] = ['type' => 'vehicle', 'value' => (string) ($vehicle['label'] ?? '')];
        }

        if (preg_match('/^\s*(\d{1,2})\s*$/u', $message, $nm)) {
            $idx = (int) $nm[1] - 1;
            if ($idx >= 0 && $idx < count($inStock)) {
                $inStock = [$inStock[$idx]];
                $filters[] = ['type' => 'pick', 'value' => $idx + 1];
            }
        }

        foreach ($filters as $f) {
            ShopChatSessionService::pushFilter($f, $sessionState);
        }

        ShopChatSessionService::storeSearchResults($inStock, (string) ($sessionState['last_search_label'] ?? ''), $sessionState, false);

        $hint = $filters !== []
            ? 'Filtre active: ' . implode(', ', array_map(static fn ($f) => (string) ($f['value'] ?? $f['type']), $filters))
            : '';

        return [
            'products' => $inStock,
            'filters' => $filters,
            'reply_hint' => $hint,
        ];
    }

    /** @param array<string, mixed> $p */
    private static function priceNum(array $p): float
    {
        if (isset($p['price_num'])) {
            return (float) $p['price_num'];
        }
        $raw = (string) ($p['price'] ?? '');

        return (float) str_replace(',', '.', preg_replace('/[^\d.,]/', '', $raw) ?? '');
    }

    /** @return list<string> */
    private static function tokenize(string $text): array
    {
        $parts = preg_split('/[\s,.-]+/u', mb_strtolower($text, 'UTF-8')) ?: [];

        return array_values(array_filter($parts, static fn ($t) => mb_strlen($t) >= 2));
    }
}

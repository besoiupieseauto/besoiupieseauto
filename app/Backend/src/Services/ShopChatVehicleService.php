<?php

declare(strict_types=1);

namespace Besoiu\Services;

/**
 * Vehicul în chat — stocare și filtrare compatibilitate text din catalog.
 */
final class ShopChatVehicleService
{
    /**
     * @return array{label:string,tokens:list<string>}|null
     */
    public function parseFromMessage(string $message): ?array
    {
        if (preg_match('/\b([A-HJ-NPR-Z0-9]{17})\b/i', $message, $vin)) {
            return ['label' => 'VIN ' . strtoupper($vin[1]), 'tokens' => [strtolower($vin[1])], 'vin' => strtoupper($vin[1])];
        }

        if (preg_match('/\b(pentru|la|pe)\s+([A-Za-z0-9ăâîșțĂÂÎȘȚ\s.-]{3,40})/ui', $message, $m)) {
            $label = trim($m[2]);
            if ($label !== '' && !preg_match('/\b(frana|filtru|ulei|pret|brand)\b/ui', $label)) {
                return ['label' => $label, 'tokens' => self::tokenize($label)];
            }
        }

        if (preg_match('/\b(am|detin|dețin)\s+([A-Za-z0-9ăâîșțĂÂÎȘȚ\s.-]{3,35})/ui', $message, $m)) {
            $label = trim($m[2]);

            return ['label' => $label, 'tokens' => self::tokenize($label)];
        }

        return null;
    }

    /**
     * @param list<array<string, mixed>> $products
     * @param array<string, mixed> $vehicle
     * @return list<array<string, mixed>>
     */
    public static function filterProducts(array $products, array $vehicle): array
    {
        $tokens = is_array($vehicle['tokens'] ?? null) ? $vehicle['tokens'] : self::tokenize((string) ($vehicle['label'] ?? ''));
        if ($tokens === []) {
            return $products;
        }

        $scored = [];
        foreach ($products as $p) {
            if (!is_array($p)) {
                continue;
            }
            $hay = mb_strtolower(implode(' ', [
                (string) ($p['name'] ?? ''),
                (string) ($p['pCar'] ?? ''),
                (string) ($p['pMarca'] ?? ''),
                (string) ($p['pModel'] ?? ''),
                (string) ($p['pMotorizare'] ?? ''),
                (string) ($p['pNote'] ?? ''),
                (string) ($p['pCompatibilitati'] ?? ''),
            ]), 'UTF-8');

            $score = 0;
            foreach ($tokens as $t) {
                if (str_contains($hay, $t)) {
                    ++$score;
                }
            }
            if ($score > 0) {
                $scored[] = ['score' => $score, 'product' => $p];
            }
        }

        if ($scored === []) {
            return $products;
        }

        usort($scored, static fn ($a, $b) => $b['score'] <=> $a['score']);

        return array_values(array_map(static fn ($row) => $row['product'], $scored));
    }

    /** @return list<string> */
    private static function tokenize(string $text): array
    {
        $stop = ['pentru', 'auto', 'masina', 'mașina', 'model', 'motor'];
        $parts = preg_split('/[\s,.-]+/u', mb_strtolower($text, 'UTF-8')) ?: [];
        $out = [];
        foreach ($parts as $p) {
            if (mb_strlen($p) >= 2 && !in_array($p, $stop, true)) {
                $out[] = $p;
            }
        }

        return array_values(array_unique($out));
    }
}

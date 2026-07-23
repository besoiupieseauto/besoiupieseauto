<?php

declare(strict_types=1);

namespace Besoiu\Services;

use Besoiu\Services\Products\ProduseService;
use Throwable;

/**
 * CRUD produse din Composer — titlu, categorie, pret, stoc, brand, imagini URL, descriere, bulk selectie.
 * Toate actiunile: preview + confirmare obligatorie.
 */
final class SectionAssistantProductCrudService
{
    /** @var array<string, string> */
    private const FIELD_LABELS = [
        'pName' => 'Titlu',
        'pCategory' => 'Categorie',
        'pSubcategory' => 'Subcategorie',
        'pPrice' => 'Pret',
        'pStock' => 'Stoc',
        'pBrand' => 'Brand',
        'pImages' => 'Imagini',
        'pNote' => 'Descriere',
        'status' => 'Status',
        '_image_url' => 'Imagine URL',
    ];

    public static function messageLooksLikeCrud(string $message): bool
    {
        // Doar parseIntent — buildChanges() fără produs dă fals pozitive și regex grele.
        return (new self())->parseIntent($message, []) !== null;
    }

    /** @param array<string, mixed> $context @param array<string, mixed> $input @return array<string, mixed>|null */
    public function tryHandle(string $message, array $context, array $input): ?array
    {
        $pending = is_array($input['pending_action'] ?? null) ? $input['pending_action'] : null;
        $shouldExecute = !empty($input['execute_action']) || !empty($input['confirm_execute']);

        if ($pending !== null && ($pending['type'] ?? '') === 'product_crud' && $shouldExecute) {
            $executed = $this->executePending($pending);

            return $executed ?? $this->errorPlan('Nu am putut executa actiunea CRUD.');
        }

        if ($shouldExecute && $pending === null) {
            return null;
        }

        $selected = $this->normalizeSelectedProducts($input);

        if (count($selected) > 1 || ($selected !== [] && $this->wantsBulkApply($message, $selected))) {
            return $this->tryBulkHandle($message, $selected);
        }

        if (count($selected) === 1 && (
            SectionAssistantQueryHelper::extractProductCode($message) === null
            || SectionAssistantQueryHelper::messageRefersToSelectedProduct($message)
        )) {
            $product = SectionAssistantProductQueries::findByRandomnId((string) $selected[0]['randomn_id']);
            if ($product !== null) {
                $changes = $this->buildChanges($message, $product, []);
                if ($changes !== []) {
                    return $this->buildPreview($product, $changes, $message, false);
                }
            }
        }

        $intent = $this->parseIntent($message, $input);
        if ($intent === null) {
            return null;
        }

        $code = (string) ($intent['code'] ?? '');
        $product = SectionAssistantProductQueries::findByCode($code);
        if ($product === null && count($selected) === 1) {
            $product = SectionAssistantProductQueries::findByRandomnId((string) $selected[0]['randomn_id']);
        }
        if ($product === null) {
            return $this->errorPlan('Nu am gasit produs activ cu codul ' . $code . '. Verifica codul OEM sau bifeaza produsul in lista.');
        }

        $changes = $this->buildChanges($message, $product, $intent);
        if ($changes === []) {
            return null;
        }

        return $this->buildPreview($product, $changes, $message, false);
    }

    /** @param array<string, mixed> $pending @return array<string, mixed>|null */
    public function executePending(array $pending): ?array
    {
        $changes = is_array($pending['changes'] ?? null) ? $pending['changes'] : [];
        $targets = is_array($pending['targets'] ?? null) ? $pending['targets'] : [];

        if ($targets === []) {
            $id = trim((string) ($pending['randomn_id'] ?? ''));
            $code = trim((string) ($pending['code'] ?? ''));
            if ($id === '' || $changes === []) {
                return null;
            }
            $targets = [['randomn_id' => $id, 'code' => $code]];
        }

        if ($changes === []) {
            return null;
        }

        try {
            $service = new ProduseService();
            $updated = 0;
            $failed = 0;
            $sampleCode = '';

            foreach ($targets as $target) {
                if (!is_array($target)) {
                    continue;
                }
                $id = trim((string) ($target['randomn_id'] ?? ''));
                $code = trim((string) ($target['code'] ?? ''));
                if ($id === '') {
                    ++$failed;
                    continue;
                }

                $resolved = $this->resolveChangesForSave($changes, $code);
                if ($resolved === null) {
                    ++$failed;
                    continue;
                }

                if ($service->editProduse($id, $resolved)) {
                    ++$updated;
                    if ($sampleCode === '') {
                        $sampleCode = $code;
                    }
                } else {
                    ++$failed;
                }
            }

            if ($updated === 0) {
                return $this->errorPlan('Niciun produs nu a fost actualizat.');
            }

            $summary = $this->summarizeChanges($changes);
            $isBulk = count($targets) > 1;

            return [
                'source' => 'action_executed',
                'reply_ro' => $isBulk
                    ? sprintf('Am actualizat %d produs(e)%s: %s.', $updated, $failed > 0 ? ' (' . $failed . ' esuate)' : '', $summary)
                    : 'Am actualizat produsul ' . $sampleCode . ' — ' . $summary . '.',
                'intent' => 'configure_products',
                'action' => [
                    'type' => 'product_crud',
                    'updated' => $updated,
                    'failed' => $failed,
                    'changes' => $changes,
                ],
                'cheat_sheet' => [
                    'kind' => 'action_executed',
                    'title' => $isBulk ? 'Actualizare bulk' : 'Produs actualizat',
                    'subtitle' => $isBulk ? $updated . ' produse modificate' : 'Cod ' . $sampleCode,
                    'updated_at' => date('d.m.Y H:i'),
                    'stats' => [
                        ['key' => 'updated', 'label' => 'Actualizate', 'value' => (string) $updated, 'primary' => true],
                        ['key' => 'failed', 'label' => 'Esuate', 'value' => (string) $failed, 'warn' => $failed > 0],
                        ['key' => 'changes', 'label' => 'Modificari', 'value' => $summary],
                    ],
                    'hints' => ['Modificarile sunt live in admin si pe site.'],
                    'shortcuts' => [
                        ['label' => 'Lista produse', 'url' => '/admin/product'],
                    ],
                ],
                'suggestions' => [],
                'next_steps' => [],
            ];
        } catch (Throwable $e) {
            return $this->errorPlan('Eroare CRUD: ' . $e->getMessage());
        }
    }

    /**
     * @param list<array<string, mixed>> $selected
     * @return array<string, mixed>|null
     */
    private function tryBulkHandle(string $message, array $selected): ?array
    {
        $reference = SectionAssistantProductQueries::findByRandomnId((string) ($selected[0]['randomn_id'] ?? '')) ?? [];
        $changes = $this->buildChanges($message, $reference, []);
        if ($changes === []) {
            return $this->errorPlan(
                'Selectezi ' . count($selected) . ' produse — spune ce modifici. Ex: «pret 150», «categorie Frane», «sterge imagini».'
            );
        }

        $targets = [];
        foreach ($selected as $row) {
            $id = trim((string) ($row['randomn_id'] ?? ''));
            if ($id === '') {
                continue;
            }
            $targets[] = [
                'randomn_id' => $id,
                'code' => trim((string) ($row['code'] ?? '')),
            ];
        }

        if ($targets === []) {
            return $this->errorPlan('Produsele selectate nu au ID valid.');
        }

        return $this->buildBulkPreview($targets, $changes, $message);
    }

    /**
     * @param array<string, mixed> $input
     * @return list<array{randomn_id:string,code:string,name?:string}>
     */
    private function normalizeSelectedProducts(array $input): array
    {
        $raw = $input['selected_products'] ?? [];
        if (!is_array($raw)) {
            return [];
        }

        $out = [];
        foreach ($raw as $row) {
            if (!is_array($row)) {
                continue;
            }
            $id = trim((string) ($row['randomn_id'] ?? ''));
            if ($id === '') {
                continue;
            }
            $out[] = [
                'randomn_id' => $id,
                'code' => trim((string) ($row['code'] ?? '')),
                'name' => trim((string) ($row['name'] ?? '')),
            ];
        }

        return $out;
    }

    /**
     * @param list<array<string, mixed>> $selected
     */
    private function wantsBulkApply(string $message, array $selected): bool
    {
        if (count($selected) <= 1) {
            return false;
        }

        $lower = mb_strtolower($message, 'UTF-8');

        return (bool) preg_match('/\b(selectat\w*|bifat\w*|toate|bulk|lot)\b/u', $lower)
            || SectionAssistantQueryHelper::extractProductCode($message) === null;
    }

    /** @param array<string, mixed> $input @return array<string, mixed>|null */
    private function parseIntent(string $message, array $input): ?array
    {
        $message = SectionAssistantQueryHelper::normalizeComposerTypos(trim($message));
        if ($message === '') {
            return null;
        }

        $lower = mb_strtolower($message, 'UTF-8');

        if (!$this->hasCrudVerb($lower) && !$this->hasFieldKeyword($lower)) {
            return null;
        }

        if ($this->isLegacyTitleAppendIntent($lower)) {
            return null;
        }

        if (preg_match('/\bbadge\b/u', $lower) && preg_match('/\b(hot|promo|nou|top)\b/u', $lower)) {
            return null;
        }

        if (preg_match('/\bvitrin/u', $lower) && preg_match('/\b(pune|scoate|adaug|mut)\b/u', $lower)) {
            return null;
        }

        $code = SectionAssistantQueryHelper::extractProductCode($message);
        if ($code === null) {
            return null;
        }

        return ['code' => $code];
    }

    private function hasCrudVerb(string $lower): bool
    {
        return (bool) preg_match(
            '/\b(editez|editeaza|edit|modific|modifica|schimb|schimba|setez|seteaza|pune|actualizez|actualizeaza|sterge|scoate|ascunde|activeaza|dezactiveaza|elimina|salveaza|update|incarca)\b/u',
            $lower
        );
    }

    private function hasFieldKeyword(string $lower): bool
    {
        return (bool) preg_match(
            '/\b(titlu|nume|denumire|pname|categor\w*|subcategor\w*|pret|preț|stoc|brand|imagine|imagini|poza|poze|descriere|pnote|nota|status|ascunde|activeaza|url)\b/u',
            $lower
        );
    }

    private function isLegacyTitleAppendIntent(string $lower): bool
    {
        return (bool) preg_match('/\badaug[aă]?\b/u', $lower)
            && (bool) preg_match('/\b(categor\w*|subcategor\w*)\b/u', $lower)
            && (bool) preg_match('/\b(titlu|denumire|nume|final)\b/u', $lower);
    }

    /**
     * @param array<string, mixed> $product
     * @param array<string, mixed> $intent
     * @return array<string, mixed>
     */
    private function buildChanges(string $message, array $product, array $intent): array
    {
        $lower = mb_strtolower($message, 'UTF-8');
        $changes = [];

        if (preg_match('/\b(sterge|scoate|elimina)\s+(?:toate\s+)?imagin\w*\b/u', $lower)
            || (preg_match('/\b(fara|fără)\s+imagine\b/u', $lower) && $this->hasCrudVerb($lower))) {
            $changes['pImages'] = '[]';
        }

        $imageUrl = $this->extractImageUrl($message);
        if ($imageUrl !== null) {
            $changes['_image_url'] = $imageUrl;
        }

        $note = $this->extractDescription($message);
        if ($note !== null) {
            $changes['pNote'] = $note;
        }

        if (preg_match('/\b(sterge|delete)\s+produs\b/u', $lower)
            || preg_match('/\belimin[aă]?\s+produs\b/u', $lower)) {
            $changes['status'] = '0';
        } elseif (preg_match('/\b(ascunde|dezactiveaza|dezactiv|hide)\b/u', $lower)) {
            $changes['status'] = '0';
        } elseif (preg_match('/\b(activeaza|activez|publica|repune\s+online)\b/u', $lower)) {
            $changes['status'] = '1';
        }

        if (preg_match('/\bpret(?:ul)?\s*(?:la|de|=\s*)?(\d+(?:[.,]\d{1,2})?)(?:\s*(?:ron|lei|ed\s+ron))?/u', $lower, $m)) {
            $changes['pPrice'] = str_replace(',', '.', $m[1]);
        } elseif (preg_match('/\b(?:modific\w*|schimb\w*|setez\w*|pune)\s+pret(?:ul)?\s+(\d+(?:[.,]\d{1,2})?)/u', $lower, $m)) {
            $changes['pPrice'] = str_replace(',', '.', $m[1]);
        } elseif (preg_match('/\bpret(?:ul)?\b/u', $lower)
            && preg_match('/\bla\s+(\d+(?:[.,]\d{1,2})?)\s*(?:ron|lei|ed\s+ron)?\b/u', $lower, $m)) {
            $changes['pPrice'] = str_replace(',', '.', $m[1]);
        }

        if (preg_match('/\bstoc(?:ul)?\s*(?:la|de|=\s*)?(\d+)\b/u', $lower, $m)) {
            $changes['pStock'] = $m[1];
        }

        if (preg_match('/\bbrand(?:ul)?\s*(?:la|pe|=\s*)?([a-z0-9][a-z0-9\-\s]{1,28})\b/u', $lower, $m)) {
            $changes['pBrand'] = strtoupper(trim($m[1]));
        }

        if (preg_match('/\bsubcategor(?:ie|ia)\s*(?:la|pe|in|=\s*)?([a-zà-ž0-9][a-zà-ž0-9\-\s]{1,40})/iu', $message, $m)) {
            $changes['pSubcategory'] = $this->cleanFieldValue($m[1]);
        }

        if (preg_match('/\bcategor(?:ie|ia)\s*(?:la|pe|in|=\s*)?([a-zà-ž0-9][a-zà-ž0-9\-\s]{1,40})/iu', $message, $m)
            && !isset($changes['pSubcategory'])) {
            $val = $this->cleanFieldValue($m[1]);
            if (!preg_match('/^sub/u', mb_strtolower($val, 'UTF-8'))) {
                $changes['pCategory'] = $val;
            }
        }

        $title = $this->extractExplicitTitle($message);
        if ($title !== null) {
            $changes['pName'] = $title;
        }

        if ($product === []) {
            return $changes;
        }

        return $this->filterUnchanged($changes, $product);
    }

    private function extractImageUrl(string $message): ?string
    {
        if (preg_match('/\b(?:imagine|poza|poz[aă])\s*(?:url|de\s+la|from)?\s*(https?:\/\/[^\s\'"<>]+)/iu', $message, $m)) {
            return trim($m[1]);
        }
        if (preg_match('/\b(?:pune|incarca|seteaza)\s+imagine\s+(https?:\/\/[^\s\'"<>]+)/iu', $message, $m)) {
            return trim($m[1]);
        }
        if (preg_match('/\b(imagine|poza)\s+(https?:\/\/[^\s\'"<>]+)/iu', $message, $m)) {
            return trim($m[2]);
        }
        if (preg_match('/(https?:\/\/[^\s\'"<>]+\.(?:jpg|jpeg|png|webp|gif)(?:\?[^\s\'"<>]*)?)/iu', $message, $m)) {
            return trim($m[1]);
        }

        return null;
    }

    private function extractDescription(string $message): ?string
    {
        if (preg_match('/\b(?:descriere|pnote|nota)\s*(?:la|=\s*)["\'](.+?)["\'](?:\s+cod\b|\s+pe\s+selectat|$)/isu', $message, $m)) {
            return trim($m[1]);
        }
        if (preg_match('/\b(?:descriere|pnote)\s+(?:la|=\s*)(.+?)(?:\s+cod\b|\s+pe\s+selectat|\s+selectat|$)/isu', $message, $m)) {
            return $this->cleanFieldValue($m[1]);
        }

        return null;
    }

    private function extractExplicitTitle(string $message): ?string
    {
        if (preg_match('/\b(?:titlu(?:lui)?|nume(?:lui)?|denumire(?:a)?)\s*(?:la|in|cu|=)\s*["\']?(.+?)["\']?(?:\s+cod\b|\s+oem\b|\s+selectat|$)/iu', $message, $m)) {
            return $this->cleanFieldValue($m[1]);
        }
        if (preg_match('/\b(?:schimb|modific)\s+(?:titlu(?:lui)?|nume(?:lui)?|denumirea)\s+(?:la|in|cu)\s+["\']?(.+?)["\']?(?:\s+cod\b|\s+selectat|$)/iu', $message, $m)) {
            return $this->cleanFieldValue($m[1]);
        }

        return null;
    }

    private function cleanFieldValue(string $value): string
    {
        $value = trim(preg_replace('/\s+/u', ' ', $value) ?? '');
        $value = preg_replace('/\s+(?:cod|oem|produs|selectat\w*)\s+.*/iu', '', $value) ?? $value;

        return trim($value);
    }

    /**
     * @param array<string, mixed> $changes
     * @param array<string, mixed> $product
     * @return array<string, mixed>
     */
    private function filterUnchanged(array $changes, array $product): array
    {
        $out = [];
        foreach ($changes as $field => $value) {
            if ($field === '_image_url') {
                $out[$field] = $value;
                continue;
            }

            $current = trim((string) ($product[$field] ?? ''));
            $new = trim((string) $value);

            if ($field === 'pPrice') {
                if (abs((float) str_replace(',', '.', $current) - (float) str_replace(',', '.', $new)) < 0.001) {
                    continue;
                }
            } elseif ($field === 'pImages') {
                if ($current === '' || $current === '[]') {
                    continue;
                }
            } elseif ($field === 'pNote') {
                if ($current === $new) {
                    continue;
                }
            } elseif ($current === $new) {
                continue;
            }
            $out[$field] = $value;
        }

        return $out;
    }

    /**
     * @param array<string, mixed> $changes
     * @return array<string, mixed>|null
     */
    private function resolveChangesForSave(array $changes, string $code): ?array
    {
        $resolved = $changes;
        if (isset($resolved['_image_url'])) {
            $url = (string) $resolved['_image_url'];
            unset($resolved['_image_url']);
            $local = $this->downloadProductImage($url, $code);
            if ($local === '') {
                return null;
            }
            $resolved['pImages'] = json_encode([$local], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            $resolved['pImageSource'] = 'composer_crud';
        }

        return $resolved;
    }

    private function downloadProductImage(string $url, string $code): string
    {
        if (str_starts_with($url, '/uploads/') || str_starts_with($url, '/admin/uploads/')) {
            return $url;
        }

        $this->bootstrapTecdocStock();
        if (function_exists('tecdoc_download_image')) {
            $stored = tecdoc_download_image($url, $code);

            return $stored !== '' ? $stored : '';
        }

        return '';
    }

    private function bootstrapTecdocStock(): void
    {
        if (function_exists('tecdoc_download_image')) {
            return;
        }

        $path = dirname(__DIR__, 3) . '/system/tecdoc_stock.php';
        if (is_file($path)) {
            require_once $path;
        }
    }

    /**
     * @param array<string, mixed> $product
     * @param array<string, mixed> $changes
     * @return array<string, mixed>
     */
    private function buildPreview(array $product, array $changes, string $message, bool $isBulk): array
    {
        $code = trim((string) ($product['pCode'] ?? $product['code'] ?? ''));
        $id = trim((string) ($product['randomn_id'] ?? ''));
        $stats = $this->diffStats($product, $changes);
        $summary = $this->summarizeChanges($changes);

        $pending = [
            'type' => 'product_crud',
            'randomn_id' => $id,
            'code' => $code,
            'changes' => $changes,
        ];

        return [
            'source' => 'action_preview',
            'reply_ro' => 'Voi modifica produsul ' . $code . ': ' . $summary . '. Confirmi?',
            'intent' => 'configure_products',
            'pending_action' => $pending,
            'cheat_sheet' => $this->previewCheatSheet(
                'Confirmare CRUD produs',
                'Cod ' . $code,
                $stats,
                [[
                    'name' => trim((string) ($product['pName'] ?? $product['name'] ?? '')) ?: '-',
                    'brand' => trim((string) ($product['pBrand'] ?? $product['brand'] ?? '')) ?: '-',
                    'code' => $code,
                    'category' => trim((string) ($product['pCategory'] ?? $product['category'] ?? '')) ?: '-',
                    'price' => is_numeric($product['pPrice'] ?? null)
                        ? number_format((float) $product['pPrice'], 2, '.', '') . ' RON'
                        : (string) ($product['price'] ?? '-'),
                    'edit_url' => $id !== '' ? '/admin/editproduse?id=' . rawurlencode($id) : '/admin/product',
                ]],
                'Doresti sa executi aceasta actiune pe produsul ' . $code . '?',
                $pending,
                $id
            ),
            'suggestions' => [],
            'next_steps' => [],
        ];
    }

    /**
     * @param list<array{randomn_id:string,code:string}> $targets
     * @param array<string, mixed> $changes
     * @return array<string, mixed>
     */
    private function buildBulkPreview(array $targets, array $changes, string $message): array
    {
        $summary = $this->summarizeChanges($changes);
        $products = SectionAssistantProductQueries::findManyByRandomnIds(
            array_map(static fn ($t) => (string) ($t['randomn_id'] ?? ''), $targets)
        );

        $pending = [
            'type' => 'product_crud',
            'mode' => 'bulk',
            'targets' => $targets,
            'changes' => $changes,
        ];

        return [
            'source' => 'action_preview',
            'reply_ro' => 'Voi modifica ' . count($targets) . ' produse selectate: ' . $summary . '. Confirmi?',
            'intent' => 'configure_products',
            'pending_action' => $pending,
            'cheat_sheet' => $this->previewCheatSheet(
                'Confirmare CRUD bulk',
                count($targets) . ' produse selectate',
                [
                    ['key' => 'count', 'label' => 'Produse tinta', 'value' => (string) count($targets), 'primary' => true],
                    ['key' => 'changes', 'label' => 'Modificari', 'value' => $summary, 'warn' => true],
                ],
                array_slice(array_map(static fn ($p) => [
                    'name' => (string) ($p['name'] ?? '-'),
                    'brand' => (string) ($p['brand'] ?? '-'),
                    'code' => (string) ($p['code'] ?? '-'),
                    'category' => (string) ($p['category'] ?? '-'),
                    'price' => (string) ($p['price'] ?? '-'),
                    'edit_url' => (string) ($p['edit_url'] ?? '/admin/product'),
                    'randomn_id' => (string) ($p['randomn_id'] ?? ''),
                ], $products), 0, 20),
                'Doresti sa aplici aceste modificari pe ' . count($targets) . ' produse bifate?',
                $pending,
                ''
            ),
            'suggestions' => [],
            'next_steps' => [],
        ];
    }

    /**
     * @param list<array<string, mixed>> $stats
     * @param list<array<string, mixed>> $products
     * @param array<string, mixed> $pending
     * @return array<string, mixed>
     */
    private function previewCheatSheet(
        string $title,
        string $subtitle,
        array $stats,
        array $products,
        string $confirmQuestion,
        array $pending,
        string $editId
    ): array {
        return [
            'kind' => 'action_preview',
            'title' => $title,
            'subtitle' => $subtitle,
            'updated_at' => date('d.m.Y H:i'),
            'stats' => $stats,
            'products' => $products,
            'selective' => count($products) > 1,
            'selectable' => false,
            'products_note' => count($products) > 1
                ? 'Preview primele produse afectate.'
                : 'Produs tinta:',
            'hints' => [
                'Verifica valorile inainte de salvare.',
                'Bulk: bifeaza produse in lista, apoi «pret 150» sau «categorie Frane».',
                'Imagine: «pune imagine https://... cod X» · Descriere: «descriere "text" cod X».',
            ],
            'confirm_required' => true,
            'confirm_question' => $confirmQuestion,
            'confirm' => [
                'label' => 'Da, salveaza modificarile',
                'action' => 'product_crud',
            ],
            'shortcuts' => $editId !== ''
                ? [['label' => 'Edit manual', 'url' => '/admin/editproduse?id=' . rawurlencode($editId)]]
                : [['label' => 'Lista produse', 'url' => '/admin/product']],
        ];
    }

    /**
     * @param array<string, mixed> $changes
     */
    private function summarizeChanges(array $changes): string
    {
        $parts = [];
        foreach ($changes as $field => $value) {
            $label = self::FIELD_LABELS[$field] ?? $field;
            if ($field === '_image_url') {
                $parts[] = $label . ' → ' . mb_substr((string) $value, 0, 48, 'UTF-8') . '…';
            } elseif ($field === 'pImages' && ($value === '[]' || $value === '')) {
                $parts[] = 'sterge imagini';
            } elseif ($field === 'pNote') {
                $parts[] = $label . ' → ' . mb_substr(strip_tags((string) $value), 0, 60, 'UTF-8');
            } else {
                $parts[] = $label . ' → ' . (string) $value;
            }
        }

        return $parts !== [] ? implode('; ', $parts) : 'modificari';
    }

    /**
     * @param array<string, mixed> $product
     * @param array<string, mixed> $changes
     * @return list<array<string, mixed>>
     */
    private function diffStats(array $product, array $changes): array
    {
        $stats = [];
        foreach ($changes as $field => $value) {
            $label = self::FIELD_LABELS[$field] ?? $field;
            if ($field === '_image_url') {
                $before = trim((string) ($product['pImages'] ?? ''));
                $beforeLabel = $before === '' || $before === '[]' ? 'fara imagini' : 'cu imagini';
                $stats[] = ['key' => 'before_img', 'label' => 'Imagini acum', 'value' => $beforeLabel];
                $stats[] = ['key' => 'after_img', 'label' => 'Imagine noua', 'value' => mb_substr((string) $value, 0, 50, 'UTF-8') . '…', 'warn' => true];
                continue;
            }

            $before = trim((string) ($product[$field] ?? ''));
            if ($field === 'pImages') {
                $before = $before === '' || $before === '[]' ? 'fara imagini' : 'cu imagini';
                $after = 'fara imagini';
            } elseif ($field === 'pNote') {
                $after = mb_substr(strip_tags((string) $value), 0, 80, 'UTF-8');
                $before = mb_substr(strip_tags($before), 0, 80, 'UTF-8') ?: '-';
            } elseif ($field === 'status') {
                $before = $before === '0' ? 'ascuns' : 'activ';
                $after = $value === '0' ? 'ascuns' : 'activ';
            } else {
                $after = (string) $value;
            }
            $stats[] = ['key' => 'before_' . $field, 'label' => $label . ' acum', 'value' => $before ?: '-'];
            $stats[] = ['key' => 'after_' . $field, 'label' => $label . ' nou', 'value' => $after, 'warn' => true];
        }

        return $stats;
    }

    /** @return array<string, mixed> */
    private function errorPlan(string $message): array
    {
        return [
            'source' => 'action_error',
            'reply_ro' => $message,
            'intent' => 'configure_products',
            'cheat_sheet' => [
                'kind' => 'action_error',
                'title' => 'Actiune CRUD',
                'subtitle' => $message,
                'updated_at' => date('d.m.Y H:i'),
                'stats' => [],
                'hints' => [
                    'Un produs: «modifica pret 99 cod 30204A»',
                    'Bulk: bifeaza randuri + «pret 150» sau «categorie Frane»',
                    'Imagine URL: «pune imagine https://... cod X»',
                    'Descriere: «descriere "text HTML sau simplu" cod X»',
                ],
            ],
            'suggestions' => [],
            'next_steps' => [],
        ];
    }
}

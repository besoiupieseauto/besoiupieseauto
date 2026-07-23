<?php

declare(strict_types=1);

namespace Besoiu\Services;

/**
 * Configurare și randare tab-uri descriere pagină produs (site public).
 */
final class ProductDescriptionTabsService
{
    private ProductCardFormationService $formation;

    public function __construct(?ProductCardFormationService $formation = null)
    {
        $this->formation = $formation ?? new ProductCardFormationService();
    }

    /** @return array<string, array{label:string, hint:string}> */
    public static function fieldCatalog(): array
    {
        return [
            'denumire' => [
                'label' => 'Denumire / descriere scurtă',
                'hint' => 'pName sau primul rând din pNote',
            ],
            'brand_piesa' => [
                'label' => 'Producător piesă',
                'hint' => 'pBrand',
            ],
            'cod' => [
                'label' => 'Cod articol / OEM',
                'hint' => 'pCode',
            ],
            'categorie' => [
                'label' => 'Categorie magazin',
                'hint' => 'pCategory',
            ],
            'subcategorie' => [
                'label' => 'Subcategorie',
                'hint' => 'pSubcategory',
            ],
            'pozitie' => [
                'label' => 'Poziție montaj',
                'hint' => 'Față/spate/stânga/dreapta din specs',
            ],
            'vehicul_manual' => [
                'label' => 'Vehicul (marcă + model + motor)',
                'hint' => 'pMarca / pModel / pMotorizare',
            ],
            'compat_text' => [
                'label' => 'Text compatibilitate',
                'hint' => 'pCompatibilitati sau pCar',
            ],
            'oem_codes' => [
                'label' => 'Coduri OEM',
                'hint' => 'pOem (CSV)',
            ],
            'livrare' => [
                'label' => 'Livrare',
                'hint' => 'pShipping',
            ],
            'garantie' => [
                'label' => 'Garanție',
                'hint' => 'pWarranty',
            ],
            'retur' => [
                'label' => 'Retur',
                'hint' => 'pReturn',
            ],
            'stare' => [
                'label' => 'Stare / condiție',
                'hint' => 'Implicit: Nou',
            ],
        ];
    }

    /** @return array<string, array{label:string, hint:string}> */
    public static function columnCatalog(): array
    {
        return [
            'car_brand' => ['label' => 'Marcă auto', 'hint' => 'CAR_BRAND din import TecDoc'],
            'car_model' => ['label' => 'Model', 'hint' => 'CAR_MODEL'],
            'car_motor' => ['label' => 'Motorizare', 'hint' => 'CAR_TYP'],
            'car_fuel' => ['label' => 'Combustibil', 'hint' => 'Extras din CAR_TYP dacă există'],
            'car_year' => ['label' => 'An', 'hint' => 'CAR_OF_YEAR – CAR_TO_YEAR'],
            'car_kw' => ['label' => 'Putere (kW)', 'hint' => 'CAR_KW'],
        ];
    }

    /** @return array<string, string> */
    public static function formatCatalog(): array
    {
        return [
            'prose' => 'Text descriptiv (~400 cuvinte)',
            'fields' => 'Listă descriere (dt/dd)',
            'table' => 'Tabel (th/td)',
            'list' => 'Listă puncte (ul/li)',
            'cards' => 'Carduri câmpuri',
            'stats' => 'Statistică / KPI',
            'inline' => 'Rând inline (label: valoare)',
            'chart' => 'Grafic (Chart UI)',
            'html' => 'HTML complet (pNote TecDoc)',
            'reviews' => 'Recenzii (secțiune fixă site)',
        ];
    }

    /** @return list<string> */
    public static function fieldRowFormats(): array
    {
        return ['fields', 'table', 'list', 'cards', 'stats', 'inline'];
    }

    /** @return array<string, mixed> */
    public static function defaultTabsConfig(): array
    {
        return [
            'tabs' => [
                [
                    'id' => 'description',
                    'enabled' => true,
                    'label' => 'Descriere',
                    'format' => 'prose',
                    'prose_words' => ProductProseDescriptionService::DEFAULT_TARGET_WORDS,
                    'source' => 'field_rows',
                    'fields' => [
                        ['id' => 'denumire', 'label' => 'Descrierea', 'enabled' => true],
                        ['id' => 'brand_piesa', 'label' => 'Producătorul', 'enabled' => true],
                        ['id' => 'cod', 'label' => 'Număr articol', 'enabled' => true],
                        ['id' => 'stare', 'label' => 'Condiție', 'enabled' => true, 'static_value' => 'Nou'],
                    ],
                ],
                [
                    'id' => 'fitment',
                    'enabled' => true,
                    'label' => 'Compatibilitate',
                    'format' => 'table',
                    'source' => 'compat_entries',
                    'columns' => [
                        ['id' => 'car_brand', 'label' => 'Marcă', 'enabled' => true],
                        ['id' => 'car_model', 'label' => 'Model', 'enabled' => true],
                        ['id' => 'car_motor', 'label' => 'Motorizare', 'enabled' => true],
                        ['id' => 'car_fuel', 'label' => 'Combustibil', 'enabled' => true],
                        ['id' => 'car_year', 'label' => 'An', 'enabled' => true],
                    ],
                    'footnote' => 'Compatibilitatea finală se confirmă după seria VIN. Contactați-ne pentru verificare.',
                    'fallback_manual' => true,
                ],
                [
                    'id' => 'specs',
                    'enabled' => true,
                    'label' => 'Specificații tehnice',
                    'format' => 'table',
                    'source' => 'field_rows',
                    'fields' => [
                        ['id' => 'brand_piesa', 'label' => 'Producător', 'enabled' => true],
                        ['id' => 'cod', 'label' => 'Cod produs', 'enabled' => true],
                        ['id' => 'categorie', 'label' => 'Categorie', 'enabled' => true],
                        ['id' => 'vehicul_manual', 'label' => 'Compatibil', 'enabled' => true, 'empty_label' => 'Verificați după VIN'],
                        ['id' => 'stare', 'label' => 'Stare', 'enabled' => true, 'static_value' => 'Nou'],
                        ['id' => 'garantie', 'label' => 'Garanție', 'enabled' => true, 'static_value' => 'Conform politicii magazinului'],
                    ],
                ],
                [
                    'id' => 'reviews',
                    'enabled' => true,
                    'label' => 'Recenzii',
                    'format' => 'reviews',
                    'reviews_count' => 1,
                ],
            ],
        ];
    }

    /** @return array<string, mixed> */
    public function loadTabsConfig(?array $product = null): array
    {
        if ($product !== null) {
            return $this->formation->resolveTabsConfigForProduct($product);
        }

        $config = $this->formation->load();
        $tabs = is_array($config['product_tabs'] ?? null) ? $config['product_tabs'] : [];

        return $this->mergeTabsConfig(self::defaultTabsConfig(), $tabs);
    }

    /**
     * @param array<string, mixed> $product
     * @return list<array{id:string, label:string, nav_id:string, pane_id:string, content_html:string}>
     */
    public function buildForProduct(array $product, string $descriptionHtml = ''): array
    {
        try {
            $tabsCfg = $this->formation->resolveTabsConfigForProduct($product);
            $ctx = $this->contextFromProduct($product, $descriptionHtml);
            $built = [];

            foreach ($tabsCfg['tabs'] ?? [] as $tab) {
                if (!is_array($tab) || empty($tab['enabled'])) {
                    continue;
                }

                $id = trim((string) ($tab['id'] ?? ''));
                if ($id === '') {
                    continue;
                }

                $label = trim((string) ($tab['label'] ?? $id));
                if ($id === 'reviews') {
                    $count = max(0, (int) ($tab['reviews_count'] ?? 0));
                    if ($count > 0) {
                        $label .= ' (' . $count . ')';
                    }
                }

                $content = $this->renderTabContent($tab, $ctx);
                if ($content === '' && $id !== 'reviews') {
                    continue;
                }

                $built[] = [
                    'id' => $id,
                    'label' => $label,
                    'nav_id' => 'product-tab-' . $id,
                    'pane_id' => 'product-' . $id . '-content',
                    'content_html' => $content,
                ];
            }

            if ($built !== []) {
                return $built;
            }
        } catch (\Throwable $e) {
            error_log('[ProductDescriptionTabsService] buildForProduct: ' . $e->getMessage());
        }

        return $this->fallbackTabs($product, $descriptionHtml);
    }

    /**
     * @param array<string, mixed>|null $sampleCtx
     * @return array{tabs:list<array<string, mixed>>}
     */
    public function preview(?array $sampleCtx = null): array
    {
        $product = $this->sampleProduct($sampleCtx);
        $description = trim((string) ($sampleCtx['description_html'] ?? ''));
        if ($description === '') {
            $description = $this->sampleDescriptionHtml($product);
        }

        $ctx = $this->contextFromProduct($product, $description);
        $ctx['is_preview'] = true;
        $built = [];
        $tabsCfg = $this->loadTabsConfig();

        foreach ($tabsCfg['tabs'] ?? [] as $tab) {
            if (!is_array($tab) || empty($tab['enabled'])) {
                continue;
            }
            $id = trim((string) ($tab['id'] ?? ''));
            if ($id === '') {
                continue;
            }
            $label = trim((string) ($tab['label'] ?? $id));
            $content = $this->renderTabContent($tab, $ctx);
            if ($content === '' && $id !== 'reviews') {
                continue;
            }
            $built[] = [
                'id' => $id,
                'label' => $label,
                'format' => (string) ($tab['format'] ?? 'fields'),
                'content_html' => $content,
            ];
        }

        return ['tabs' => $built];
    }

    /**
     * @param array<string, mixed> $defaults
     * @param array<string, mixed> $incoming
     * @return array<string, mixed>
     */
    public function mergeTabsConfig(array $defaults, array $incoming): array
    {
        if (!isset($incoming['tabs']) || !is_array($incoming['tabs'])) {
            return $defaults;
        }

        $out = $defaults;
        $out['tabs'] = [];
        $seen = [];

        foreach ($incoming['tabs'] as $tab) {
            if (!is_array($tab)) {
                continue;
            }
            $id = trim((string) ($tab['id'] ?? ''));
            if ($id === '' || isset($seen[$id])) {
                continue;
            }
            $seen[$id] = true;
            $def = $this->findDefaultTab($defaults, $id);
            $merged = is_array($def) ? array_merge($def, $tab) : $tab;
            if (isset($tab['fields']) && is_array($tab['fields'])) {
                $merged['fields'] = $tab['fields'];
            }
            if (isset($tab['columns']) && is_array($tab['columns'])) {
                $merged['columns'] = $tab['columns'];
            }
            if (isset($tab['chart_metrics']) && is_array($tab['chart_metrics'])) {
                $merged['chart_metrics'] = $tab['chart_metrics'];
            }
            $out['tabs'][] = $merged;
        }

        return $out;
    }

    /**
     * @param array<string, mixed> $tab
     * @param array<string, mixed> $ctx
     */
    private function renderTabContent(array $tab, array $ctx): string
    {
        $format = (string) ($tab['format'] ?? 'fields');

        if ($format === 'reviews') {
            return $this->renderReviewsPlaceholder();
        }

        if ($format === 'prose') {
            $ctx['field_values'] = $this->fieldValues($ctx);
            $targetWords = max(150, min(800, (int) ($tab['prose_words'] ?? ProductProseDescriptionService::DEFAULT_TARGET_WORDS)));
            $prose = (new ProductProseDescriptionService())->buildHtml($ctx, $targetWords);
            $cards = $this->renderFieldCards($tab, $ctx);
            if ($cards !== '' && $prose !== '') {
                return '<div class="besoiu-product-tab-prose-wrap">' . $cards . $prose . '</div>';
            }

            return $prose !== '' ? $prose : $cards;
        }

        if ($format === 'html') {
            $html = trim((string) ($ctx['description_html'] ?? ''));
            return $html !== '' ? $html : '';
        }

        if ($format === 'table') {
            $source = (string) ($tab['source'] ?? 'field_rows');
            if ($source === 'compat_entries') {
                return $this->renderCompatTable($tab, $ctx);
            }

            return $this->renderFieldTable($tab, $ctx);
        }

        if ($format === 'list') {
            return $this->renderFieldList($tab, $ctx);
        }

        if ($format === 'cards') {
            return $this->renderFieldCards($tab, $ctx);
        }

        if ($format === 'stats') {
            return $this->renderFieldStats($tab, $ctx);
        }

        if ($format === 'inline') {
            return $this->renderFieldInline($tab, $ctx);
        }

        if ($format === 'chart') {
            return $this->renderChart($tab, $ctx);
        }

        $source = (string) ($tab['source'] ?? 'field_rows');
        if ($source === 'tecdoc_note') {
            $html = trim((string) ($ctx['description_html'] ?? ''));
            return $html !== '' ? $html : $this->renderFieldSheet($tab, $ctx);
        }

        return $this->renderFieldSheet($tab, $ctx);
    }

    /** @param array<string, mixed> $tab @param array<string, mixed> $ctx */
    private function renderFieldSheet(array $tab, array $ctx): string
    {
        $rows = $this->resolveFieldRows($tab, $ctx);
        if ($rows === []) {
            return '';
        }

        $html = '<dl class="tecdoc-desc-sheet">';
        foreach ($rows as $row) {
            $html .= '<dt>' . $this->h((string) $row['label']) . '</dt>';
            $html .= '<dd>' . $this->h((string) $row['value']) . '</dd>';
        }
        $html .= '</dl>';

        return $html;
    }

    /** @param array<string, mixed> $tab @param array<string, mixed> $ctx */
    private function renderFieldTable(array $tab, array $ctx): string
    {
        $rows = $this->resolveFieldRows($tab, $ctx);
        if ($rows === []) {
            return '';
        }

        $html = '<table><tbody>';
        foreach ($rows as $row) {
            $html .= '<tr><th>' . $this->h((string) $row['label']) . '</th><td>' . $this->h((string) $row['value']) . '</td></tr>';
        }
        $html .= '</tbody></table>';

        return $html;
    }

    /** @param array<string, mixed> $tab @param array<string, mixed> $ctx */
    private function renderFieldList(array $tab, array $ctx): string
    {
        $rows = $this->resolveFieldRows($tab, $ctx);
        if ($rows === []) {
            return '';
        }

        $html = '<ul class="besoiu-product-tab-list">';
        foreach ($rows as $row) {
            $html .= '<li><strong>' . $this->h((string) $row['label']) . ':</strong> '
                . $this->h((string) $row['value']) . '</li>';
        }
        $html .= '</ul>';

        return $html;
    }

    /** @param array<string, mixed> $tab @param array<string, mixed> $ctx */
    private function renderFieldCards(array $tab, array $ctx): string
    {
        $rows = $this->resolveFieldRows($tab, $ctx);
        if ($rows === []) {
            return '';
        }

        $html = '<div class="besoiu-product-tab-cards">';
        foreach ($rows as $row) {
            $fieldId = (string) ($row['id'] ?? '');
            $icon = self::fieldIconClass($fieldId);
            $html .= '<div class="besoiu-product-tab-card">'
                . '<div class="besoiu-product-tab-card__icon" aria-hidden="true"><i class="' . $icon . '"></i></div>'
                . '<div class="besoiu-product-tab-card__body">'
                . '<div class="besoiu-product-tab-card__value">' . $this->h((string) $row['value']) . '</div>'
                . '<div class="besoiu-product-tab-card__label">' . $this->h((string) $row['label']) . '</div>'
                . '</div></div>';
        }
        $html .= '</div>';

        return $html;
    }

    /** @param array<string, mixed> $tab @param array<string, mixed> $ctx */
    private function renderFieldStats(array $tab, array $ctx): string
    {
        $rows = $this->resolveFieldRows($tab, $ctx);
        if ($rows === []) {
            return '';
        }

        $html = '<div class="besoiu-product-tab-stats">';
        foreach ($rows as $row) {
            $fieldId = (string) ($row['id'] ?? '');
            $icon = self::fieldIconClass($fieldId);
            $html .= '<div class="besoiu-product-tab-stat">'
                . '<div class="besoiu-product-tab-stat__icon" aria-hidden="true"><i class="' . $icon . '"></i></div>'
                . '<div class="besoiu-product-tab-stat__value">' . $this->h((string) $row['value']) . '</div>'
                . '<div class="besoiu-product-tab-stat__label">' . $this->h((string) $row['label']) . '</div>'
                . '</div>';
        }
        $html .= '</div>';

        return $html;
    }

    /** @param array<string, mixed> $tab @param array<string, mixed> $ctx */
    private function renderFieldInline(array $tab, array $ctx): string
    {
        $rows = $this->resolveFieldRows($tab, $ctx);
        if ($rows === []) {
            return '';
        }

        $html = '<div class="besoiu-product-tab-inline">';
        foreach ($rows as $index => $row) {
            if ($index > 0) {
                $html .= '<span class="besoiu-product-tab-inline__sep" aria-hidden="true">·</span>';
            }
            $html .= '<span class="besoiu-product-tab-inline__item">'
                . '<span class="besoiu-product-tab-inline__label">' . $this->h((string) $row['label']) . ':</span> '
                . '<span class="besoiu-product-tab-inline__value">' . $this->h((string) $row['value']) . '</span>'
                . '</span>';
        }
        $html .= '</div>';

        return $html;
    }

    /** @param array<string, mixed> $tab @param array<string, mixed> $ctx */
    private function renderChart(array $tab, array $ctx): string
    {
        $product = is_array($ctx['product'] ?? null) ? $ctx['product'] : [];
        if ($product === []) {
            return '';
        }

        $chartService = new ProductTabChartService();
        $allowSample = !empty($ctx['is_preview']);
        $payload = $chartService->buildPayload($product, $tab, $allowSample, $ctx);
        if ($payload === null) {
            return '<p class="besoiu-product-tab-chart-empty">Nu există date suficiente pentru acest grafic.</p>';
        }

        $json = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($json === false) {
            return '';
        }

        $title = trim((string) ($payload['title'] ?? ''));
        $chartId = 'besoiu-chart-' . substr(md5($json . ($tab['id'] ?? '')), 0, 10);
        $jsonAttr = htmlspecialchars($json, ENT_QUOTES, 'UTF-8');

        $html = '<div class="besoiu-product-tab-chart-wrap">';
        if ($title !== '') {
            $html .= '<h4 class="besoiu-product-tab-chart__title">' . $this->h($title) . '</h4>';
        }
        $html .= '<div class="besoiu-product-tab-chart" data-besoiu-chart="' . $jsonAttr . '">';
        $html .= '<canvas id="' . $this->h($chartId) . '" role="img" aria-label="' . $this->h($title) . '"></canvas>';
        $html .= '</div></div>';

        return $html;
    }

    /** @param array<string, mixed> $tab @param array<string, mixed> $ctx */
    private function renderCompatTable(array $tab, array $ctx): string
    {
        $columns = $this->enabledColumns($tab);
        if ($columns === []) {
            return '';
        }

        $entries = is_array($ctx['entries'] ?? null) ? $ctx['entries'] : [];
        if ($entries === []) {
            $entries = $this->entriesFromCompatText(
                trim((string) ($ctx['pCompatibilitati'] ?? ''))
            );
        }
        if ($entries === []) {
            $entries = $this->entriesFromCompatText(
                trim((string) ($ctx['pMotorizare'] ?? ''))
            );
        }

        $html = '<table><thead><tr>';
        foreach ($columns as $col) {
            $html .= '<th>' . $this->h((string) $col['label']) . '</th>';
        }
        $html .= '</tr></thead><tbody>';

        $rowCount = 0;
        foreach ($entries as $entry) {
            if (!is_array($entry)) {
                continue;
            }
            $cells = $this->compatCells($entry, $columns);
            if (!$this->compatRowHasData($cells)) {
                continue;
            }
            $html .= '<tr>';
            foreach ($cells as $cell) {
                $html .= '<td>' . $this->h($cell !== '' ? $cell : '—') . '</td>';
            }
            $html .= '</tr>';
            $rowCount++;
            if ($rowCount >= 40) {
                break;
            }
        }

        if ($rowCount === 0 && !empty($tab['fallback_manual'])) {
            $manualCells = [];
            foreach ($columns as $col) {
                $id = (string) ($col['id'] ?? '');
                $manualCells[] = match ($id) {
                    'car_brand' => trim((string) ($ctx['pMarca'] ?? '')),
                    'car_model' => trim((string) ($ctx['pModel'] ?? '')),
                    'car_motor' => trim((string) ($ctx['pMotorizare'] ?? '')),
                    default => '',
                };
            }
            if ($this->compatRowHasData($manualCells)) {
                $html .= '<tr>';
                foreach ($manualCells as $cell) {
                    $html .= '<td>' . $this->h($cell !== '' ? $cell : '—') . '</td>';
                }
                $html .= '</tr>';
                $rowCount++;
            }
        }

        if ($rowCount === 0) {
            $fallbackEntries = $this->entriesFromDescriptionHtml((string) ($ctx['description_html'] ?? ''));
            if ($fallbackEntries === []) {
                $compatText = trim((string) ($ctx['pCompatibilitati'] ?? ''));
                if ($compatText === '') {
                    $compatText = $this->extractCompatTextFromDescription((string) ($ctx['description_html'] ?? ''));
                }
                $fallbackEntries = $this->entriesFromCompatText($compatText);
            }
            foreach ($fallbackEntries as $entry) {
                if (!is_array($entry)) {
                    continue;
                }
                $cells = $this->compatCells($entry, $columns);
                if (!$this->compatRowHasData($cells)) {
                    continue;
                }
                $html .= '<tr>';
                foreach ($cells as $cell) {
                    $html .= '<td>' . $this->h($cell !== '' ? $cell : '—') . '</td>';
                }
                $html .= '</tr>';
                $rowCount++;
                if ($rowCount >= 40) {
                    break;
                }
            }
        }

        if ($rowCount === 0) {
            $colspan = max(1, count($columns));
            $category = mb_strtolower(trim((string) ($ctx['pCategory'] ?? '')), 'UTF-8');
            $isUniversal = str_contains($category, 'ulei')
                || str_contains($category, 'consum')
                || str_contains(mb_strtolower(trim((string) ($ctx['piece_name'] ?? '')), 'UTF-8'), 'ulei');
            $message = $isUniversal
                ? 'Produs universal (ex. ulei, consumabile) — compatibilitatea depinde de specificațiile tehnice (vâscozitate, normă API/ACEA). Trimite-ne seria VIN sau modelul motorului pentru confirmare.'
                : 'Nu avem încă o listă de vehicule compatibile salvată pentru acest produs. Contactați-ne cu seria VIN pentru verificare.';
            $html .= '<tr><td colspan="' . $colspan . '">' . $this->h($message) . '</td></tr>';
        }

        $footnote = trim((string) ($tab['footnote'] ?? ''));
        if ($footnote !== '') {
            $colspan = count($columns);
            $html .= '<tr><td colspan="' . $colspan . '" style="font-size:13px;color:var(--muted)">'
                . $this->h($footnote) . '</td></tr>';
        }

        $html .= '</tbody></table>';

        return $html;
    }

    /** @param array<string, mixed> $tab @param array<string, mixed> $ctx @return list<array{label:string, value:string}> */
    private function resolveFieldRows(array $tab, array $ctx): array
    {
        $fields = is_array($tab['fields'] ?? null) ? $tab['fields'] : [];
        $values = $this->fieldValues($ctx);
        $rows = [];

        foreach ($fields as $field) {
            if (!is_array($field) || empty($field['enabled'])) {
                continue;
            }
            $id = (string) ($field['id'] ?? '');
            $label = trim((string) ($field['label'] ?? ''));
            if ($label === '') {
                $label = self::fieldCatalog()[$id]['label'] ?? $id;
            }

            $static = trim((string) ($field['static_value'] ?? ''));
            $value = $static !== '' ? $static : trim((string) ($values[$id] ?? ''));
            if ($value === '') {
                $empty = trim((string) ($field['empty_label'] ?? ''));
                if ($empty !== '') {
                    $value = $empty;
                } else {
                    continue;
                }
            }

            $rows[] = ['id' => $id, 'label' => $label, 'value' => $value];
        }

        return $rows;
    }

    /** @param array<string, mixed> $ctx @return array<string, string> */
    public function fieldValuesForChart(array $ctx): array
    {
        return $this->fieldValues($ctx);
    }

    /** @param array<string, mixed> $ctx @return array<string, string> */
    private function fieldValues(array $ctx): array
    {
        $segmentCtx = [
            'piece_name' => $ctx['piece_name'] ?? '',
            'brand' => $ctx['pBrand'] ?? '',
            'code' => $ctx['pCode'] ?? '',
            'pMarca' => $ctx['pMarca'] ?? '',
            'pModel' => $ctx['pModel'] ?? '',
            'pMotorizare' => $ctx['pMotorizare'] ?? '',
            'pCategory' => $ctx['pCategory'] ?? '',
            'pSubcategory' => $ctx['pSubcategory'] ?? '',
            'specs_text' => $ctx['specs_text'] ?? '',
            'entries' => $ctx['entries'] ?? [],
        ];

        try {
            $segments = $this->formation->segmentValues(
                ProductCardFormationService::CHANNEL_WEBSITE,
                $segmentCtx
            );
        } catch (\Throwable) {
            $segments = [];
        }

        $denumire = $this->resolveDenumireValue($ctx);

        $vehicul = trim((string) ($segments['vehicul_manual'] ?? ''));
        if ($vehicul === '') {
            $parts = array_values(array_filter([
                trim((string) ($ctx['pMarca'] ?? '')),
                trim((string) ($ctx['pModel'] ?? '')),
                trim((string) ($ctx['pMotorizare'] ?? '')),
            ]));
            $vehicul = $parts !== [] ? implode(' ', $parts) : '';
        }

        return [
            'denumire' => $denumire,
            'brand_piesa' => trim((string) ($segments['brand_piesa'] ?? $ctx['pBrand'] ?? '')),
            'cod' => trim((string) ($segments['cod'] ?? $ctx['pCode'] ?? '')),
            'categorie' => trim((string) ($ctx['pCategory'] ?? '')),
            'subcategorie' => trim((string) ($ctx['pSubcategory'] ?? '')),
            'pozitie' => trim((string) ($segments['pozitie'] ?? '')),
            'vehicul_manual' => $vehicul,
            'compat_text' => trim((string) ($ctx['pCompatibilitati'] ?? $ctx['pCar'] ?? '')),
            'oem_codes' => trim((string) ($ctx['pOem'] ?? '')),
            'livrare' => trim((string) ($ctx['pShipping'] ?? '')),
            'garantie' => trim((string) ($ctx['pWarranty'] ?? '')),
            'retur' => trim((string) ($ctx['pReturn'] ?? '')),
            'stare' => 'Nou',
        ];
    }

    /** @param array<string, mixed> $ctx */
    private function resolveDenumireValue(array $ctx): string
    {
        foreach ($this->descriptionRowsFromHtml((string) ($ctx['description_html'] ?? '')) as $row) {
            $label = mb_strtolower((string) ($row['label'] ?? ''), 'UTF-8');
            if (str_contains($label, 'descrier') || $label === 'denumire') {
                $value = trim((string) ($row['value'] ?? ''));
                if ($value !== '') {
                    return $value;
                }
            }
        }

        if (function_exists('tecdoc_desc_plain_text_to_rows')) {
            $plain = trim((string) ($ctx['specs_text'] ?? ''));
            if ($plain !== '' && function_exists('tecdoc_desc_is_plain_spec_sheet') && tecdoc_desc_is_plain_spec_sheet($plain)) {
                foreach (tecdoc_desc_plain_text_to_rows($plain) as $row) {
                    $label = mb_strtolower((string) ($row['label'] ?? ''), 'UTF-8');
                    if (str_contains($label, 'descrier') || $label === 'denumire') {
                        $value = trim((string) ($row['value'] ?? ''));
                        if ($value !== '') {
                            return $value;
                        }
                    }
                }
            }
        }

        $name = trim((string) ($ctx['piece_name'] ?? ''));
        if ($name !== '') {
            return $name;
        }

        return $this->firstDescriptionLine((string) ($ctx['description_html'] ?? ''));
    }

    /** @return list<array{label:string, value:string}> */
    private function descriptionRowsFromHtml(string $html): array
    {
        if ($html === '' || !preg_match('/<dt[^>]*>/i', $html)) {
            return [];
        }

        $rows = [];
        if (preg_match_all('/<dt[^>]*>(.*?)<\/dt>\s*<dd[^>]*>(.*?)<\/dd>/is', $html, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $match) {
                $label = trim(strip_tags($match[1]));
                $value = trim(strip_tags($match[2]));
                if ($label !== '' && $value !== '') {
                    $rows[] = ['label' => $label, 'value' => $value];
                }
            }
        }

        return $rows;
    }

    /** @param array<string, mixed> $entry @param list<array<string, mixed>> $columns @return list<string> */
    private function compatCells(array $entry, array $columns): array
    {
        $cells = [];
        foreach ($columns as $col) {
            $id = (string) ($col['id'] ?? '');
            $cells[] = match ($id) {
                'car_brand' => trim((string) ($entry['CAR_BRAND'] ?? '')),
                'car_model' => trim((string) ($entry['CAR_MODEL'] ?? '')),
                'car_motor' => $this->formatMotorCell($entry),
                'car_fuel' => $this->extractFuel((string) ($entry['CAR_TYP'] ?? '')),
                'car_year' => $this->formatYearRange(
                    trim((string) ($entry['CAR_OF_YEAR'] ?? '')),
                    trim((string) ($entry['CAR_TO_YEAR'] ?? ''))
                ),
                'car_kw' => trim((string) ($entry['CAR_KW'] ?? '')),
                default => '',
            };
        }

        return $cells;
    }

    /**
     * @param array<string, mixed> $product
     * @return list<array{id:string, label:string, nav_id:string, pane_id:string, content_html:string}>
     */
    private function fallbackTabs(array $product, string $descriptionHtml): array
    {
        $html = $descriptionHtml !== '' ? $descriptionHtml : '<p>Descriere indisponibilă.</p>';

        return [[
            'id' => 'description',
            'label' => 'Descriere',
            'nav_id' => 'product-tab-desc',
            'pane_id' => 'product-desc-content',
            'content_html' => $html,
        ]];
    }

    /** @param array<string, mixed> $tab @return list<array<string, mixed>> */
    private function enabledColumns(array $tab): array
    {
        $columns = [];
        foreach ($tab['columns'] ?? [] as $col) {
            if (!is_array($col) || empty($col['enabled'])) {
                continue;
            }
            $id = (string) ($col['id'] ?? '');
            $label = trim((string) ($col['label'] ?? ''));
            if ($label === '') {
                $label = self::columnCatalog()[$id]['label'] ?? $id;
            }
            $columns[] = ['id' => $id, 'label' => $label];
        }

        return $columns;
    }

    /**
     * @param array<string, mixed> $product
     * @return array<string, mixed>
     */
    private function contextFromProduct(array $product, string $descriptionHtml): array
    {
        $raw = json_decode((string) ($product['raw_json'] ?? ''), true);
        if (!is_array($raw)) {
            $raw = [];
        }
        $rows = [];
        foreach (['source_rows', 'rows', 'tecdoc_entries', 'entries'] as $key) {
            if (!empty($raw[$key]) && is_array($raw[$key])) {
                $rows = array_values(array_filter($raw[$key], 'is_array'));
                if ($rows !== []) {
                    break;
                }
            }
        }
        $entries = $this->entriesFromRows($rows);
        if ($entries === [] && is_array($raw['compat'] ?? null)) {
            $entries = $this->filterCompatEntriesForDisplay($raw['compat']);
        }
        if ($entries === [] && $descriptionHtml !== '') {
            $entries = $this->entriesFromDescriptionHtml($descriptionHtml);
        }
        if ($entries === []) {
            $entries = $this->entriesFromCompatText(trim((string) ($product['pCompatibilitati'] ?? '')));
        }

        return [
            'product' => $product,
            'piece_name' => trim((string) ($product['pName'] ?? '')),
            'pBrand' => trim((string) ($product['pBrand'] ?? '')),
            'pCode' => trim((string) ($product['pCode'] ?? '')),
            'pMarca' => trim((string) ($product['pMarca'] ?? '')),
            'pModel' => trim((string) ($product['pModel'] ?? '')),
            'pMotorizare' => trim((string) ($product['pMotorizare'] ?? '')),
            'pCategory' => trim((string) ($product['pCategory'] ?? '')),
            'pSubcategory' => trim((string) ($product['pSubcategory'] ?? '')),
            'pCar' => trim((string) ($product['pCar'] ?? '')),
            'pCompatibilitati' => trim((string) ($product['pCompatibilitati'] ?? '')),
            'pOem' => trim((string) ($product['pOem'] ?? '')),
            'pShipping' => trim((string) ($product['pShipping'] ?? '')),
            'pWarranty' => trim((string) ($product['pWarranty'] ?? '')),
            'pReturn' => trim((string) ($product['pReturn'] ?? '')),
            'specs_text' => trim((string) ($product['pNote'] ?? $product['pNoteWebsite'] ?? '')),
            'description_html' => $descriptionHtml,
            'entries' => $entries,
        ];
    }

    /** @param list<array<string, mixed>> $rows @return list<array<string, mixed>> */
    private function entriesFromRows(array $rows): array
    {
        if ($rows === []) {
            return [];
        }

        $first = $rows[0] ?? [];
        if (is_array($first) && (isset($first['CAR_BRAND']) || isset($first['car_brand']))) {
            return $this->filterCompatEntriesForDisplay(
                array_values(array_filter($rows, 'is_array'))
            );
        }

        $importLib = dirname(__DIR__) . '/Controllers/Produse/import_lib.php';
        $baseLib = dirname(__DIR__) . '/Controllers/Produse/import_base_lib.php';
        if (!is_file($importLib) || !is_file($baseLib)) {
            return [];
        }

        try {
            require_once $importLib;
            require_once $baseLib;

            if (!function_exists('import_base_entries_from_rows') || !function_exists('import_base_filter_entries')) {
                return [];
            }

            $parsed = import_base_entries_from_rows($rows);
            $entries = $this->filterCompatEntriesForDisplay(
                import_base_filter_entries($parsed)
            );
            if ($entries !== []) {
                return $entries;
            }

            // Afișare pagină produs: păstrează compatibilități vechi / filtrate la import (ex. ulei).
            return $this->filterCompatEntriesForDisplay(
                import_base_filter_entries($parsed, -1)
            );
        } catch (\Throwable $e) {
            error_log('[ProductDescriptionTabsService] entriesFromRows: ' . $e->getMessage());

            return [];
        }
    }

    /**
     * @param list<array<string, mixed>> $entries
     * @return list<array<string, mixed>>
     */
    private function filterCompatEntriesForDisplay(array $entries): array
    {
        return array_values(array_filter($entries, function (array $entry): bool {
            foreach (['CAR_BRAND', 'CAR_MODEL', 'CAR_TYP', 'CAR_OF_YEAR', 'CAR_TO_YEAR', 'CAR_KW'] as $key) {
                if (trim((string) ($entry[$key] ?? '')) !== '') {
                    return true;
                }
            }

            return false;
        }));
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function entriesFromCompatText(string $text): array
    {
        $text = trim($text);
        if ($text === '') {
            return [];
        }

        $entries = [];
        foreach (preg_split('/\r?\n/u', $text) ?: [] as $line) {
            $line = trim(ltrim(trim((string) $line), '-•'));
            if ($line === '' || mb_stripos($line, 'compatibilit', 0, 'UTF-8') === 0) {
                continue;
            }

            if (str_contains($line, '|')) {
                $parts = array_map('trim', explode('|', $line));
                $years = $parts[4] ?? '';
                $yearFrom = '';
                $yearTo = '';
                if (preg_match('/(\d{4})\s*[-–]\s*(\d{4}|prezent|\?)/iu', $years, $ym)) {
                    $yearFrom = $ym[1];
                    $yearTo = trim((string) ($ym[2] ?? ''));
                }
                $kw = '';
                if (preg_match('/(\d+)\s*kW/iu', $line, $km)) {
                    $kw = $km[1];
                }
                $entries[] = [
                    'CAR_BRAND' => $parts[0] ?? '',
                    'CAR_MODEL' => $parts[1] ?? '',
                    'CAR_TYP' => $parts[2] ?? '',
                    'CAR_OF_YEAR' => $yearFrom,
                    'CAR_TO_YEAR' => $yearTo,
                    'CAR_KW' => $kw,
                ];
                continue;
            }

            // Format vechi: HYUNDAI: COUPE I (RD) 1.6 16V (1998-2002); COUPE I (RD) 2.0 (1999-2002)
            if (preg_match('/^([A-ZÀ-ÿ0-9][A-ZÀ-ÿ0-9 \/\-]{1,40})\s*:\s*(.+)$/u', $line, $bm)) {
                $brand = trim($bm[1]);
                foreach (preg_split('/\s*;\s*/u', trim($bm[2])) ?: [] as $item) {
                    $item = trim((string) $item);
                    if ($item === '') {
                        continue;
                    }
                    $parsed = $this->parseCompatItemLine($item);
                    $entries[] = [
                        'CAR_BRAND' => $brand,
                        'CAR_MODEL' => $parsed['model'],
                        'CAR_TYP' => $parsed['motor'],
                        'CAR_OF_YEAR' => $parsed['year_from'],
                        'CAR_TO_YEAR' => $parsed['year_to'],
                        'CAR_KW' => $parsed['kw'],
                    ];
                }
                continue;
            }

            $parsed = $this->parseCompatItemLine($line);
            $entries[] = [
                'CAR_BRAND' => '',
                'CAR_MODEL' => $parsed['model'] !== '' ? $parsed['model'] : $line,
                'CAR_TYP' => $parsed['motor'],
                'CAR_OF_YEAR' => $parsed['year_from'],
                'CAR_TO_YEAR' => $parsed['year_to'],
                'CAR_KW' => $parsed['kw'],
            ];
        }

        return $this->filterCompatEntriesForDisplay($entries);
    }

    /**
     * Din HTML Base nested: Marcă → Model → Motorizare.
     *
     * @return list<array<string, mixed>>
     */
    private function entriesFromDescriptionHtml(string $html): array
    {
        $html = trim($html);
        if ($html === '' || !preg_match('/Compatibil\s+cu/iu', $html)) {
            return [];
        }

        if (!preg_match(
            '/Compatibil\s+cu(?:\s+urmatoarele\s+modele\s+auto)?\s*:?\s*<\/(?:b|strong|p)[^>]*>\s*<ul>(.*)$/is',
            $html,
            $m
        )) {
            return [];
        }

        $block = (string) $m[1];
        if (preg_match('/^(.*?)(?:<p[^>]*>\s*<b>\s*Coduri\s+OE)/is', $block, $cut)) {
            $block = (string) $cut[1];
        }

        $entries = [];
        // <li><b>BRAND</b><ul>…models…
        if (preg_match_all('/<li>\s*<b>([^<]+)<\/b>\s*<ul>(.*?)<\/ul>\s*<\/li>/is', $block, $brands, PREG_SET_ORDER)) {
            foreach ($brands as $brandMatch) {
                $brand = trim(html_entity_decode($brandMatch[1], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'));
                $modelsHtml = (string) $brandMatch[2];
                // nested motors: <li><b>MODEL</b><ul><li>motor…</li>
                if (preg_match_all('/<li>\s*<b>([^<]+)<\/b>\s*<ul>(.*?)<\/ul>\s*<\/li>/is', $modelsHtml, $models, PREG_SET_ORDER)) {
                    foreach ($models as $modelMatch) {
                        $model = trim(html_entity_decode($modelMatch[1], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'));
                        if (preg_match_all('/<li>(.*?)<\/li>/is', (string) $modelMatch[2], $motors)) {
                            foreach ($motors[1] as $motorHtml) {
                                $motorLine = trim(html_entity_decode(strip_tags((string) $motorHtml), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'));
                                $parsed = $this->parseCompatItemLine($motorLine);
                                $entries[] = [
                                    'CAR_BRAND' => $brand,
                                    'CAR_MODEL' => $model,
                                    'CAR_TYP' => $parsed['motor'] !== '' ? $parsed['motor'] : $motorLine,
                                    'CAR_OF_YEAR' => $parsed['year_from'],
                                    'CAR_TO_YEAR' => $parsed['year_to'],
                                    'CAR_KW' => $parsed['kw'],
                                ];
                            }
                        }
                    }
                }
                // single line model+motor: <li><b>MODEL</b> rest (years)</li>
                if (preg_match_all('/<li>\s*<b>([^<]+)<\/b>(.*?)(<\/li>)/is', $modelsHtml, $singles, PREG_SET_ORDER)) {
                    foreach ($singles as $single) {
                        if (str_contains((string) $single[0], '<ul>')) {
                            continue;
                        }
                        $model = trim(html_entity_decode($single[1], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'));
                        $rest = trim(html_entity_decode(strip_tags((string) $single[2]), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'));
                        $parsed = $this->parseCompatItemLine(trim($model . ' ' . $rest));
                        $entries[] = [
                            'CAR_BRAND' => $brand,
                            'CAR_MODEL' => $model,
                            'CAR_TYP' => $parsed['motor'] !== '' ? $parsed['motor'] : $rest,
                            'CAR_OF_YEAR' => $parsed['year_from'],
                            'CAR_TO_YEAR' => $parsed['year_to'],
                            'CAR_KW' => $parsed['kw'],
                        ];
                    }
                }
            }
        }

        return $this->filterCompatEntriesForDisplay($entries);
    }

    /**
     * @return array{model:string,motor:string,year_from:string,year_to:string,kw:string}
     */
    private function parseCompatItemLine(string $line): array
    {
        $line = trim(preg_replace('/\s+/u', ' ', $line) ?? $line);
        $out = ['model' => '', 'motor' => '', 'year_from' => '', 'year_to' => '', 'kw' => ''];
        if ($line === '') {
            return $out;
        }

        if (preg_match('/\((\d{4})\s*[-–]\s*([^)]+)\)\s*$/u', $line, $ym)) {
            $out['year_from'] = $ym[1];
            $out['year_to'] = trim($ym[2]);
            $line = trim(substr($line, 0, -strlen($ym[0])));
        }
        if (preg_match('/\b(\d+)\s*KW\b/iu', $line, $km)) {
            $out['kw'] = $km[1];
            $line = trim(preg_replace('/\b\d+\s*KW\b/iu', '', $line) ?? $line);
        }

        // MODEL (COD) motor…
        if (preg_match('/^(.+?\([^)]+\))\s+(.+)$/u', $line, $mm)) {
            $out['model'] = trim($mm[1]);
            $out['motor'] = trim($mm[2]);

            return $out;
        }

        // Dacă linia e doar motorizare (fără model)
        if (preg_match('/^\d/u', $line) || preg_match('/\b(dCi|TDI|TSI|TFSI|KW)\b/iu', $line)) {
            $out['motor'] = $line;

            return $out;
        }

        $out['model'] = $line;

        return $out;
    }

    private function extractCompatTextFromDescription(string $html): string
    {
        if ($html === '') {
            return '';
        }

        if (function_exists('import_compat_text_from_description_html')) {
            $fromBase = trim((string) import_compat_text_from_description_html($html));
            if ($fromBase !== '') {
                return $fromBase;
            }
        }

        if (preg_match('/Compatibilit[aă]ți?\s*:?\s*<\/dt>\s*<dd[^>]*>(.*?)<\/dd>/isu', $html, $match)) {
            return trim(strip_tags($match[1]));
        }

        if (preg_match('/Compatibilit[aă]ți?\s*:\s*(.+)$/imu', strip_tags($html), $match)) {
            return trim((string) ($match[1] ?? ''));
        }

        return '';
    }

    /** @param list<string> $cells */
    private function compatRowHasData(array $cells): bool
    {
        foreach ($cells as $cell) {
            if (trim((string) $cell) !== '') {
                return true;
            }
        }

        return false;
    }

    /** @param array<string, mixed> $defaults */
    private function findDefaultTab(array $defaults, string $id): ?array
    {
        foreach ($defaults['tabs'] ?? [] as $tab) {
            if (is_array($tab) && (string) ($tab['id'] ?? '') === $id) {
                return $tab;
            }
        }

        return null;
    }

    private function tabFormatById(string $id): string
    {
        foreach ($this->loadTabsConfig()['tabs'] ?? [] as $tab) {
            if (is_array($tab) && (string) ($tab['id'] ?? '') === $id) {
                return (string) ($tab['format'] ?? 'fields');
            }
        }

        return 'fields';
    }

    /** @param array<string, mixed>|null $sampleCtx @return array<string, mixed> */
    private function sampleProduct(?array $sampleCtx): array
    {
        $ctx = ProductCardFormationService::sampleContext();
        if (is_array($sampleCtx)) {
            $ctx = array_merge($ctx, $sampleCtx);
        }

        return [
            'id' => 0,
            'randomn_id' => 'preview_sample',
            'pName' => (string) ($ctx['piece_name'] ?? 'Plăcuțe frână'),
            'pBrand' => (string) ($ctx['brand'] ?? 'BOSCH'),
            'pCode' => (string) ($ctx['code'] ?? '0 986 424 268'),
            'pPrice' => (string) ($ctx['pPrice'] ?? '189.99'),
            'pBasePrice' => (string) ($ctx['pBasePrice'] ?? '142.50'),
            'pMarca' => (string) ($ctx['pMarca'] ?? 'Audi'),
            'pModel' => (string) ($ctx['pModel'] ?? 'A3 Sportback'),
            'pMotorizare' => (string) ($ctx['pMotorizare'] ?? '1.6 TDI'),
            'pCategory' => (string) ($ctx['pCategory'] ?? 'Frâne'),
            'pSubcategory' => (string) ($ctx['pSubcategory'] ?? 'Plăcuțe frână'),
            'pNote' => (string) ($ctx['specs_text'] ?? ''),
            'raw_json' => json_encode([
                'supplier_price' => [
                    'net_price' => 118.40,
                    'supplier' => 'autonet',
                ],
                'rows' => [
                    [
                        'car brand' => 'AUDI',
                        'car model' => 'A3 Sportback',
                        'car typ' => '1.6 TDI',
                        'car of year' => '2008',
                        'car to year' => '2012',
                        'car kw' => '77',
                    ],
                    [
                        'car brand' => 'VW',
                        'car model' => 'Golf VI',
                        'car typ' => '1.6 TDI',
                        'car of year' => '2009',
                        'car to year' => '2013',
                        'car kw' => '77',
                    ],
                ],
            ], JSON_UNESCAPED_UNICODE),
        ];
    }

    /** @param array<string, mixed> $product */
    private function sampleDescriptionHtml(array $product): string
    {
        return '<dl class="tecdoc-desc-sheet">'
            . '<dt>Descrierea</dt><dd>' . $this->h((string) ($product['pName'] ?? '')) . '</dd>'
            . '<dt>Producătorul</dt><dd>' . $this->h((string) ($product['pBrand'] ?? '')) . '</dd>'
            . '<dt>Număr articol</dt><dd>' . $this->h((string) ($product['pCode'] ?? '')) . '</dd>'
            . '<dt>Condiție</dt><dd>Nou</dd>'
            . '</dl>';
    }

    private function renderReviewsPlaceholder(): string
    {
        $path = dirname(__DIR__, 3) . '/system/product-tab-reviews-html.php';
        if (!is_file($path)) {
            return '';
        }
        require_once $path;

        return besoiu_product_reviews_html(true);
    }

    public static function fieldIconClass(string $fieldId): string
    {
        return match ($fieldId) {
            'denumire' => 'fa-solid fa-box',
            'brand_piesa' => 'fa-solid fa-industry',
            'cod' => 'fa-solid fa-barcode',
            'categorie' => 'fa-solid fa-folder-open',
            'subcategorie' => 'fa-solid fa-tags',
            'pozitie' => 'fa-solid fa-location-crosshairs',
            'vehicul_manual' => 'fa-solid fa-car',
            'compat_text' => 'fa-solid fa-car-side',
            'oem_codes' => 'fa-solid fa-hashtag',
            'livrare' => 'fa-solid fa-truck-fast',
            'garantie' => 'fa-solid fa-shield-halved',
            'retur' => 'fa-solid fa-rotate-left',
            'stare' => 'fa-solid fa-circle-check',
            default => 'fa-solid fa-circle-info',
        };
    }

    private function firstDescriptionLine(string $html): string
    {
        if ($html === '') {
            return '';
        }

        if (preg_match('/<dd[^>]*>(.*?)<\/dd>/is', $html, $m)) {
            return trim(strip_tags($m[1]));
        }

        return trim(strip_tags($html));
    }

    private function extractFuel(string $motor): string
    {
        $motor = trim($motor);
        if ($motor === '') {
            return '';
        }
        if (preg_match('/\b(TDI|TFSI|TSI|HDI|CDI|DCI|Hybrid|Electric|Benzin[aă]?|Diesel)\b/iu', $motor, $m)) {
            return $m[1];
        }

        return '';
    }

    /** @param array<string, mixed> $entry */
    private function formatMotorCell(array $entry): string
    {
        $motor = trim((string) ($entry['CAR_TYP'] ?? ''));
        $kw = trim((string) ($entry['CAR_KW'] ?? ''));
        if ($kw !== '' && $motor !== '' && !preg_match('/\bKW\b/i', $motor)) {
            return $motor . ' ' . $kw . ' KW';
        }
        if ($motor !== '') {
            return $motor;
        }
        if ($kw !== '') {
            return $kw . ' KW';
        }

        return '';
    }

    private function formatYearRange(string $from, string $to): string
    {
        $from = $this->normalizeTecdocYear($from);
        $to = $this->normalizeTecdocYear($to);
        if ($from !== '' && $to !== '' && $from !== $to) {
            return $from . '–' . $to;
        }
        if ($from !== '') {
            return $from;
        }

        return $to;
    }

    /** TecDoc AAAALL (ex. 200008) → an afișat 2000. */
    private function normalizeTecdocYear(string $value): string
    {
        $value = trim($value);
        if ($value === '') {
            return '';
        }
        if (preg_match('/^(prezent|present)$/iu', $value)) {
            return 'Prezent';
        }
        if (preg_match('/^(\d{4})\d{2}$/', $value, $m)) {
            return $m[1];
        }
        if (preg_match('/^(\d{4})$/', $value, $m)) {
            return $m[1];
        }

        return $value;
    }

    private function h(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
    }
}

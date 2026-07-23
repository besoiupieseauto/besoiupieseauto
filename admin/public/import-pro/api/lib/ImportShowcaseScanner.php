<?php
declare(strict_types=1);

require_once __DIR__ . '/ImportCardStaging.php';
require_once __DIR__ . '/ImportShowcaseTypeMatcher.php';
require_once __DIR__ . '/ImportShowcaseCsvReader.php';
require_once __DIR__ . '/ImportTecdocMysqlEnrichment.php';

/**
 * Scan rapid produse-vitrină: filtrează CSV după cuvinte cheie + reguli semantice / Ollama.
 */
final class ImportShowcaseScanner
{
    private const OLLAMA_BATCH_SIZE = 12;
    /** Limită rânduri CSV — evită scanări de 500k+ când nu există potriviri. */
    public const DEFAULT_MAX_ROWS = 150000;
    /** Oprește după N potriviri keyword dacă avem destule carduri TecDoc sau prea multe miss-uri. */
    private const KEYWORD_HIT_SOFT_CAP = 350;
    private const TECDOC_MISS_SOFT_CAP = 280;

    /**
     * @param list<string> $types
     * @return array{0: list<array<string, mixed>>, 1: array<string, mixed>}
     */
    public static function scanFile(
        string $path,
        string $filename,
        array $types,
        int $minScore,
        int $targetCount,
        int $maxRowsScan = self::DEFAULT_MAX_ROWS,
        bool $useOllama = true
    ): array {
        import_require_fetch_product_lib('SupplierPriceListBuilder.php');

        $opened = ImportShowcaseCsvReader::open($path);
        if ($opened === null) {
            return [[], [
                'status' => 'error',
                'message' => 'Nu pot deschide fișierul CSV',
                'format' => 'necunoscut',
                'scan_mode' => 'none',
            ]];
        }

        /** @var resource $headerHandle */
        $headerHandle = $opened['handle'];
        $delimiter = (string) $opened['delimiter'];
        $header = ImportShowcaseCsvReader::readHeader($headerHandle, $delimiter);
        fclose($headerHandle);

        if ($header === false) {
            return [[], [
                'status' => 'error',
                'message' => 'Nu pot citi header CSV',
                'format' => 'necunoscut',
                'scan_mode' => 'none',
            ]];
        }

        if (SupplierPriceListBuilder::isBaseCsvHeader($header)) {
            return self::scanBaseCsv($path, $types, $minScore, $targetCount, $maxRowsScan, $useOllama, $delimiter);
        }

        $hasHeader = SupplierPriceListBuilder::looksLikeHeader($header);
        /** @var list<list<string|null>> $pendingRows */
        $pendingRows = [];
        if (!$hasHeader) {
            $pendingRows[] = $header;
            $header = [];
        }

        $supplierType = SupplierPriceListBuilder::detectSupplierType(
            $hasHeader ? $header : ($pendingRows[0] ?? []),
            $filename
        );
        if ($supplierType !== null) {
            if (!self::isShowcasePriceListHeader($hasHeader ? $header : [], $filename)) {
                return [[], [
                    'status' => 'skipped',
                    'message' => 'Fișier fără listă preț (cross-reference sau fără coloane denumire+preț) — exclus scan vitrină',
                    'format' => 'csv_crossref',
                    'scan_mode' => 'none',
                    'supplier_type' => $supplierType,
                    'csv_delimiter' => $delimiter,
                ]];
            }

            return self::scanSupplierCsv(
                $path,
                $header,
                $supplierType,
                $types,
                $minScore,
                $targetCount,
                $maxRowsScan,
                $useOllama,
                $pendingRows,
                $hasHeader,
                $delimiter
            );
        }

        return [[], [
            'status' => 'skipped',
            'message' => 'Format CSV necunoscut — header neacceptat',
            'format' => 'necunoscut',
            'scan_mode' => 'none',
        ]];
    }

    /** @param list<string|null> $header */
    private static function isShowcasePriceListHeader(array $header, string $filename): bool
    {
        $fn = mb_strtolower($filename);
        if (preg_match('/\b(qwp|crossref|cross-ref|referinta|reference only)\b/u', $fn)) {
            return false;
        }

        $joined = mb_strtolower(implode(' ', array_map(static fn ($c): string => trim((string) $c), $header)));
        if ($joined === '') {
            return true;
        }

        $hasName = (bool) preg_match(
            '/\b(denumire|art.?name|artname|name|descriere|produs|denumire articol|art_name)\b/u',
            $joined
        );
        $hasPrice = (bool) preg_match(
            '/\b(pret|preț|price|pr\.|net|unitar|achizitie|achiziție)\b/u',
            $joined
        );

        if (!$hasName || !$hasPrice) {
            return false;
        }

        return !preg_match('/\b(artnr|refnr|referencebrand)\b/u', $joined)
            || $hasName;
    }

    /**
     * @param list<string|null> $header
     * @param list<list<string|null>> $pendingRows
     * @param list<string> $types
     * @return array{0: list<array<string, mixed>>, 1: array<string, mixed>}
     */
    private static function scanSupplierCsv(
        string $path,
        array $header,
        string $supplierType,
        array $types,
        int $minScore,
        int $targetCount,
        int $maxRowsScan,
        bool $useOllama,
        array $pendingRows = [],
        bool $hasHeader = true,
        string $delimiter = ';'
    ): array {
        $opened = ImportShowcaseCsvReader::open($path);
        if ($opened === null) {
            return [[], self::errorReport('csv_furnizor', 'Nu pot deschide fișierul CSV')];
        }

        /** @var resource $handle */
        $handle = $opened['handle'];
        if ($delimiter === ';' && ($opened['delimiter'] ?? '') !== '') {
            $delimiter = (string) $opened['delimiter'];
        }

        if ($hasHeader) {
            ImportShowcaseCsvReader::readHeader($handle, $delimiter);
        }

        $ollamaAvailable = import_ollama_available();
        $useOllamaPost = $useOllama && $ollamaAvailable;
        $collectTarget = $useOllamaPost
            ? min(60, max($targetCount * 3, $targetCount))
            : $targetCount;
        $report = [
            'status' => 'done',
            'format' => 'csv_furnizor',
            'scan_mode' => $useOllamaPost ? 'tecdoc_mysql_rules_ollama_post' : 'tecdoc_mysql_semantic_rules',
            'supplier_type' => $supplierType,
            'has_header' => $hasHeader,
            'csv_delimiter' => $delimiter,
            'rows_scanned' => 0,
            'rows_keyword_hit' => 0,
            'rows_tecdoc_miss' => 0,
            'cards_built' => 0,
            'cards_matched' => 0,
            'min_score' => $minScore,
            'types' => $types,
            'ollama_requested' => $useOllama,
            'ollama_available' => $ollamaAvailable,
            'ollama_used' => false,
            'ollama_batches' => 0,
            'ollama_approved' => 0,
            'ollama_rejected' => 0,
            'max_rows_scan' => $maxRowsScan,
            'collect_target' => $collectTarget,
        ];

        $cards = [];
        $stats = ['imagesFromAutopartner' => 0, 'withoutImage' => 0];
        $pending = $pendingRows;
        $sourceFile = strtolower($supplierType) . '/' . basename($path);

        while (true) {
            if ($pending !== []) {
                $row = array_shift($pending);
            } else {
                $row = ImportShowcaseCsvReader::readRow($handle, $delimiter);
            }
            if ($row === false) {
                break;
            }

            if (count($cards) >= $collectTarget) {
                break;
            }

            $report['rows_scanned']++;
            if ($report['rows_scanned'] > $maxRowsScan) {
                break;
            }

            $parsed = self::parseSupplierRow($row, $header, $supplierType, $hasHeader);
            if ($parsed === null) {
                continue;
            }

            $name = trim((string) ($parsed['name'] ?? ''));
            if ($name === '') {
                continue;
            }

            $probe = ['title' => $name, 'name' => $name];
            $typeMatch = ImportShowcaseTypeMatcher::matchKeyword($probe, $types, $minScore, true);
            if ($typeMatch === null) {
                continue;
            }

            $report['rows_keyword_hit']++;

            $card = ImportTecdocMysqlEnrichment::cardFromSupplierRow($parsed, $supplierType, $sourceFile);
            if ($card === null) {
                ++$report['rows_tecdoc_miss'];
                if (self::shouldUseLightweightShowcaseCard($name, $typeMatch, $types, $minScore)) {
                    $card = self::buildLightweightSupplierCard($parsed, $supplierType, $stats);
                    if ($card !== null) {
                        $card['sourceFile'] = $sourceFile;
                        $card['sourceSupplier'] = strtolower($supplierType);
                        $card['cardBuild'] = 'supplier_showcase_light';
                        $report['cards_lightweight'] = ((int) ($report['cards_lightweight'] ?? 0)) + 1;
                    }
                }
                if ($card === null) {
                    continue;
                }
            }

            $card = self::finalizeShowcaseCard($card, $typeMatch, $stats, true);
            if ($card === null) {
                continue;
            }

            $report['cards_built']++;
            $cards[] = $card;
            $report['cards_matched']++;

            if (self::shouldStopSupplierScan($report, count($cards), $targetCount, $collectTarget)) {
                $report['scan_budget_exhausted'] = true;
                break;
            }
        }

        fclose($handle);

        if ($useOllama && !$ollamaAvailable) {
            $report['message_warning'] = 'Ollama indisponibil — rezultate doar pe reguli locale (pornește ollama serve)';
        }

        if ($useOllamaPost && $cards !== []) {
            $cards = self::applyOllamaPostFilter($cards, $types, $minScore, $targetCount, $report);
            $report['ollama_used'] = true;
            // Re-calculează stats imagine după filtrare Ollama
            $stats = ['imagesFromAutopartner' => 0, 'withoutImage' => 0];
            foreach ($cards as $c) {
                if (!empty($c['hasImage'])) {
                    $stats['imagesFromAutopartner']++;
                } else {
                    $stats['withoutImage']++;
                }
            }
        } elseif ($useOllamaPost) {
            $report['ollama_used'] = true;
        }

        $report['cards_added'] = count($cards);
        $report['cards_with_image'] = $stats['imagesFromAutopartner'];
        $report['cards_without_image'] = $stats['withoutImage'];
        $report['message'] = self::buildResultMessage($cards, $report, $stats, $minScore, (bool) ($report['ollama_used'] ?? false));
        if (!empty($report['scan_budget_exhausted'])) {
            $report['message'] .= ' · scan oprit anticipat (limită candidați keyword/TecDoc)';
        }
        if (!empty($report['message_warning'])) {
            $report['message'] .= ' · ' . $report['message_warning'];
        }

        return [$cards, $report];
    }

    /**
     * Filtru Ollama după scanare: titlu + descriere + specs + info imagine.
     * Păstrează DOAR produsele aprobate de Ollama (fără fallback pe false-positive).
     *
     * @param list<array<string, mixed>> $cards
     * @param list<string> $types
     * @param array<string, mixed> $report
     * @return list<array<string, mixed>>
     */
    private static function applyOllamaPostFilter(
        array $cards,
        array $types,
        int $minScore,
        int $targetCount,
        array &$report
    ): array {
        $items = [];
        foreach ($cards as $card) {
            $items[] = [
                'name' => (string) ($card['name'] ?? ''),
                'title' => (string) ($card['title'] ?? $card['name'] ?? ''),
                'description' => (string) ($card['description'] ?? ''),
                'brand' => (string) ($card['brand'] ?? ''),
                'sku' => (string) ($card['sku'] ?? ''),
                'parameters' => is_array($card['parameters'] ?? null) ? $card['parameters'] : [],
                'has_image' => !empty($card['hasImage']),
                'image_url' => (string) ($card['imageDisplayUrl'] ?? $card['imageUrl'] ?? $card['scrapedImageUrl'] ?? ''),
                'type_match' => [
                    'type' => (string) ($card['showcaseType'] ?? ''),
                    'score' => (int) ($card['showcaseScore'] ?? 0),
                ],
                'card' => $card,
            ];
        }

        $ollamaMeta = [];
        $approved = ImportShowcaseTypeMatcher::ollamaFilterBatch($items, $types, $minScore, $ollamaMeta);
        $report['ollama_batches'] = (int) ($ollamaMeta['batches'] ?? (int) ceil(max(1, count($items)) / self::OLLAMA_BATCH_SIZE));
        $report['ollama_approved'] = count($approved);
        $report['ollama_rejected'] = max(0, count($items) - count($approved));
        $report['ollama_kept_rules'] = false;
        if (!empty($ollamaMeta['batch_errors'])) {
            $report['ollama_batch_errors'] = $ollamaMeta['batch_errors'];
            $report['ollama_chunks_failed'] = (int) ($ollamaMeta['chunks_failed'] ?? 0);
            $errCount = count($ollamaMeta['batch_errors']);
            $report['message_warning'] = ($report['message_warning'] ?? '')
                ? ($report['message_warning'] . ' · Ollama: ' . $errCount . ' lot(uri) eșuate')
                : ('Ollama: ' . $errCount . ' lot(uri) eșuate — ' . ($ollamaMeta['batch_errors'][0]['message'] ?? 'eroare'));
        }

        if ($approved === [] && $items !== []) {
            $approved = self::fallbackHighConfidenceRuleItems($items, $types, $minScore);
            if ($approved !== []) {
                $report['ollama_kept_rules'] = true;
                $report['ollama_approved'] = count($approved);
                $report['ollama_rejected'] = max(0, count($items) - count($approved));
                $report['message_warning'] = ($report['message_warning'] ?? '')
                    ? ($report['message_warning'] . ' · Ollama fără rezultate — păstrate potriviri clare pe reguli')
                    : 'Ollama fără rezultate — păstrate potriviri clare pe reguli';
            }
        }

        if ($approved === []) {
            return [];
        }

        $out = [];
        foreach ($approved as $item) {
            $card = $item['card'] ?? null;
            $typeMatch = $item['type_match'] ?? null;
            if (!is_array($card) || !is_array($typeMatch)) {
                continue;
            }
            $card['showcaseType'] = $typeMatch['type'];
            $card['showcaseScore'] = $typeMatch['score'];
            $card['showcaseMatchMethod'] = $typeMatch['method'] ?? 'ollama';
            if (!empty($typeMatch['reason'])) {
                $card['showcaseOllamaReason'] = $typeMatch['reason'];
            }
            $out[] = $card;
            if (count($out) >= $targetCount) {
                break;
            }
        }

        return $out;
    }

    /**
     * @param array{type:string,score:int,method?:string} $typeMatch
     * @param list<string> $types
     */
    private static function shouldUseLightweightShowcaseCard(
        string $name,
        array $typeMatch,
        array $types,
        int $minScore
    ): bool {
        $type = mb_strtolower(trim((string) ($typeMatch['type'] ?? '')));
        if ($type === '' || !in_array($type, $types, true)) {
            return false;
        }

        return ImportShowcaseTypeMatcher::isHighConfidenceShowcaseName($name, $type, $minScore);
    }

    /**
     * @param list<array<string, mixed>> $items
     * @param list<string> $types
     * @return list<array<string, mixed>>
     */
    private static function fallbackHighConfidenceRuleItems(array $items, array $types, int $minScore): array
    {
        $ruleMin = max($minScore, 84);
        $kept = [];

        foreach ($items as $item) {
            $card = is_array($item['card'] ?? null) ? $item['card'] : [];
            $name = trim((string) ($item['name'] ?? $card['name'] ?? $item['title'] ?? $card['title'] ?? ''));
            $type = mb_strtolower(trim((string) ($item['type_match']['type'] ?? $card['showcaseType'] ?? '')));
            if ($name === '' || $type === '' || !in_array($type, $types, true)) {
                continue;
            }

            if (!ImportShowcaseTypeMatcher::isHighConfidenceShowcaseName($name, $type, $ruleMin)) {
                continue;
            }

            $rulesCheck = ImportShowcaseTypeMatcher::matchKeyword(
                [
                    'title' => (string) ($item['title'] ?? $card['title'] ?? $name),
                    'name' => $name,
                    'description' => (string) ($item['description'] ?? $card['description'] ?? ''),
                ],
                [$type],
                $ruleMin,
                true
            );
            if ($rulesCheck === null) {
                continue;
            }

            $item['type_match'] = [
                'type' => $type,
                'score' => max((int) ($item['type_match']['score'] ?? 0), (int) ($rulesCheck['score'] ?? 0)),
                'method' => 'keyword_semantic',
                'reason' => 'Potrivire clară pe reguli (fallback fără Ollama)',
            ];
            $kept[] = $item;
        }

        return $kept;
    }

    /**
     * @param list<string|null> $row
     * @param list<string|null> $header
     * @return array{code:string,brand:string,name:string,priceNet:?float}|null
     */
    private static function parseSupplierRow(array $row, array $header, string $supplierType, bool $hasHeader): ?array
    {
        if (!$hasHeader && $supplierType === 'AUTOPARTNER') {
            return SupplierPriceListBuilder::extractPositionalRow($row, $supplierType);
        }

        $assoc = self::rowToAssoc($header, $row);

        return SupplierPriceListBuilder::extractRowFields($assoc, $supplierType);
    }

    /**
     * @param array<string, mixed> $card
     * @param array{type:string,score:int,method?:string} $typeMatch
     * @param array<string, int> $stats
     * @return array<string, mixed>|null
     */
    private static function finalizeShowcaseCard(array $card, array $typeMatch, array &$stats, bool $attachImages = true): ?array
    {
        if ($attachImages) {
            try {
                $card = import_attach_local_image_to_card($card);
            } catch (Throwable) {
                // Cardul rămâne fără imagine — nu oprește scanarea pe un singur SKU.
            }
            if (!empty($card['hasImage'])) {
                $stats['imagesFromAutopartner']++;
            } else {
                $stats['withoutImage']++;
            }
        }

        $card['showcaseType'] = $typeMatch['type'];
        $card['showcaseScore'] = $typeMatch['score'];
        $card['showcaseMatchMethod'] = $typeMatch['method'] ?? 'keyword_semantic';

        return $card;
    }

    /**
     * @param list<string> $types
     * @return array{0: list<array<string, mixed>>, 1: array<string, mixed>}
     */
    private static function scanBaseCsv(
        string $path,
        array $types,
        int $minScore,
        int $targetCount,
        int $maxRowsScan,
        bool $useOllama,
        string $delimiter = ';'
    ): array {
        require_once import_motor_product_matcher_path();

        $ollamaAvailable = import_ollama_available();
        $useOllamaPost = $useOllama && $ollamaAvailable;
        $collectTarget = $useOllamaPost
            ? min(60, max($targetCount * 3, $targetCount))
            : $targetCount;
        $report = [
            'status' => 'done',
            'format' => 'csv_baza',
            'scan_mode' => $useOllamaPost ? 'tecdoc_mysql_matcher_ollama_post' : 'tecdoc_mysql_matcher_rules',
            'csv_delimiter' => $delimiter,
            'rows_scanned' => 0,
            'rows_keyword_hit' => 0,
            'cards_built' => 0,
            'cards_matched' => 0,
            'min_score' => $minScore,
            'types' => $types,
            'ollama_requested' => $useOllama,
            'ollama_available' => $ollamaAvailable,
            'ollama_used' => false,
            'ollama_batches' => 0,
            'ollama_approved' => 0,
            'ollama_rejected' => 0,
            'collect_target' => $collectTarget,
        ];

        $opened = ImportShowcaseCsvReader::open($path);
        if ($opened === null) {
            return [[], self::errorReport('csv_baza', 'Nu pot deschide fișierul CSV')];
        }
        /** @var resource $handle */
        $handle = $opened['handle'];
        if ($delimiter === ';' && ($opened['delimiter'] ?? '') !== '') {
            $delimiter = (string) $opened['delimiter'];
        }
        $header = ImportShowcaseCsvReader::readHeader($handle, $delimiter);
        if ($header === false) {
            fclose($handle);
            return [[], self::errorReport('csv_baza', 'Header CSV invalid')];
        }

        $keywordHits = 0;
        while (($row = ImportShowcaseCsvReader::readRow($handle, $delimiter)) !== false) {
            $report['rows_scanned']++;
            if ($report['rows_scanned'] > $maxRowsScan) {
                break;
            }
            $assoc = self::rowToAssoc($header, $row);
            $name = trim((string) ($assoc['ART_NAME'] ?? $assoc['ARTNAME'] ?? $assoc['ArtName'] ?? ''));
            if ($name === '') {
                continue;
            }
            if (ImportShowcaseTypeMatcher::matchKeyword(['title' => $name, 'name' => $name], $types, $minScore, true) !== null) {
                $keywordHits++;
            }
            if ($keywordHits >= $targetCount * 3) {
                break;
            }
        }
        fclose($handle);

        $report['rows_keyword_hit'] = $keywordHits;
        $buildLimit = min(600, max($targetCount * 4, $keywordHits > 0 ? $keywordHits * 2 : 80, 40));

        $matcher = new ProductMatcher();
        [$builtCards] = $matcher->buildCardsFromUploadedCsv($path, $buildLimit, true);
        $report['cards_built'] = count($builtCards);

        $cards = [];
        foreach ($builtCards as $card) {
            if (!is_array($card)) {
                continue;
            }
            $enriched = ImportTecdocMysqlEnrichment::enrichCardFromMysql($card);
            if ($enriched === null || !ImportTecdocMysqlEnrichment::cardHasTecdocBody($enriched)) {
                continue;
            }
            $card = $enriched;
            $match = ImportShowcaseTypeMatcher::matchKeyword($card, $types, $minScore, true);
            if ($match === null) {
                continue;
            }
            $card['showcaseType'] = $match['type'];
            $card['showcaseScore'] = $match['score'];
            $card['showcaseMatchMethod'] = $match['method'] ?? 'keyword_semantic';
            $cards[] = $card;
            $report['cards_matched']++;
            if (count($cards) >= $collectTarget) {
                break;
            }
        }

        if ($useOllama && !$ollamaAvailable) {
            $report['message_warning'] = 'Ollama indisponibil — rezultate doar pe reguli locale (pornește ollama serve)';
        }

        if ($useOllamaPost && $cards !== []) {
            $cards = self::applyOllamaPostFilter($cards, $types, $minScore, $targetCount, $report);
            $report['ollama_used'] = true;
        } elseif ($useOllamaPost) {
            $report['ollama_used'] = true;
        } else {
            $cards = array_slice($cards, 0, $targetCount);
        }

        $report['cards_added'] = count($cards);
        $withImg = count(array_filter($cards, static fn (array $c): bool => !empty($c['hasImage'])));
        $report['cards_with_image'] = $withImg;
        $report['cards_without_image'] = count($cards) - $withImg;
        $stats = ['imagesFromAutopartner' => $withImg, 'withoutImage' => count($cards) - $withImg];
        $report['message'] = self::buildResultMessage($cards, $report, $stats, $minScore, (bool) ($report['ollama_used'] ?? false), 'CSV bază');
        if (!empty($report['message_warning'])) {
            $report['message'] .= ' · ' . $report['message_warning'];
        }

        return [$cards, $report];
    }

    /**
     * @param list<array<string, mixed>> $cards
     * @param array<string, mixed> $report
     * @param array<string, int> $stats
     */
    private static function buildResultMessage(
        array $cards,
        array $report,
        array $stats,
        int $minScore,
        bool $ollamaActive,
        string $context = ''
    ): string {
        if ($cards === []) {
            $keywordHits = (int) ($report['rows_keyword_hit'] ?? 0);
            $ollamaRejected = (int) ($report['ollama_rejected'] ?? 0);
            if ($ollamaActive && $ollamaRejected > 0) {
                $hint = ' — Ollama a respins toți cei ' . $ollamaRejected . ' candidați (titlu/descriere/imagine)';
                $suffix = ' (filtrare Ollama)';
            } else {
                $suffix = $ollamaActive ? ' (reguli + Ollama)' : ' (reguli semantice)';
                $hint = $keywordHits > 0
                    ? (' — ' . $keywordHits . ' candidat(e) keyword, fără date în TecDoc MySQL sau respinse')
                    : ' — niciun rând cu cuvânt cheie după reguli';
            }

            return '0 potriviri vitrină din ' . ($report['rows_scanned'] ?? 0) . ' rânduri'
                . ($context !== '' ? ' — ' . $context : '')
                . $hint
                . ($context !== '' || ($ollamaActive && $ollamaRejected > 0) ? '' : ' (scor < ' . $minScore . '% sau respins semantic)')
                . $suffix;
        }

        $ollamaNote = '';
        if ($ollamaActive && ((int) ($report['ollama_rejected'] ?? 0)) > 0) {
            $ollamaNote = ', Ollama a respins ' . (int) $report['ollama_rejected'] . ' false-positive';
        }
        if (!empty($report['ollama_kept_rules'])) {
            $ollamaNote .= ', fallback reguli clare';
        }
        $lightNote = '';
        if (((int) ($report['cards_lightweight'] ?? 0)) > 0) {
            $lightNote = ', ' . (int) $report['cards_lightweight'] . ' din CSV fără TecDoc';
        }

        return 'Găsite ' . count($cards) . ' produse vitrină din ' . ($report['rows_scanned'] ?? 0)
            . ' rânduri scanate'
            . ($context !== '' ? ' (' . $context . ')' : '')
            . ' (' . ($stats['imagesFromAutopartner'] ?? 0) . ' cu imagine, '
            . ($stats['withoutImage'] ?? 0) . ' fără'
            . ($ollamaActive ? ', Ollama: titlu+descriere+imagine' : ', reguli semantice')
            . $ollamaNote . $lightNote . ')';
    }

    /**
     * @param array{code:string,brand:string,name:string,priceNet:?float} $parsed
     * @param array<string, int> $stats
     * @return array<string, mixed>|null
     */
    private static function buildLightweightSupplierCard(array $parsed, string $supplierType, array &$stats): ?array
    {
        $sanitize = static function (string $value): string {
            return function_exists('import_utf8_sanitize_string')
                ? import_utf8_sanitize_string($value)
                : $value;
        };

        $code = $sanitize(trim((string) ($parsed['code'] ?? '')));
        $brand = $sanitize(trim((string) ($parsed['brand'] ?? '')));
        $name = $sanitize(trim((string) ($parsed['name'] ?? '')));
        $priceNet = $parsed['priceNet'] ?? null;

        if ($code === '' || $priceNet === null || (float) $priceNet <= 0) {
            return null;
        }

        $nameLower = mb_strtolower($name, 'UTF-8');
        if ($nameLower !== '' && (
            str_contains($nameLower, 'valoare piesa veche')
            || str_contains($nameLower, 'valoare de garantie')
            || str_contains($nameLower, 'core_value')
        )) {
            return null;
        }

        $title = self::buildTitle($name, $brand, $code);
        $priceFinal = self::computeFinalPrice((float) $priceNet, $supplierType);
        $pricePurchaseVat = (int) ceil((float) $priceNet * 1.21);

        return [
            'sku' => $code,
            'brand' => $brand,
            'ttcArtId' => '',
            'autopartnerCode' => '',
            'imageSource' => '',
            'hasImage' => false,
            'scrapeQuery' => trim($name . ' ' . $brand . ' ' . $code),
            'ean' => '',
            'name' => $name,
            'title' => $title,
            'description' => '',
            'parameters' => [],
            'termsOfUse' => '',
            'oemCodes' => '',
            'priceNet' => round((float) $priceNet, 2),
            'priceFinal' => $priceFinal,
            'pricePurchaseVat' => $pricePurchaseVat,
            'priceRecommended' => (int) ceil($priceFinal * 1.35),
            'supplier' => $supplierType,
            'compatCount' => 0,
            'imageUrl' => '',
            'scrapedImageUrl' => '',
            'scrapedImageSource' => '',
            'scrapedImageScore' => null,
            'sourceFileType' => 'supplier_showcase_light',
        ];
    }

    /** @param list<string|null> $header @param list<string|null> $row @return array<string, string> */
    private static function rowToAssoc(array $header, array $row): array
    {
        $assoc = [];
        foreach ($header as $i => $col) {
            $key = function_exists('import_utf8_sanitize_string')
                ? import_utf8_sanitize_string(trim((string) $col))
                : trim((string) $col);
            $value = trim((string) ($row[$i] ?? ''));
            $assoc[$key] = function_exists('import_utf8_sanitize_string')
                ? import_utf8_sanitize_string($value)
                : $value;
        }

        return $assoc;
    }

    private static function buildTitle(string $name, string $brand, string $code): string
    {
        $name = trim(preg_replace('/\s+/u', ' ', $name) ?? '');
        $brand = trim($brand);
        $code = trim($code);

        $title = $name;
        if ($brand !== '' && !str_contains(mb_strtoupper($name, 'UTF-8'), $brand)) {
            $title .= ' ' . $brand;
        }
        if ($code !== '' && !str_contains(mb_strtoupper($name, 'UTF-8'), $code)) {
            $title .= ' ' . $code;
        }

        return trim(preg_replace('/\s+/u', ' ', $title) ?? '');
    }

    private static function computeFinalPrice(float $priceNet, string $supplierType): float
    {
        return match ($supplierType) {
            'INTERCARS' => round($priceNet, 2),
            default => round($priceNet * 1.21, 2),
        };
    }

    /** @return array<string, mixed> */
    private static function errorReport(string $format, string $message): array
    {
        return [
            'status' => 'error',
            'format' => $format,
            'scan_mode' => 'none',
            'message' => $message,
        ];
    }

    /**
     * Oprire anticipată: nu parcurge tot CSV-ul când candidații keyword / miss TecDoc sunt deja suficienți.
     *
     * @param array<string, mixed> $report
     */
    private static function shouldStopSupplierScan(
        array $report,
        int $cardCount,
        int $targetCount,
        int $collectTarget
    ): bool {
        if ($cardCount >= $collectTarget) {
            return true;
        }

        if ($cardCount < max(3, (int) ceil($targetCount * 0.4))) {
            return false;
        }

        $keywordHits = (int) ($report['rows_keyword_hit'] ?? 0);
        if ($keywordHits < max(120, $targetCount * 8)) {
            return false;
        }

        $tecdocMiss = (int) ($report['rows_tecdoc_miss'] ?? 0);
        $keywordCap = max(self::KEYWORD_HIT_SOFT_CAP, $targetCount * 12);
        $missCap = max(self::TECDOC_MISS_SOFT_CAP, $targetCount * 10);

        if ($cardCount >= $targetCount) {
            return true;
        }

        return $keywordHits >= $keywordCap && $tecdocMiss >= $missCap;
    }
}

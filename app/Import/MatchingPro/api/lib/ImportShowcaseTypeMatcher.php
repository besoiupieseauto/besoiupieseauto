<?php
declare(strict_types=1);

require_once __DIR__ . '/ImportShowcaseEpiesaRag.php';

/**
 * Matching semantic tipuri vitrină — reguli + Ollama + RAG epiesa.ro.
 */
final class ImportShowcaseTypeMatcher
{
    /** @var array<string, list<string>> */
    private const EXCLUDE_CONTAINS = [
        'ulei' => [
            'filtru ulei',
            'filtru de ulei',
            'filtruulei',
            'baie de ulei',
            'baia de ulei',
            'baia ulei',
            'baie ulei',
            'pompa ulei',
            'pompă ulei',
            'radiator ulei',
            'racitor de ulei',
            'racitor ulei',
            'răcitor de ulei',
            'răcitor ulei',
            'amortizor',
            'garnitura baie ulei',
            'garnituri baie ulei',
            'garnitura conducta ulei',
            'garnitura filtru ulei',
            'senzor ulei',
            'senzor presiune ulei',
            'senzor temperatura ulei',
            'conducta de ulei',
            'conducta ulei',
            'conductă ulei',
            'furtun ulei',
            'buson ulei',
            'buson de golire',
            'golire a uleiului',
            'surub ulei',
            'șurub ulei',
            'cheie filtru',
            'separator ulei',
            'etansare ulei',
            'etanșare ulei',
            'capac ulei',
            'capcană pentru ulei',
            'capcana pentru ulei',
            'carcasa filtru ulei',
            'locas filtru ulei',
            'protectie baie ulei',
            'protectie baie de ulei',
            'recipient ulei',
            'recuperator ulei',
            'palnie ulei',
            'cheie pentru filtru',
            'set lant, antrenare pompa ulei',
            'pompa de ulei',
            'brat/bieleta',
            'bieleta suspensie',
            'bara stabilizatoare',
            'set reparatie, bara stabilizatoare',
            'suspensie, stabilizator',
            'set piese',
            'set de piese',
            'schimb de ulei',
            'schimb ulei',
            'kit schimb ulei',
            'kit ulei',
            'nivel ulei',
            'sonda ulei',
            'sonda de ulei',
            'sonda nivel ulei',
            'indicator nivel ulei',
            'tija nivel ulei',
            'sticla nivel ulei',
            'pluta ulei',
            'dop ulei',
            'surub golire ulei',
            'surub golire a uleiului',
            'cheie golire ulei',
            'canistra ulei uzat',
            'canistra colectare ulei',
            'presostat ulei',
            'intrerupator presiune ulei',
            'întrerupator presiune ulei',
            'modul presiune ulei',
            'filtru',
            'kit filtru',
            'pachet filtru',
        ],
        'baterie' => [
            'suport baterie',
            'acoperire baterie',
            'capac baterie',
            'comutator baterie',
            'comutator principal baterie',
            'senzor baterie',
            'conductor baterie',
            'conductor principal baterie',
            'capac cutie baterie',
            'clema baterie',
            'clemă baterie',
            'borna baterie',
            'bornă baterie',
            'cablu baterie',
            'cabluri baterie',
            'legatura baterie',
            'legătură baterie',
            'protectie baterie',
            'protecție baterie',
        ],
        'lichid' => [
            'garnitura conducta lichid',
            'garnitură conducta lichid',
            'garnitura flansa lichid',
            'garnitura flanșă lichid',
            'garnitura termostat',
            'senzor temperatura lichid',
            'senzor temperatura lichid racire',
            'senzor lichid',
            'sonda lichid',
            'sonda nivel lichid',
            'cuplaj conducta lichid',
            'flansa lichid',
            'flanșă lichid',
            'termostat lichid',
            'termostat lichid racire',
            'termostat',
            'termosta ',
            'vas expansiune lichid',
            'vas expansiune',
            'rezervor expansiune',
            'conducta lichid',
            'conductă lichid',
            'conducta de lichid',
            'furtun lichid',
            'furtun racire',
            'pompa lichid',
            'pompa lichid racire',
            'pompa apa',
            'pompa de apa',
            'pompă apă',
            'radiator lichid',
            'radiator racire',
            'electrovalv',
            'modul termostat',
            'carcasa termostat',
            'supapa lichid',
            'robinet lichid',
            'intrerupator control nivel lichid',
            'întrerupator control nivel lichid',
            'jet de lichid de spalare',
            'jet lichid spalare',
            'rezervor lichid de spalare',
            'aparat de schimbat lichid',
            'schimbator caldura',
            'schimbător căldură',
        ],
        'adeziv' => [
            'banda adeziva',
            'bandă adezivă',
            'benzi adezive',
            'set adeziv bara',
            'adeziv bara',
            'bara stabilizatoare',
            'set reparatie',
        ],
    ];

    /** @var array<string, list<string>> */
    private const POSITIVE_CONTAINS = [
        'ulei' => [
            'ulei motor',
            'ulei transmisie',
            'ulei servodirect',
            'ulei hidraulic',
            'ulei automata',
            'ulei automată',
            'ulei api',
            'ulei gm',
            'ulei dexos',
            'ulei long',
            'ulei top',
            'ulei 0w',
            'ulei 5w',
            'ulei 10w',
            'ulei 15w',
            'lubrifiant',
            'spray lubrifiant',
            '0w20',
            '0w30',
            '5w30',
            '5w40',
            '10w40',
            '10w60',
            '15w40',
            '75w90',
            '80w90',
            '85w140',
            'motor oil',
            'longlife',
            'ulei opel',
            'ulei total',
            'ulei liqui',
            'ulei vw',
            'ulei mazda',
            'ulei toyota',
            'ulei castrol',
            'ulei mobil',
            'ulei elf',
            'ulei cutie viteza',
            'ulei cutie viteze',
            'ulei atf',
            'ulei cvt',
            'ulei fd',
            'ulei original',
            'ulei long time',
            'ulei longlife',
            'handy-oil',
            'scoogear',
        ],
        'baterie' => [
            'baterie auto',
            'baterie litiu',
            'baterie lithium',
            'acumulator auto',
            'acumulator',
            'baterie varta',
            'baterie banner',
            'baterie exide',
            'baterie bosch',
            'baterie fd baterie',
        ],
        'lichid' => [
            'lichid frana',
            'lichid frână',
            'lichid de frana',
            'lichid de frână',
            'lichid parbriz',
            'lichid de spalare parbriz',
            'lichid spalare parbriz',
            'antigel',
            'coolant',
            'lichid curatare',
            'apa distilata',
            'apă distilată',
            'dot 3',
            'dot 4',
            'dot 5',
            'dot5',
            'dot4',
            'dot3',
            'drauliquid',
            'brake fluid',
            'antifreeze',
        ],
        'adeziv' => [
            'adeziv',
            'lipici',
            'set adeziv',
            'silicon',
            'chit',
        ],
    ];

    /** @var array<string, string> */
    private const TYPE_HINTS = [
        'ulei' => 'Categoria „Uleiuri și lubrifianți auto” (epiesa): DOAR consumabile — ulei motor, ulei cutie/transmisie, ulei hidraulic, spray lubrifiant, paste ulei (bidon/sticlă). NU: filtre ulei, pompe, băi ulei, radiatoare, garnituri, senzori, termostate, piese mecanice.',
        'baterie' => 'Categoria „Electrice auto — Baterii”: DOAR baterii/acumulatoare (auto 12V, buton CR2032/CR1632 etc.). NU: suporturi, borne, cabluri, capace, comutatoare, senzori.',
        'lichid' => 'Categoria „Lichide auto”: DOAR consumabile — antigel, lichid frână (DOT), lichid parbriz, apă distilată (bidon). NU: termostat, pompă apă, vas expansiune, radiator, furtun, garnitură, senzor — chiar dacă numele conține „lichid răcire”.',
        'adeziv' => 'Adezivi, lipici, silicon, chit. NU: benzi adezive decorative.',
        'bec' => 'Becuri auto (halogen, LED, xenon).',
        'lubrifiant' => 'Spray lubrifiant, WD-40, ungerere — NU ulei motor în bidon.',
    ];

    /**
     * @param array<string, mixed> $card
     * @param list<string> $types
     * @return array{type:string,score:int,method:string}|null
     */
    public static function matchKeyword(array $card, array $types, int $minScore = 72, bool $requirePositive = true): ?array
    {
        $haystack = self::buildHaystack($card);
        if ($haystack === '') {
            return null;
        }

        $best = null;
        foreach ($types as $type) {
            $needle = mb_strtolower(trim((string) $type));
            if ($needle === '' || !self::containsTypeKeyword($haystack, $needle)) {
                continue;
            }
            if (self::isExcluded($haystack, $needle)) {
                continue;
            }
            if ($requirePositive && !self::passesPositiveGate($haystack, $needle)) {
                continue;
            }

            $score = (int) min(100, 68 + mb_strlen($needle) * 4);
            if ($best === null || $score > $best['score']) {
                $best = ['type' => $needle, 'score' => $score, 'method' => 'keyword_semantic'];
            }
        }

        if ($best === null || $best['score'] < $minScore) {
            return null;
        }

        return $best;
    }

    /**
     * Filtru Ollama pe candidați (titlu + descriere + specs + info imagine).
     *
     * @param list<array<string, mixed>> $items
     * @param list<string> $types
     * @param array<string, mixed>|null $meta
     * @return list<array<string, mixed>>
     */
    public static function ollamaFilterBatch(array $items, array $types, int $minScore = 72, ?array &$meta = null): array
    {
        if ($meta !== null) {
            $meta = [
                'batches' => 0,
                'batch_errors' => [],
                'chunks_failed' => 0,
            ];
        }

        if ($items === [] || !import_ollama_available()) {
            return [];
        }

        $chunks = array_chunk($items, 12);
        $approved = [];

        foreach ($chunks as $batchIndex => $chunk) {
            if ($meta !== null) {
                ++$meta['batches'];
            }
            try {
                $decisions = self::ollamaClassifyChunk($chunk, $types);
            } catch (Throwable $e) {
                if ($meta !== null) {
                    ++$meta['chunks_failed'];
                    $meta['batch_errors'][] = [
                        'batch' => $batchIndex,
                        'message' => $e->getMessage(),
                    ];
                }
                continue;
            }

            foreach ($chunk as $pos => $item) {
                $decision = $decisions[$pos] ?? null;
                if (!is_array($decision)) {
                    continue;
                }
                if (empty($decision['match'])) {
                    continue;
                }
                $type = mb_strtolower(trim((string) ($decision['type'] ?? '')));
                $confidence = (int) ($decision['confidence'] ?? 0);
                if ($type === '' || !in_array($type, $types, true) || $confidence < $minScore) {
                    continue;
                }

                $haystack = self::itemHaystack($item);
                if ($haystack !== '' && self::isExcluded($haystack, $type)) {
                    continue;
                }

                $cardForRules = is_array($item['card'] ?? null) ? $item['card'] : [
                    'title' => (string) ($item['title'] ?? ''),
                    'name' => (string) ($item['name'] ?? ''),
                    'description' => (string) ($item['description'] ?? ''),
                ];
                $rulesCheck = self::matchKeyword($cardForRules, [$type], $minScore, true);
                if ($rulesCheck === null) {
                    continue;
                }

                $item['type_match'] = [
                    'type' => $type,
                    'score' => max($confidence, (int) ($rulesCheck['score'] ?? 0)),
                    'method' => 'ollama',
                    'reason' => trim((string) ($decision['reason'] ?? '')),
                ];
                $approved[] = $item;
            }
        }

        return $approved;
    }

    /**
     * @param list<array<string, mixed>> $chunk
     * @param list<string> $types
     * @return array<int, array<string, mixed>>
     */
    private static function ollamaClassifyChunk(array $chunk, array $types): array
    {
        $lines = [];
        foreach ($chunk as $i => $item) {
            $lines[] = self::formatProductForOllama($i, $item);
        }

        $rules = [];
        foreach ($types as $type) {
            $rules[] = '- ' . $type . ': ' . (self::TYPE_HINTS[$type] ?? 'Produs consumabil pentru categoria ' . $type);
        }

        $typesStr = self::typesList($types);
        $rulesBlock = self::implodeLines($rules);
        $linesBlock = self::implodeLines($lines);
        $lastIdx = self::lastIndex($chunk);
        $ragBlock = ImportShowcaseEpiesaRag::formatForPrompt(
            ImportShowcaseEpiesaRag::retrieveForBatch($chunk, $types, 18)
        );
        $ragSection = $ragBlock !== ''
            ? ($ragBlock . "\n\n")
            : "Referință: https://www.epiesa.ro/ — Produse auto universale (uleiuri, lichide, electrice/baterii, iluminat/becuri).\n\n";

        $prompt = <<<PROMPT
Ești expert produse auto universale / consumabile vitrină — catalog epiesa.ro.
Analizează FIECARE produs folosind TITLU + DESCRIERE + SPECIFICAȚII + INFO IMAGINE.
Tipuri cerute: {$typesStr}

{$ragSection}Reguli stricte (match=true DOAR pentru consumabile de raft, ca pe epiesa):
{$rulesBlock}

Produse de clasificat:
{$linesBlock}

Răspunde DOAR JSON:
{
  "results": [
    {"i": 0, "match": true, "type": "ulei", "confidence": 92, "reason": "ulei motor 5W30 bidon"},
    {"i": 1, "match": false, "type": null, "confidence": 95, "reason": "filtru ulei, nu ulei motor"}
  ]
}
Pentru fiecare index 0..{$lastIdx}:
- match=true DOAR dacă e consumabil de vitrină (ulei/lichid/baterie/adeziv etc. în ambalaj), NU piesă mecanică.
- Exemple respingere: filtru ulei, termostat lichid răcire, pompă apă, suport baterie, garnitură, senzor.
- Folosește descrierea și specificațiile ca dovadă; dacă imaginea lipsește, decide doar din text.
- Dacă e ambiguu → match=false.
PROMPT;

        $parsed = import_ollama_chat_json($prompt, min(120, import_ollama_timeout_sec()));
        $results = is_array($parsed['results'] ?? null) ? $parsed['results'] : [];
        $map = [];
        foreach ($results as $row) {
            if (!is_array($row)) {
                continue;
            }
            $idx = (int) ($row['i'] ?? -1);
            if ($idx >= 0) {
                $map[$idx] = $row;
            }
        }

        return $map;
    }

    /** @param array<string, mixed> $item */
    private static function formatProductForOllama(int $index, array $item): string
    {
        $card = is_array($item['card'] ?? null) ? $item['card'] : [];
        $name = trim((string) ($item['name'] ?? $card['name'] ?? ''));
        $title = trim((string) ($item['title'] ?? $card['title'] ?? $name));
        $brand = trim((string) ($item['brand'] ?? $card['brand'] ?? ''));
        $sku = trim((string) ($item['sku'] ?? $card['sku'] ?? ''));
        $description = self::plainText((string) ($item['description'] ?? $card['description'] ?? ''), 420);
        $specs = self::formatParameters(
            is_array($item['parameters'] ?? null) ? $item['parameters'] : (is_array($card['parameters'] ?? null) ? $card['parameters'] : [])
        );
        $hasImage = !empty($item['has_image']) || !empty($card['hasImage']);
        $imageUrl = trim((string) (
            $item['image_url']
            ?? $card['imageDisplayUrl']
            ?? $card['imageUrl']
            ?? $card['scrapedImageUrl']
            ?? ''
        ));
        $imageHint = $hasImage
            ? ('da' . ($imageUrl !== '' ? ' · ' . mb_substr(basename(parse_url($imageUrl, PHP_URL_PATH) ?: $imageUrl), 0, 80) : ''))
            : 'nu';

        $candidate = '';
        if (is_array($item['type_match'] ?? null)) {
            $candidate = (string) ($item['type_match']['type'] ?? '');
        }

        $parts = [
            $index . '.',
            'TITLU: ' . mb_substr($title !== '' ? $title : $name, 0, 180),
        ];
        if ($name !== '' && mb_strtolower($name) !== mb_strtolower($title)) {
            $parts[] = 'NUME CSV: ' . mb_substr($name, 0, 120);
        }
        if ($brand !== '' || $sku !== '') {
            $parts[] = 'BRAND/SKU: ' . trim($brand . ' / ' . $sku, ' /');
        }
        if ($description !== '') {
            $parts[] = 'DESCRIERE: ' . $description;
        }
        if ($specs !== '') {
            $parts[] = 'SPECS: ' . $specs;
        }
        $parts[] = 'IMAGINE: ' . $imageHint;
        if ($candidate !== '') {
            $parts[] = 'CANDIDAT: ' . $candidate;
        }

        return implode(' | ', $parts);
    }

    /** @param array<string, mixed> $item */
    private static function itemHaystack(array $item): string
    {
        $card = is_array($item['card'] ?? null) ? $item['card'] : [];

        return mb_strtolower(trim(implode(' ', array_filter([
            (string) ($item['title'] ?? $card['title'] ?? ''),
            (string) ($item['name'] ?? $card['name'] ?? ''),
            self::plainText((string) ($item['description'] ?? $card['description'] ?? ''), 300),
        ]))));
    }

    private static function plainText(string $htmlOrText, int $maxLen = 400): string
    {
        $text = html_entity_decode(strip_tags($htmlOrText), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = trim(preg_replace('/\s+/u', ' ', $text) ?? '');
        if ($text === '') {
            return '';
        }

        return mb_substr($text, 0, $maxLen);
    }

    /** @param list<mixed> $parameters */
    private static function formatParameters(array $parameters): string
    {
        $bits = [];
        foreach (array_slice($parameters, 0, 8) as $row) {
            if (!is_array($row)) {
                continue;
            }
            $key = trim((string) ($row['key'] ?? $row['name'] ?? ''));
            $value = trim((string) ($row['value'] ?? ''));
            if ($key === '' && $value === '') {
                continue;
            }
            $bits[] = trim($key . ': ' . $value, ': ');
        }

        return mb_substr(implode('; ', $bits), 0, 280);
    }

    /** @param array<string, mixed> $card */
    private static function buildHaystack(array $card): string
    {
        return mb_strtolower(trim(implode(' ', array_filter([
            (string) ($card['title'] ?? ''),
            (string) ($card['name'] ?? ''),
            (string) ($card['matchedName'] ?? ''),
            (string) ($card['description'] ?? ''),
        ]))));
    }

    private static function containsTypeKeyword(string $haystack, string $type): bool
    {
        if ($type !== 'ulei' && str_contains($haystack, $type)) {
            return true;
        }

        return match ($type) {
            'ulei' => self::containsUleiCandidate($haystack),
            'lichid' => (bool) preg_match(
                '/\b(antigel|antifreeze|drauliquid|brake\s*fluid|apa\s*distilat|apă\s*distilat|dot\s*[345]|dot[345])\b/u',
                $haystack
            ) || str_contains($haystack, 'lichid'),
            'baterie' => str_contains($haystack, 'acumulator')
                || (bool) preg_match('/\bbaterie\b/u', $haystack)
                || (bool) preg_match('/\bcr[0-9]{4}\b/u', $haystack),
            'lubrifiant' => str_contains($haystack, 'lubrifiant')
                || str_contains($haystack, 'wd-40')
                || str_contains($haystack, 'wd40')
                || str_contains($haystack, 'ungere'),
            'bec' => (bool) preg_match('/\b(bec|led|xenon|halogen|h1|h3|h4|h7|h11|d1s|d2s|w5w|p21w)\b/u', $haystack),
            default => str_contains($haystack, $type),
        };
    }

    private static function containsUleiCandidate(string $haystack): bool
    {
        if (str_contains($haystack, 'ulei') || str_contains($haystack, 'lubrifiant')) {
            return true;
        }

        if (self::hasOilViscosityGrade($haystack)) {
            return true;
        }

        if (preg_match('/\b(spray\s+lubrifiant|wd-?40|ungere|paste\s+.*ulei|motor\s+oil|gear\s+oil|atf)\b/u', $haystack)) {
            return true;
        }

        if (self::hasKnownOilBrand($haystack)
            && (self::hasOilViscosityGrade($haystack) || self::hasBottleVolume($haystack))) {
            return true;
        }

        return false;
    }

    private static function isExcluded(string $haystack, string $type): bool
    {
        foreach (self::EXCLUDE_CONTAINS[$type] ?? [] as $fragment) {
            if (str_contains($haystack, mb_strtolower($fragment))) {
                return true;
            }
        }

        if ($type === 'ulei' && self::isMechanicalOilPart($haystack)) {
            return true;
        }

        return false;
    }

    private static function isMechanicalOilPart(string $haystack): bool
    {
        return (bool) preg_match(
            '/\b('
            . 'senzor|sonda|pompa|filtru|garnitur|set\s+piese|set\s+de\s+piese|schimb\s+(de\s+)?ulei|'
            . 'kit\s+|pachet\s+|brat|bieleta|amortizor|radiator|termostat|cheie\s|capac\s+baie|'
            . 'conduct[aă]|furtun|separator|protectie\s+baie|protecție\s+baie|nivel\s+ulei|'
            . 'presostat|modul\s+presiune|intrerupator|întrerupator|indicator\s+nivel|tija\s+nivel|'
            . 'sticla\s+nivel|pluta\s+ulei|dop\s+ulei|canistra|recipient|palnie|recuperator'
            . ')\b/u',
            $haystack
        );
    }

    private static function passesPositiveGate(string $haystack, string $type): bool
    {
        if ($type === 'ulei') {
            return self::passesUleiPositiveGate($haystack);
        }

        $positives = self::POSITIVE_CONTAINS[$type] ?? [];
        if ($positives === []) {
            return true;
        }

        foreach ($positives as $fragment) {
            if (str_contains($haystack, mb_strtolower($fragment))) {
                return true;
            }
        }

        if ($type === 'baterie' && preg_match('/\b(cr[0-9]{4}|ah\s*[0-9]+|[0-9]+\s*ah)\b/u', $haystack)) {
            return true;
        }

        if ($type === 'lichid' && preg_match('/\b(dot\s*[345]|dot[345])\b/u', $haystack)) {
            return true;
        }

        if ($type === 'lichid' && preg_match('/\b[0-9]+(\.[0-9]+)?\s*(l|ml)\b/u', $haystack)) {
            return (bool) preg_match('/\b(lichid|antigel|coolant|frana|frână|parbriz|distilat)\b/u', $haystack);
        }

        if ($type === 'lubrifiant' && preg_match('/\b(spray|wd-?40|ungere)\b/u', $haystack)) {
            return !preg_match('/\b[0-9]{1,2}\s*w\s*-?\s*[0-9]{1,2}\b/ui', $haystack);
        }

        return false;
    }

    private static function passesUleiPositiveGate(string $haystack): bool
    {
        foreach (self::POSITIVE_CONTAINS['ulei'] ?? [] as $fragment) {
            if (str_contains($haystack, mb_strtolower($fragment))) {
                return true;
            }
        }

        if (self::hasOilViscosityGrade($haystack) && self::hasBottleVolume($haystack)) {
            return true;
        }

        if (self::hasOilViscosityGrade($haystack) && self::hasKnownOilBrand($haystack)) {
            return true;
        }

        if (preg_match('/^ulei\b/u', $haystack)) {
            return true;
        }

        if (preg_match('/\b(spray\s+lubrifiant|wd-?40|ungere)\b/u', $haystack)) {
            return true;
        }

        return false;
    }

    private static function hasOilViscosityGrade(string $haystack): bool
    {
        return (bool) preg_match('/\b[0-9]{1,2}\s*w\s*-?\s*[0-9]{1,2}\b/ui', $haystack);
    }

    private static function hasBottleVolume(string $haystack): bool
    {
        return (bool) preg_match('/\b[0-9]+(\.[0-9]+)?\s*(l|ml)\b/ui', $haystack);
    }

    private static function hasKnownOilBrand(string $haystack): bool
    {
        return (bool) preg_match(
            '/\b('
            . 'mobil\s*1|castrol|total\s*quartz|liqui\s*moly|motul|petronas|shell|valvoline|ravenol|'
            . 'elf|motul|aveno|bapco|toyota\s+fuel|power1|dexos|longlife|top\s*tec|magnatec|edge|'
            . 'quartz|ineo|esp\s|helix|advance|rowe|mannol|kroon|fuchs|eurol|ipone'
            . ')\b/ui',
            $haystack
        );
    }

    /**
     * Scor ridicat fără TecDoc — ulei/lichid/baterie identificat clar din denumire furnizor.
     */
    public static function isHighConfidenceShowcaseName(string $name, string $type, int $minScore = 72): bool
    {
        $type = mb_strtolower(trim($type));
        if ($type === '') {
            return false;
        }

        $match = self::matchKeyword(['title' => $name, 'name' => $name], [$type], $minScore, true);
        if ($match === null) {
            return false;
        }

        $haystack = mb_strtolower(trim($name));
        if ($type === 'ulei') {
            return self::hasOilViscosityGrade($haystack)
                || preg_match('/^ulei\b/u', $haystack)
                || (self::hasKnownOilBrand($haystack) && self::hasBottleVolume($haystack));
        }

        if ($type === 'lichid') {
            return (bool) preg_match('/\b(antigel|dot\s*[345]|dot[345]|apa\s*distilat|apă\s*distilat)\b/u', $haystack);
        }

        if ($type === 'baterie') {
            return (bool) preg_match('/\b(baterie|acumulator)\b/u', $haystack)
                && preg_match('/\b([0-9]+\s*ah|ah\s*[0-9]+|cr[0-9]{4})\b/u', $haystack);
        }

        return (int) ($match['score'] ?? 0) >= max($minScore, 84);
    }

    public static function passesRulesForName(string $name, string $type, int $minScore = 72): bool
    {
        $type = mb_strtolower(trim($type));
        if ($type === '') {
            return false;
        }

        return self::matchKeyword(['title' => $name, 'name' => $name], [$type], $minScore, true) !== null;
    }

    /** @param list<string> $types */
    private static function typesList(array $types): string
    {
        return implode(', ', $types);
    }

    /** @param list<string> $lines */
    private static function implodeLines(array $lines): string
    {
        return implode("\n", $lines);
    }

    /** @param list<mixed> $chunk */
    private static function lastIndex(array $chunk): int
    {
        return max(0, count($chunk) - 1);
    }
}

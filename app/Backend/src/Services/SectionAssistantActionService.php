<?php



declare(strict_types=1);



namespace Besoiu\Services;



use Config\Database;

use Besoiu\Services\Products\ProduseService;

use Throwable;



/**

 * Acțiuni executabile din asistentul Composer — badge, vitrină, etc.

 */

final class SectionAssistantActionService

{

    private string $projectRoot;

    private SectionAssistantExtendedActions $extended;

    private SectionAssistantProductCrudService $productCrud;



    public function __construct(?string $projectRoot = null)

    {

        $this->projectRoot = $projectRoot ?? dirname(__DIR__, 3);

        $this->extended = new SectionAssistantExtendedActions($this->projectRoot);

        $this->productCrud = new SectionAssistantProductCrudService();

    }



    /** @param array<string, mixed> $context @param array<string, mixed> $input @return array<string, mixed>|null */

    public function tryHandle(string $message, string $section, array $context, array $input): ?array

    {

        $pending = is_array($input['pending_action'] ?? null) ? $input['pending_action'] : null;

        $shouldExecute = !empty($input['execute_action']) || !empty($input['confirm_execute']);



        if ($pending !== null && $shouldExecute) {

            $executed = $this->executePendingAction($pending, $context, $input);

            if ($executed !== null) {

                return $executed;

            }

        }



        $badgeIntent = $this->detectSetBadgeIntent($message);

        if ($badgeIntent !== null) {

            return $this->handleSetBadge($badgeIntent, $context, $input);

        }



        $importIntent = $this->detectImportPublishIntent($message);

        if ($importIntent !== null) {

            return $this->handleImportPublish($importIntent, $context, $input);

        }



        $vitrinaIntent = $this->detectVitrinaIntent($message);

        if ($vitrinaIntent !== null) {

            return $this->handleVitrina($vitrinaIntent, $context, $input);

        }



        $crudPlan = $this->productCrud->tryHandle($message, $context, $input);

        if ($crudPlan !== null) {

            return $crudPlan;

        }



        $titleIntent = $this->detectEditTitleIntent($message);

        if ($titleIntent !== null) {

            return $this->handleEditProductTitle($titleIntent, $context, $input);

        }



        $extendedPlan = $this->extended->tryHandle($message, $context, $input);

        if ($extendedPlan !== null) {

            return $extendedPlan;

        }



        return null;

    }



    /** @param array<string, mixed> $pending @param array<string, mixed> $context @param array<string, mixed> $input @return array<string, mixed>|null */

    private function executePendingAction(array $pending, array $context, array $input): ?array

    {

        $type = (string) ($pending['type'] ?? '');

        if ($type === 'set_badge') {

            $badge = trim((string) ($pending['badge'] ?? ''));

            if ($badge === '') {

                return null;

            }



            return $this->handleSetBadge([

                'type' => 'set_badge',

                'badge' => $badge,

                'scope' => (string) ($pending['scope'] ?? 'all'),

            ], $context, array_merge($input, ['execute_action' => true, 'confirm_execute' => true]));

        }



        if ($type === 'publish_import') {

            $ids = array_values(array_filter(array_map('intval', (array) ($pending['ids'] ?? []))));

            if ($ids === []) {

                return null;

            }



            return $this->handleImportPublish([

                'type' => 'publish_import',

                'ids' => $ids,

                'limit' => count($ids),

                'keywords' => (array) ($pending['keywords'] ?? []),

                'require_image' => !empty($pending['require_image']),

                'publish_mode' => (string) ($pending['publish_mode'] ?? 'skip'),

                'label' => (string) ($pending['label'] ?? 'Publicare import'),

            ], $context, array_merge($input, ['execute_action' => true, 'confirm_execute' => true]));

        }



        if ($type === 'vitrina_toggle') {

            $ids = array_values(array_filter(array_map('strval', (array) ($pending['ids'] ?? []))));

            if ($ids === []) {

                return null;

            }



            return $this->handleVitrina([

                'type' => 'vitrina_toggle',

                'ids' => $ids,

                'enable' => !empty($pending['enable']),

                'keyword' => (string) ($pending['keyword'] ?? ''),

            ], $context, array_merge($input, ['execute_action' => true, 'confirm_execute' => true]));

        }



        if ($type === 'edit_product_title') {

            $code = trim((string) ($pending['code'] ?? ''));

            if ($code === '') {

                return null;

            }



            return $this->handleEditProductTitle([

                'type' => 'edit_product_title',

                'code' => $code,

                'mode' => (string) ($pending['mode'] ?? 'auto'),

            ], $context, array_merge($input, ['execute_action' => true, 'confirm_execute' => true]));

        }



        if ($type === 'product_crud') {

            return $this->productCrud->executePending($pending);

        }



        if (str_starts_with($type, 'extended_')) {

            return $this->extended->executePendingAction($pending, $context);

        }



        return null;

    }



    public static function messageLooksLikeAction(string $message): bool

    {

        $self = new self();



        return SectionAssistantProductCrudService::messageLooksLikeCrud($message)

            || $self->detectSetBadgeIntent($message) !== null

            || $self->detectImportPublishIntent($message) !== null

            || $self->detectVitrinaIntent($message) !== null

            || $self->detectEditTitleIntent($message) !== null

            || SectionAssistantExtendedActions::messageLooksLikeAction($message);

    }



    /** @return array<string, mixed>|null */

    private function detectSetBadgeIntent(string $message): ?array

    {

        $lower = mb_strtolower($message, 'UTF-8');

        $lower = str_replace(['bagec', 'badg', 'bagde', 'eticheta'], 'badge', $lower);



        $config = $this->badgeConfig();

        $badge = null;



        foreach (array_keys($config) as $key) {

            if (preg_match('/\b' . preg_quote($key, '/') . '\b/u', $lower)) {

                $badge = $key;

                break;

            }

        }



        if ($badge === null && preg_match('/\bbadge\s+(hot|promo|nou|top|stoc|recomandat)\b/u', $lower, $m)) {

            $badge = $m[1];

        }



        if ($badge === null) {

            return null;

        }



        $hasAction = (bool) preg_match(

            '/\b(badge|mapez|map(e|a)z|aplic|adaug[aă]?|setez|pune|vreau|necesit|face|toate|tot\s+catalogul|confirma)\b/u',

            $lower

        );

        if (!$hasAction) {

            return null;

        }



        $scope = 'all';

        if (preg_match('/\b(selectat|bifat|vizibil|pagina)\b/u', $lower)) {

            $scope = 'page';

        }



        return [

            'type' => 'set_badge',

            'badge' => $badge,

            'scope' => $scope,

        ];

    }



    /** @return array<string, mixed>|null */

    private function detectImportPublishIntent(string $message): ?array

    {

        $lower = mb_strtolower($message, 'UTF-8');



        $hasPublishVerb = (bool) preg_match(

            '/\b(adaug[aă]?|public[aă]?|pune|activeaz[aă]?|aprob[aă]?|import[aă]?)\b/u',

            $lower

        );

        $hasImportCtx = (bool) preg_match(

            '/\b(import|coad[aă]|staging|importreview|scanat|pending|din coada)\b/u',

            $lower

        );

        $hasConsumable = (bool) preg_match(

            '/\b(ulei\w*|lichid\w*|consumabil\w*|antigel|freon|electr\w*|filtre?)\b/u',

            $lower

        );



        $limit = null;

        if (preg_match('/\b(\d{1,2})\s*(produse?|ulei\w*|articole?|randuri?)\b/u', $lower, $m)) {

            $limit = (int) $m[1];

        } elseif (preg_match('/\b(un|o)\s+(produs|ulei)\b/u', $lower)) {

            $limit = 1;

        }



        if (!$hasPublishVerb && !($limit !== null && ($hasConsumable || $hasImportCtx))) {

            return null;

        }



        if (!$hasImportCtx && !$hasConsumable && $limit === null) {

            return null;

        }



        if ($limit === null) {

            $limit = 10;

        }

        $limit = max(1, min(50, $limit));



        $keywords = [];

        foreach ([

            'ulei', 'uleiuri', 'lichid', 'lichide', 'consumabil', 'antigel', 'freon',

            'electr', 'filtre', 'filtru', 'baterie', 'bec', 'suspensie', 'frana',

        ] as $kw) {

            if (preg_match('/\b' . preg_quote($kw, '/') . '\w*\b/u', $lower)) {

                $keywords[] = $kw;

            }

        }



        $requireImage = (bool) preg_match(

            '/\b(cu imagini|doar cu imagini|care au imagini|doar imagini|imagine valid)\b/u',

            $lower

        );



        $publishMode = (bool) preg_match('/\b(actualizeaz[aă]?|overwrite|suprascrie)\b/u', $lower)

            ? 'overwrite'

            : 'skip';



        $labelParts = ['Publicare', (string) $limit, 'prod.'];

        if ($keywords !== []) {

            $labelParts[] = implode('/', array_slice($keywords, 0, 2));

        }

        if ($requireImage) {

            $labelParts[] = 'cu imagini';

        }



        return [

            'type' => 'publish_import',

            'limit' => $limit,

            'keywords' => array_values(array_unique($keywords)),

            'require_image' => $requireImage,

            'publish_mode' => $publishMode,

            'label' => implode(' ', $labelParts),

        ];

    }



    /** @param array<string, mixed> $intent @param array<string, mixed> $context @param array<string, mixed> $input @return array<string, mixed> */

    private function handleImportPublish(array $intent, array $context, array $input): array

    {

        return $this->actionError('Modulul de import produse a fost dezactivat.');



        $execute = !empty($input['execute_action']) || !empty($input['confirm_execute']);

        $ids = array_values(array_filter(array_map('intval', (array) ($intent['ids'] ?? []))));

        $limit = max(1, min(50, (int) ($intent['limit'] ?? 10)));

        $keywords = array_values(array_filter(array_map('strval', (array) ($intent['keywords'] ?? []))));

        $requireImage = !empty($intent['require_image']);

        $publishMode = (string) ($intent['publish_mode'] ?? 'skip');

        $label = trim((string) ($intent['label'] ?? 'Publicare import'));



        try {

            $pdo = Database::getDB();

        } catch (Throwable $e) {

            return $this->actionError('Nu pot accesa baza de date: ' . $e->getMessage());

        }



        if ($execute && $ids !== []) {

            return $this->executeImportPublish($pdo, $ids, $publishMode, $label);

        }



        $rows = SectionAssistantImportQueries::fetchPending($pdo, $limit, $keywords, $requireImage);

        $queueTotal = SectionAssistantImportQueries::countPending($pdo);



        if ($rows === []) {

            $hint = $queueTotal === 0

                ? 'Coada import este goala. Ruleaza scan CSV sau import furnizor mai intai.'

                : ($requireImage

                    ? 'Nu am gasit produse pending cu imagine care sa corespunda filtrului.'

                    : 'Nu am gasit produse in coada care sa corespunda filtrului.');



            return [

                'source' => 'action_error',

                'reply_ro' => $hint,

                'intent' => 'import',

                'cheat_sheet' => [

                    'kind' => 'action_error',

                    'title' => 'Nimic de publicat',

                    'subtitle' => $hint,

                    'updated_at' => date('d.m.Y H:i'),

                    'stats' => [

                        ['key' => 'queue', 'label' => 'In coada import', 'value' => (string) $queueTotal],

                        ['key' => 'limit', 'label' => 'Limita ceruta', 'value' => (string) $limit],

                    ],

                    'hints' => [$hint],

                    'shortcuts' => [

                        ['label' => 'Coada import', 'url' => '/admin/importreview'],

                        ['label' => 'Import CSV', 'url' => '/admin/import'],

                    ],

                ],

                'suggestions' => [],

                'next_steps' => [],

            ];

        }



        $productRows = [];

        $selectedIds = [];

        foreach ($rows as $row) {

            if (!is_array($row)) {

                continue;

            }

            $selectedIds[] = (int) ($row['id'] ?? 0);

            $productRows[] = SectionAssistantImportQueries::mapQueueRow($row);

        }

        $selectedIds = array_values(array_filter($selectedIds));



        if ($execute) {

            return $this->executeImportPublish($pdo, $selectedIds, $publishMode, $label);

        }



        return $this->buildImportPublishPreview(

            $label,

            $productRows,

            $selectedIds,

            $keywords,

            $requireImage,

            $publishMode,

            $queueTotal

        );

    }



    /** @param list<int> $ids @return array<string, mixed> */

    private function executeImportPublish(PDO $pdo, array $ids, string $publishMode, string $label): array

    {

        $ids = array_values(array_filter(array_map('intval', $ids)));

        if ($ids === []) {

            return $this->actionError('Nu exista produse selectate pentru publicare.');

        }



        if (!$this->ensureImportPublishLib()) {

            return $this->actionError('Modulul de publicare import nu este disponibil.');

        }



        $placeholders = implode(',', array_fill(0, count($ids), '?'));

        try {

            $stmt = $pdo->prepare(

                "SELECT * FROM import_produse WHERE id IN ($placeholders) AND status = 'pending'"

            );

            $stmt->execute($ids);

            $pendingRows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

        } catch (Throwable $e) {

            return $this->actionError('Eroare la citirea cozii: ' . $e->getMessage());

        }



        if ($pendingRows === []) {

            return $this->actionError('Produsele nu mai sunt in coada (publicate sau sterse).');

        }



        $mode = function_exists('import_resolve_publish_mode')

            ? import_resolve_publish_mode($publishMode)

            : $publishMode;



        try {

            $stats = import_process_publish_rows($pdo, $pendingRows, $mode);

        } catch (Throwable $e) {

            return $this->actionError('Eroare la publicare: ' . $e->getMessage());

        }



        $added = (int) ($stats['added'] ?? 0);

        $updated = (int) ($stats['updated'] ?? 0);

        $skipped = (int) ($stats['skipped'] ?? 0);

        $published = $added + $updated + (int) ($stats['forced'] ?? 0);



        $this->logAction('publish_import', [

            'ids' => $ids,

            'added' => $added,

            'updated' => $updated,

            'skipped' => $skipped,

            'publish_mode' => $mode,

        ]);



        $message = function_exists('import_build_publish_message')

            ? import_build_publish_message($stats)

            : sprintf('Publicate %d, actualizate %d, sarite %d.', $added, $updated, $skipped);



        $resultRows = [];

        foreach (array_slice($pendingRows, 0, 20) as $row) {

            if (!is_array($row)) {

                continue;

            }

            $mapped = SectionAssistantImportQueries::mapQueueRow($row);

            $mapped['badge_after'] = 'Publicat';

            $resultRows[] = $mapped;

        }



        return [

            'source' => 'action_executed',

            'reply_ro' => $message,

            'intent' => 'import',

            'action' => [

                'type' => 'publish_import',

                'added' => $added,

                'updated' => $updated,

                'skipped' => $skipped,

                'published' => $published,

            ],

            'cheat_sheet' => $this->buildActionCheatSheet(

                'Import publicat',

                $label,

                [

                    ['key' => 'published', 'label' => 'Publicate', 'value' => (string) $published],

                    ['key' => 'added', 'label' => 'Noi', 'value' => (string) $added],

                    ['key' => 'updated', 'label' => 'Actualizate', 'value' => (string) $updated],

                    ['key' => 'skipped', 'label' => 'Sarite', 'value' => (string) $skipped, 'warn' => $skipped > 0],

                ],

                $resultRows,

                $skipped > 0

                    ? ['Unele produse exista deja in magazin — verifica Conflicte in coada import.']

                    : ['Produsele apar acum in Lista produse si pe site.'],

                [

                    ['label' => 'Lista produse', 'url' => '/admin/product'],

                    ['label' => 'Coada import', 'url' => '/admin/importreview'],

                ]

            ),

            'suggestions' => [],

            'next_steps' => [],

        ];

    }



    /**

     * @param list<array<string, mixed>> $productRows

     * @param list<int> $ids

     * @param list<string> $keywords

     * @return array<string, mixed>

     */

    private function buildImportPublishPreview(

        string $label,

        array $productRows,

        array $ids,

        array $keywords,

        bool $requireImage,

        string $publishMode,

        int $queueTotal,

    ): array {

        $count = count($productRows);



        return [

            'source' => 'action_preview',

            'reply_ro' => sprintf(

                'Voi publica %d produs(e) din coada import (%d in total in coada).',

                $count,

                $queueTotal

            ),

            'intent' => 'import',

            'pending_action' => [

                'type' => 'publish_import',

                'ids' => $ids,

                'limit' => $count,

                'keywords' => $keywords,

                'require_image' => $requireImage,

                'publish_mode' => $publishMode,

                'label' => $label,

            ],

            'cheat_sheet' => [

                'kind' => 'action_preview',

                'title' => 'Confirmare publicare import',

                'subtitle' => $label,

                'updated_at' => date('d.m.Y H:i'),

                'stats' => [

                    ['key' => 'target', 'label' => 'De publicat', 'value' => (string) $count],

                    ['key' => 'queue', 'label' => 'In coada', 'value' => (string) $queueTotal],

                    ['key' => 'mode', 'label' => 'Mod conflict', 'value' => $publishMode === 'overwrite' ? 'Actualizeaza' : 'Sari peste'],

                ],

                'products' => $productRows,

                'products_note' => 'Produse din coada import care vor fi adaugate in magazin:',

                'hints' => array_values(array_filter([

                    'Verifica lista inainte de publicare.',

                    $requireImage ? 'Filtru activ: doar produse cu imagine valida.' : null,

                ])),

                'confirm_required' => true,

                'confirm_question' => 'Doresti sa publici ' . $count . ' produs(e) din coada import?',

                'shortcuts' => [

                    ['label' => 'Coada import', 'url' => '/admin/importreview'],

                ],

                'confirm' => [

                    'label' => 'Da, publica ' . $count . ' produs(e)',

                    'action' => 'publish_import',

                ],

            ],

            'suggestions' => [],

            'next_steps' => [],

        ];

    }



    /** @return array<string, mixed>|null */

    private function detectVitrinaIntent(string $message): ?array

    {

        $lower = mb_strtolower($message, 'UTF-8');

        if (!(bool) preg_match('/\b(vitrin[aă]|homepage)\b/u', $lower)) {

            return null;

        }

        if (!(bool) preg_match('/\b(pune|adaug[aă]?|scoate|sterge|elimina|activeaz[aă]?)\b/u', $lower)) {

            return null;

        }

        $enable = !(bool) preg_match('/\b(scoate|sterge|elimina|de pe|off)\b/u', $lower);

        $keyword = '';

        if (preg_match('/\b(pune|adaug[aă]?|scoate|sterge)\s+(.+?)\s+(pe|de pe)\s+vitrin/u', $lower, $m)) {

            $keyword = trim($m[2]);

        } elseif (preg_match('/\b(ulei\w*|castrol|motul|antigel|lichid\w*|[a-z0-9\-]{3,})\b/u', $lower, $m)) {

            $keyword = trim($m[1]);

        }

        if ($keyword === '' || in_array($keyword, ['pe', 'de', 'vitrina', 'homepage'], true)) {

            return null;

        }



        return [

            'type' => 'vitrina_toggle',

            'enable' => $enable,

            'keyword' => $keyword,

        ];

    }



    /** @return array<string, mixed>|null */

    private function detectEditTitleIntent(string $message): ?array

    {

        $lower = mb_strtolower($message, 'UTF-8');



        $wantsTitleEdit = (bool) preg_match(

            '/\b(editez|editeaz[aă]|edit(ez|ezi|are|are)?|modific[aă]?|schimb[aă]?|actualizez|fix|fx|corect(eaz[aă])?)\b/u',

            $lower

        ) && (bool) preg_match('/\b(titlu(lui)?|denumir(e|ea|ii)?|nume(lui)?|pname)\b/u', $lower);



        $wantsCategoryInTitle = (bool) preg_match('/\badaug[aă]?\b/u', $lower)

            && (bool) preg_match('/\b(categor\w*|subcategor\w*)\b/u', $lower)

            && (bool) preg_match('/\b(titlu(lui)?|denumir(e|ea)?|nume(lui)?|final)\b/u', $lower);



        if (!$wantsTitleEdit && !$wantsCategoryInTitle) {

            return null;

        }



        $code = $this->extractProductCodeFromMessage($message);



        $mode = 'auto';

        if (preg_match('/\bsubcategor/i', $lower)) {

            $mode = 'append_subcategory';

        } elseif (preg_match('/\bcategor/i', $lower)) {

            $mode = 'append_category';

        }



        return [

            'type' => 'edit_product_title',

            'code' => $code,

            'mode' => $mode,

        ];

    }



    /** @return array<string, mixed>|null */

    private function extractProductCodeFromMessage(string $message): ?string
    {
        return SectionAssistantQueryHelper::extractProductCode($message);
    }



    /** @param array<string, mixed> $intent @param array<string, mixed> $context @param array<string, mixed> $input @return array<string, mixed> */

    private function handleEditProductTitle(array $intent, array $context, array $input): array

    {

        $code = trim((string) ($intent['code'] ?? ''));

        $mode = (string) ($intent['mode'] ?? 'auto');



        if ($code === '') {

            return $this->actionError(

                'Spune codul produsului in mesaj. Exemplu: edit titlu 30204A adauga categorie la final'

            );

        }



        $product = SectionAssistantProductQueries::findByCode($code);

        if ($product === null) {

            return $this->actionError('Nu am gasit produs activ cu codul ' . $code . '.');

        }



        $oldName = trim((string) ($product['pName'] ?? ''));

        $category = trim((string) ($product['pCategory'] ?? ''));

        $subcategory = trim((string) ($product['pSubcategory'] ?? ''));

        $newName = $this->buildTitleWithCategorySuffix($oldName, $category, $subcategory, $mode);



        if ($newName === $oldName) {

            return $this->actionError(

                'Titlul "' . $oldName . '" contine deja categoria/subcategoria sau produsul nu are categorie setata in Edit produs.'

            );

        }



        $execute = !empty($input['execute_action']) || !empty($input['confirm_execute']);

        if (!$execute) {

            return $this->buildEditTitlePreview($product, $oldName, $newName, $intent);

        }



        try {

            $service = new ProduseService();

            $id = trim((string) ($product['randomn_id'] ?? ''));

            if ($id === '') {

                return $this->actionError('Produs fara ID valid.');

            }

            if (!$service->editProduse($id, ['pName' => $newName])) {

                return $this->actionError('Nu am putut salva noul titlu.');

            }



            $this->logAction('edit_product_title', [

                'code' => $code,

                'id' => $id,

                'old_name' => $oldName,

                'new_name' => $newName,

            ]);



            return [

                'source' => 'action_executed',

                'reply_ro' => sprintf('Am actualizat titlul produsului %s.', $code),

                'intent' => 'configure_products',

                'action' => [

                    'type' => 'edit_product_title',

                    'code' => $code,

                    'old_name' => $oldName,

                    'new_name' => $newName,

                ],

                'cheat_sheet' => $this->buildActionCheatSheet(

                    'Titlu actualizat',

                    sprintf('Cod %s — categorie adaugata la final', $code),

                    [

                        ['key' => 'code', 'label' => 'Cod', 'value' => $code],

                        ['key' => 'before', 'label' => 'Titlu vechi', 'value' => $oldName],

                        ['key' => 'after', 'label' => 'Titlu nou', 'value' => $newName, 'warn' => true],

                    ],

                    [[

                        'name' => $newName,

                        'brand' => trim((string) ($product['pBrand'] ?? '')) ?: '-',

                        'code' => $code,

                        'category' => $category !== '' ? $category : '-',

                        'price' => $this->formatRon((float) ($product['pPrice'] ?? 0)),

                        'edit_url' => '/admin/editproduse?id=' . rawurlencode($id),

                    ]],

                    ['Titlul e salvat. Panoul ramane deschis.', 'Pentru badge: badge HOT ' . $code],

                    [

                        ['label' => 'Edit produs', 'url' => '/admin/editproduse?id=' . rawurlencode($id)],

                        ['label' => 'Lista produse', 'url' => '/admin/product'],

                        ['label' => 'Pune badge HOT (chat)', 'url' => '/admin/product#bsa-hint=badge-' . rawurlencode($code)],

                    ]

                ),

                'suggestions' => [[

                    'type' => 'feature',

                    'label' => 'Continua in chat: badge HOT ' . $code,

                ]],

                'next_steps' => [

                    'Titlul a fost salvat — panoul Composer ramane deschis.',

                    'Pentru badge pe acelasi produs scrie: badge HOT ' . $code,

                ],

            ];

        } catch (Throwable $e) {

            return $this->actionError('Eroare la editarea titlului: ' . $e->getMessage());

        }

    }



    /** @param array<string, mixed> $product @param array<string, mixed> $intent @return array<string, mixed> */

    private function buildEditTitlePreview(array $product, string $oldName, string $newName, array $intent): array

    {

        $code = trim((string) ($product['pCode'] ?? ''));

        $id = trim((string) ($product['randomn_id'] ?? ''));



        return [

            'source' => 'action_preview',

            'reply_ro' => sprintf('Voi actualiza titlul produsului %s — categorie la final.', $code),

            'intent' => 'configure_products',

            'pending_action' => [

                'type' => 'edit_product_title',

                'code' => $code,

                'mode' => (string) ($intent['mode'] ?? 'auto'),

            ],

            'cheat_sheet' => [

                'kind' => 'action_preview',

                'title' => 'Confirmare editare titlu',

                'subtitle' => 'Cod ' . $code,

                'updated_at' => date('d.m.Y H:i'),

                'stats' => [

                    ['key' => 'before', 'label' => 'Titlu curent', 'value' => $oldName],

                    ['key' => 'after', 'label' => 'Titlu nou', 'value' => $newName, 'warn' => true],

                    ['key' => 'cat', 'label' => 'Categorie', 'value' => trim((string) ($product['pCategory'] ?? '')) ?: '-'],

                    ['key' => 'sub', 'label' => 'Subcategorie', 'value' => trim((string) ($product['pSubcategory'] ?? '')) ?: '-'],

                ],

                'products' => [[

                    'name' => $newName,

                    'brand' => trim((string) ($product['pBrand'] ?? '')) ?: '-',

                    'code' => $code,

                    'category' => trim((string) ($product['pCategory'] ?? '')) ?: '-',

                    'price' => $this->formatRon((float) ($product['pPrice'] ?? 0)),

                    'edit_url' => $id !== '' ? '/admin/editproduse?id=' . rawurlencode($id) : '/admin/product',

                ]],

                'hints' => [

                    'Verifica titlul nou inainte de salvare.',

                ],

                'confirm_required' => true,

                'confirm_question' => 'Doresti sa salvezi noul titlu pentru produsul ' . $code . '?',

                'confirm' => [

                    'label' => 'Da, salveaza titlul',

                    'action' => 'edit_product_title',

                    'code' => $code,

                    'mode' => (string) ($intent['mode'] ?? 'auto'),

                ],

            ],

            'suggestions' => [],

            'next_steps' => [],

        ];

    }



    private function buildTitleWithCategorySuffix(string $name, string $category, string $subcategory, string $mode = 'auto'): string

    {

        $name = trim($name);

        $category = trim($category);

        $subcategory = trim($subcategory);



        if ($name === '') {

            return $name;

        }



        $suffix = '';

        if ($mode === 'append_subcategory' && $subcategory !== '' && $subcategory !== '-') {

            $suffix = $subcategory;

        } elseif ($mode === 'append_category' && $category !== '' && $category !== '-') {

            $suffix = $category;

        } else {

            foreach ([$category, $subcategory] as $candidate) {

                $candidate = trim($candidate);

                if ($candidate === '' || $candidate === '-') {

                    continue;

                }

                if (mb_stripos($name, $candidate, 0, 'UTF-8') === false) {

                    $suffix = $candidate;

                    break;

                }

            }

        }



        if ($suffix === '' || mb_stripos($name, $suffix, 0, 'UTF-8') !== false) {

            return $name;

        }



        return rtrim($name, " \t.-|") . ' - ' . $suffix;

    }



    /** @param array<string, mixed> $intent @param array<string, mixed> $context @param array<string, mixed> $input @return array<string, mixed> */

    private function handleVitrina(array $intent, array $context, array $input): array

    {

        $execute = !empty($input['execute_action']) || !empty($input['confirm_execute']);

        $enable = !empty($intent['enable']);

        $keyword = trim((string) ($intent['keyword'] ?? ''));

        $ids = array_values(array_filter(array_map('strval', (array) ($intent['ids'] ?? []))));



        if ($execute && $ids !== []) {

            return $this->executeVitrinaToggle($ids, $enable, $keyword);

        }



        $matches = SectionAssistantProductQueries::findByKeyword($keyword, 10);

        if ($matches === []) {

            return $this->actionError('Nu am gasit produse pentru: ' . $keyword);

        }

        $productRows = [];

        $targetIds = [];

        foreach ($matches as $row) {

            if (!is_array($row)) {

                continue;

            }

            $productRows[] = $row;

            if (preg_match('/id=([^&]+)/', (string) ($row['edit_url'] ?? ''), $m)) {

                $targetIds[] = rawurldecode($m[1]);

            }

        }

        $targetIds = array_values(array_unique(array_filter($targetIds)));

        if ($execute) {

            return $this->executeVitrinaToggle($targetIds, $enable, $keyword);

        }



        $service = new ProduseService();

        $onVitrina = $service->countVitrinaProducts();

        $max = $service->vitrinaHomepageMax();



        return [

            'source' => 'action_preview',

            'reply_ro' => sprintf(

                'Voi %s %d produs(e) pe vitrina (%d/%d acum).',

                $enable ? 'adauga' : 'scoate',

                count($targetIds),

                $onVitrina,

                $max

            ),

            'intent' => 'configure_products',

            'pending_action' => [

                'type' => 'vitrina_toggle',

                'ids' => $targetIds,

                'enable' => $enable,

                'keyword' => $keyword,

            ],

            'cheat_sheet' => [

                'kind' => 'action_preview',

                'title' => 'Confirmare vitrina',

                'subtitle' => ($enable ? 'Adauga' : 'Scoate') . ' produse matching: ' . $keyword,

                'updated_at' => date('d.m.Y H:i'),

                'stats' => [

                    ['key' => 'target', 'label' => 'Produse', 'value' => (string) count($targetIds)],

                    ['key' => 'vitrina', 'label' => 'Pe vitrina acum', 'value' => $onVitrina . '/' . $max],

                ],

                'products' => $productRows,

                'products_note' => 'Produse afectate:',

                'hints' => ['Verifica produsele afectate. Max ' . $max . ' pe vitrina.'],

                'confirm_required' => true,

                'confirm_question' => 'Doresti sa ' . ($enable ? 'adaugi pe' : 'scoti de pe') . ' vitrina ' . count($targetIds) . ' produs(e)?',

                'confirm' => [

                    'label' => 'Da, ' . ($enable ? 'adauga vitrina' : 'scoate vitrina'),

                    'action' => 'vitrina_toggle',

                ],

            ],

            'suggestions' => [],

            'next_steps' => [],

        ];

    }



    /** @param list<string> $ids @return array<string, mixed> */

    private function executeVitrinaToggle(array $ids, bool $enable, string $keyword): array

    {

        $service = new ProduseService();

        $updated = 0;

        $failed = 0;

        foreach ($ids as $id) {

            $id = trim($id);

            if ($id === '') {

                continue;

            }

            if ($enable && !$service->canAddToVitrina($id)) {

                ++$failed;

                continue;

            }

            if ($service->setProductVitrina($id, $enable)) {

                ++$updated;

            } else {

                ++$failed;

            }

        }



        $this->logAction('vitrina_toggle', ['ids' => $ids, 'enable' => $enable, 'updated' => $updated]);



        $products = SectionAssistantProductQueries::vitrinaSnapshot(15);



        return [

            'source' => 'action_executed',

            'reply_ro' => sprintf('Vitrina: %d produs(e) %s.', $updated, $enable ? 'adaugate' : 'scoase'),

            'intent' => 'configure_products',

            'action' => ['type' => 'vitrina_toggle', 'updated' => $updated, 'failed' => $failed],

            'cheat_sheet' => $this->buildActionCheatSheet(

                'Vitrina actualizata',

                ($enable ? 'Adaugat' : 'Scos') . ': ' . $keyword,

                [

                    ['key' => 'updated', 'label' => 'Actualizate', 'value' => (string) $updated],

                    ['key' => 'on', 'label' => 'Pe vitrina', 'value' => (string) $products['count'] . '/' . $products['max']],

                ],

                $products['items'],

                $failed > 0 ? ['Unele produse nu au fost actualizate (limita vitrina sau ID invalid).'] : [],

                [['label' => 'Vitrina', 'url' => '/admin/vitrina']]

            ),

            'suggestions' => [],

            'next_steps' => [],

        ];

    }



    private function ensureImportPublishLib(): bool

    {

        static $loaded = false;

        if ($loaded) {

            return function_exists('import_process_publish_rows');

        }



        $critical = $this->projectRoot . '/system/import-queue-critical.php';

        if (is_file($critical)) {

            require_once $critical;

        }



        if (!defined('IMPORT_PRODUCE_SKIP_HTTP')) {

            define('IMPORT_PRODUCE_SKIP_HTTP', true);

        }

        if (!defined('IMPORT_ACTION_AS_LIB')) {

            define('IMPORT_ACTION_AS_LIB', true);

        }



        return false;



        $actionLib = $this->projectRoot . '/admin/src/Controllers/Produse/importproduse_action.php';

        if (is_file($actionLib)) {

            require_once $actionLib;

        }



        $loaded = true;



        return function_exists('import_process_publish_rows');

    }



    /** @param array<string, mixed> $intent @param array<string, mixed> $context @param array<string, mixed> $input @return array<string, mixed> */

    private function handleSetBadge(array $intent, array $context, array $input): array

    {

        $badge = (string) ($intent['badge'] ?? '');

        $config = $this->badgeConfig();

        if (!isset($config[$badge])) {

            return $this->actionError('Badge necunoscut: ' . $badge);

        }



        $label = (string) ($config[$badge]['admin'] ?? $config[$badge]['label'] ?? strtoupper($badge));

        $execute = !empty($input['execute_action']) || !empty($input['confirm_execute']);



        if (!$execute) {

            return $this->buildBadgePreview($badge, $label, $context, $intent);

        }



        try {

            $service = new ProduseService();

            if ((string) ($intent['scope'] ?? 'all') === 'all') {

                $updated = $service->setBadgeOnAllActive($badge);

            } else {

                return $this->actionError(

                    'Pentru produsele de pe pagina curenta, foloseste Lista produse - Actiuni - Aplica badge selectiv.'

                );

            }



            $this->logAction('set_badge', [

                'badge' => $badge,

                'scope' => (string) ($intent['scope'] ?? 'all'),

                'updated' => $updated,

            ]);



            $products = $this->fetchProductsWithBadge($badge, 20);



            return [

                'source' => 'action_executed',

                'reply_ro' => sprintf(

                    'Am aplicat badge %s pe %d produs(e) active.',

                    $label,

                    $updated

                ),

                'intent' => 'configure_products',

                'action' => [

                    'type' => 'set_badge',

                    'badge' => $badge,

                    'badge_label' => $label,

                    'updated' => $updated,

                    'scope' => 'all',

                ],

                'cheat_sheet' => $this->buildActionCheatSheet(

                    'Badge aplicat',

                    sprintf('Badge %s pe toate produsele active', $label),

                    [

                        ['key' => 'updated', 'label' => 'Produse actualizate', 'value' => (string) $updated],

                        ['key' => 'badge', 'label' => 'Badge setat', 'value' => $label],

                    ],

                    $products,

                    $updated > 0

                        ? ['Badge-ul apare in coltul dreapta-sus al cardului pe site. Reincarca pagina daca nu il vezi imediat.']

                        : ['Nu exista produse active de actualizat.'],

                    [

                        ['label' => 'Lista produse', 'url' => '/admin/product'],

                        ['label' => 'Vezi pe site', 'url' => '/'],

                    ]

                ),

                'suggestions' => [],

                'next_steps' => [],

            ];

        } catch (Throwable $e) {

            return $this->actionError('Eroare la aplicarea badge-ului: ' . $e->getMessage());

        }

    }



    /** @param array<string, mixed> $intent @param array<string, mixed> $context @return array<string, mixed> */

    private function buildBadgePreview(string $badge, string $label, array $context, array $intent): array

    {

        $counts = is_array($context['counts'] ?? null) ? $context['counts'] : [];

        $total = (int) ($counts['produse_total'] ?? 0);

        $snapshot = is_array($context['catalog_snapshot'] ?? null) ? $context['catalog_snapshot'] : [];

        $samples = is_array($snapshot['sample_products'] ?? null) ? $snapshot['sample_products'] : [];



        $productRows = [];

        foreach ($samples as $row) {

            if (!is_array($row)) {

                continue;

            }

            $id = trim((string) ($row['randomn_id'] ?? ''));

            $productRows[] = [

                'name' => trim((string) ($row['pName'] ?? '')) ?: 'Fara denumire',

                'brand' => trim((string) ($row['pBrand'] ?? '')) ?: '-',

                'code' => trim((string) ($row['pCode'] ?? '')) ?: '-',

                'category' => trim((string) ($row['pCategory'] ?? '')) ?: '-',

                'price' => $this->formatRon((float) ($row['pPrice'] ?? 0)),

                'edit_url' => $id !== '' ? '/admin/editproduse?id=' . rawurlencode($id) : '/admin/product',

                'badge_after' => $label,

            ];

        }



        return [

            'source' => 'action_preview',

            'reply_ro' => sprintf(

                'Voi aplica badge %s pe %d produs(e) active din catalog.',

                $label,

                $total

            ),

            'intent' => 'configure_products',

            'pending_action' => [

                'type' => 'set_badge',

                'badge' => $badge,

                'scope' => (string) ($intent['scope'] ?? 'all'),

            ],

            'cheat_sheet' => [

                'kind' => 'action_preview',

                'title' => 'Confirmare actiune',

                'subtitle' => sprintf('Badge %s pe toate produsele active', $label),

                'updated_at' => date('d.m.Y H:i'),

                'stats' => [

                    ['key' => 'target', 'label' => 'Produse tinta', 'value' => (string) $total],

                    ['key' => 'badge', 'label' => 'Badge nou', 'value' => $label, 'warn' => true],

                ],

                'products' => $productRows,

                'products_note' => $total > 0

                    ? 'Preview: aceste produse vor primi badge-ul de mai sus.'

                    : 'Nu exista produse active.',

                'hints' => [

                    'Verifica inainte de salvare.',

                    'Apasa «Da, salveaza» doar daca esti sigur.',

                ],

                'confirm_required' => true,

                'confirm_question' => 'Doresti sa aplici badge ' . $label . ' pe toate produsele active?',

                'confirm' => [

                    'action' => 'set_badge',

                    'badge' => $badge,

                ],

                'shortcuts' => [

                    ['label' => 'Lista produse', 'url' => '/admin/product'],

                ],

            ],

            'suggestions' => [],

            'next_steps' => [],

        ];

    }



    /**

     * @param list<array<string, mixed>> $stats

     * @param list<array<string, mixed>> $products

     * @param list<string> $hints

     * @param list<array{label:string,url:string}> $shortcuts

     * @return array<string, mixed>

     */

    private function buildActionCheatSheet(

        string $title,

        string $subtitle,

        array $stats,

        array $products,

        array $hints,

        array $shortcuts,

    ): array {

        return [

            'kind' => 'action_result',

            'title' => $title,

            'subtitle' => $subtitle,

            'updated_at' => date('d.m.Y H:i'),

            'stats' => $stats,

            'products' => $products,

            'products_note' => $products !== [] ? 'Produse actualizate:' : null,

            'hints' => $hints,

            'shortcuts' => $shortcuts,

        ];

    }



    /** @param array<string, mixed> $meta */

    private function logAction(string $type, array $meta): void

    {

        $dir = $this->projectRoot . '/admin/storage/assistant_actions';

        if (!is_dir($dir)) {

            @mkdir($dir, 0775, true);

        }

        $entry = [

            'at' => date('c'),

            'type' => $type,

            'meta' => $meta,

            'user' => (string) ($_SESSION['admin_user'] ?? $_SESSION['user_name'] ?? 'admin'),

        ];

        @file_put_contents(

            $dir . '/actions_' . date('Ymd') . '.jsonl',

            json_encode($entry, JSON_UNESCAPED_UNICODE) . "\n",

            FILE_APPEND

        );

    }



    /** @return list<array<string, mixed>> */

    private function fetchProductsWithBadge(string $badge, int $limit = 20): array

    {

        $limit = max(1, min(50, $limit));

        try {

            $pdo = Database::getDB();

            $stmt = $pdo->prepare(

                "SELECT randomn_id, pName, pBrand, pCode, pCategory, pPrice, pBadge

                 FROM produse

                 WHERE COALESCE(status, 1) <> 0 AND pBadge = :badge

                 ORDER BY id DESC

                 LIMIT {$limit}"

            );

            $stmt->execute(['badge' => $badge]);

            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

            $config = $this->badgeConfig();

            $label = (string) ($config[$badge]['admin'] ?? strtoupper($badge));

            $out = [];

            foreach ($rows as $row) {

                if (!is_array($row)) {

                    continue;

                }

                $id = trim((string) ($row['randomn_id'] ?? ''));

                $out[] = [

                    'name' => trim((string) ($row['pName'] ?? '')) ?: '-',

                    'brand' => trim((string) ($row['pBrand'] ?? '')) ?: '-',

                    'code' => trim((string) ($row['pCode'] ?? '')) ?: '-',

                    'category' => trim((string) ($row['pCategory'] ?? '')) ?: '-',

                    'price' => $this->formatRon((float) ($row['pPrice'] ?? 0)),

                    'edit_url' => $id !== '' ? '/admin/editproduse?id=' . rawurlencode($id) : '/admin/product',

                    'badge_after' => $label,

                ];

            }



            return $out;

        } catch (Throwable) {

            return [];

        }

    }



    /** @return array<string, mixed> */

    private function actionError(string $message): array

    {

        return [

            'source' => 'action_error',

            'reply_ro' => $message,

            'intent' => 'explain',

            'cheat_sheet' => [

                'kind' => 'action_error',

                'title' => 'Actiune esuata',

                'subtitle' => $message,

                'updated_at' => date('d.m.Y H:i'),

                'hints' => [$message],

                'shortcuts' => [

                    ['label' => 'Lista produse', 'url' => '/admin/product'],

                ],

            ],

            'suggestions' => [],

            'next_steps' => [],

        ];

    }



    /** @return array<string, array<string, string>> */

    private function badgeConfig(): array

    {

        $path = $this->projectRoot . '/config/product-badges.php';

        if (!is_file($path)) {

            return [];

        }

        $config = require $path;



        return is_array($config) ? $config : [];

    }



    private function formatRon(float $price): string

    {

        if ($price <= 0) {

            return '-';

        }



        return number_format($price, 2, ',', '.') . ' RON';

    }

}



<?php

declare(strict_types=1);

namespace Besoiu\Services;

use Besoiu\Core\Comunicare\ShopChatKnowledgeModel;
use Besoiu\Exceptions\NotFoundException;
use Besoiu\Exceptions\ValidationException;

/**
 * Baza cunoștințe chat magazin — Q&A, butoane, reguli RAG pentru decoder AI.
 */
final class ShopChatKnowledgeService
{
    public function __construct(
        private readonly ShopChatKnowledgeModel $model = new ShopChatKnowledgeModel(),
    ) {
    }

    public static function create(): self
    {
        return new self();
    }

    /** @param array<string, mixed> $filters @return list<array<string, mixed>> */
    public function list(array $filters = []): array
    {
        return array_map([$this, 'normalizeRow'], $this->model->findAll($filters));
    }

    /** @return array<string, int> */
    public function stats(): array
    {
        return $this->model->stats();
    }

    /** @param array<string, mixed> $payload */
    public function createEntry(array $payload): array
    {
        $title = trim((string) ($payload['title'] ?? ''));
        if ($title === '') {
            throw new ValidationException('Titlul este obligatoriu.');
        }

        $randomnId = bin2hex(random_bytes(8));
        $row = [
            'randomn_id' => $randomnId,
            'entry_type' => $this->normalizeType((string) ($payload['entry_type'] ?? 'qa_pair')),
            'channel' => $this->normalizeChannel((string) ($payload['channel'] ?? 'both')),
            'title' => $title,
            'question_examples' => $this->normalizeStringList($payload['question_examples'] ?? []),
            'expected_meaning' => trim((string) ($payload['expected_meaning'] ?? '')),
            'expected_action' => trim((string) ($payload['expected_action'] ?? '')),
            'expected_intent' => trim((string) ($payload['expected_intent'] ?? '')),
            'expected_response' => trim((string) ($payload['expected_response'] ?? '')),
            'metadata_json' => is_array($payload['metadata_json'] ?? null) ? $payload['metadata_json'] : [],
            'tags_json' => $this->normalizeStringList($payload['tags_json'] ?? $payload['tags'] ?? []),
            'priority' => max(0, min(100, (int) ($payload['priority'] ?? 50))),
            'status' => 1,
        ];

        if (!$this->model->insert($row)) {
            throw new ValidationException('Nu s-a putut salva intrarea.');
        }

        return $this->normalizeRow($this->model->findByRandomId($randomnId) ?? $row);
    }

    /** @param array<string, mixed> $payload */
    public function update(string $randomnId, array $payload): array
    {
        if ($this->model->findByRandomId($randomnId) === null) {
            throw new NotFoundException('Intrare inexistentă.');
        }

        $data = [];
        foreach (['title', 'expected_meaning', 'expected_action', 'expected_intent', 'expected_response'] as $key) {
            if (array_key_exists($key, $payload)) {
                $data[$key] = trim((string) $payload[$key]);
            }
        }
        if (array_key_exists('entry_type', $payload)) {
            $data['entry_type'] = $this->normalizeType((string) $payload['entry_type']);
        }
        if (array_key_exists('channel', $payload)) {
            $data['channel'] = $this->normalizeChannel((string) $payload['channel']);
        }
        if (array_key_exists('question_examples', $payload)) {
            $data['question_examples'] = $this->normalizeStringList($payload['question_examples']);
        }
        if (array_key_exists('metadata_json', $payload)) {
            $data['metadata_json'] = is_array($payload['metadata_json']) ? $payload['metadata_json'] : [];
        }
        if (array_key_exists('tags_json', $payload) || array_key_exists('tags', $payload)) {
            $data['tags_json'] = $this->normalizeStringList($payload['tags_json'] ?? $payload['tags'] ?? []);
        }
        if (array_key_exists('priority', $payload)) {
            $data['priority'] = max(0, min(100, (int) $payload['priority']));
        }

        if ($data !== [] && !$this->model->updateByRandomId($randomnId, $data)) {
            throw new ValidationException('Nu s-a putut actualiza intrarea.');
        }

        return $this->normalizeRow($this->model->findByRandomId($randomnId) ?? []);
    }

    public function delete(string $randomnId): void
    {
        if ($this->model->findByRandomId($randomnId) === null) {
            throw new NotFoundException('Intrare inexistentă.');
        }
        $this->model->softDelete($randomnId);
    }

    /**
     * Salvează annotare din Marker DOM (admin / site public).
     *
     * @param array<string, mixed> $payload
     */
    public function createFromDomMarker(array $payload): array
    {
        $whatIs = trim((string) ($payload['what_is'] ?? ''));
        $whatDoes = trim((string) ($payload['what_does'] ?? ''));
        $why = trim((string) ($payload['why'] ?? ''));
        $result = trim((string) ($payload['expected_result'] ?? ''));
        $dom = is_array($payload['dom'] ?? null) ? $payload['dom'] : [];

        if ($whatIs === '' && $whatDoes === '' && $result === '') {
            throw new ValidationException('Completează cel puțin „Ce este?” sau „Ce face?” / rezultat.');
        }

        $label = trim((string) ($dom['label'] ?? ''));
        $title = $whatIs !== '' ? $whatIs : ($label !== '' ? $label : 'Element DOM');

        $meaningParts = array_filter([
            $whatIs !== '' ? 'Element: ' . $whatIs : '',
            $whatDoes !== '' ? 'Face: ' . $whatDoes : '',
            $why !== '' ? 'Scop: ' . $why : '',
        ]);
        $meaning = implode(' · ', $meaningParts);

        $examples = $this->normalizeStringList($payload['question_examples'] ?? []);
        if ($examples === [] && $label !== '') {
            $examples = [$label];
        }

        $tags = ['dom_marker', (string) ($dom['tag'] ?? 'element')];
        if (!empty($dom['scope'])) {
            $tags[] = (string) $dom['scope'];
        }

        $metadata = array_merge($dom, [
            'source' => 'dom_marker',
            'annotated_at' => date('c'),
        ]);

        $scope = trim((string) ($dom['scope'] ?? ''));
        $channelRaw = trim((string) ($payload['channel'] ?? ''));
        if ($channelRaw === '') {
            $channelRaw = $scope === 'admin' ? 'internal' : 'external';
        }

        return $this->createEntry([
            'entry_type' => 'button_doc',
            'channel' => $this->normalizeChannel($channelRaw),
            'title' => $title,
            'question_examples' => $examples,
            'expected_meaning' => $meaning,
            'expected_action' => trim((string) ($payload['expected_action'] ?? '')),
            'expected_intent' => trim((string) ($payload['expected_intent'] ?? 'general')),
            'expected_response' => $result,
            'metadata_json' => $metadata,
            'tags_json' => $tags,
            'priority' => max(55, min(95, (int) ($payload['priority'] ?? 65))),
        ]);
    }

    /**
     * Caută documentație marker DOM pentru un element — mod informativ operator.
     *
     * @param array<string, mixed> $dom
     * @return array{found: bool, match_score: int, instruction: array<string, mixed>, entry: array<string, mixed>|null}
     */
    public function lookupByDomMeta(array $dom): array
    {
        if (!ShopChatKnowledgeModel::tableExists()) {
            return [
                'found' => false,
                'match_score' => 0,
                'instruction' => $this->buildEmptyDomInstruction($dom),
                'entry' => null,
            ];
        }

        $scope = trim((string) ($dom['scope'] ?? 'admin'));

        $entries = $this->model->findAll([
            'entry_type' => 'button_doc',
            'channel' => 'all',
        ]);

        $best = null;
        $bestScore = 0;
        foreach ($entries as $row) {
            $meta = $this->decodeJsonObject($row['metadata_json'] ?? null);
            if (!$this->isDomMarkerLookupCandidate($row, $meta)) {
                continue;
            }
            $score = $this->scoreDomMetaMatch($dom, $meta, $row, $scope);
            if ($score > $bestScore) {
                $bestScore = $score;
                $best = $row;
            }
        }

        if ($best === null || !$this->isDomMarkerMatchAccepted($dom, $best, $bestScore)) {
            return [
                'found' => false,
                'match_score' => $bestScore,
                'instruction' => $this->buildEmptyDomInstruction($dom),
                'entry' => null,
            ];
        }

        return [
            'found' => true,
            'match_score' => $bestScore,
            'instruction' => $this->formatDomInstruction($best, $dom),
            'entry' => $this->normalizeRow($best),
        ];
    }

    /**
     * Fragment RAG pentru prompt decoder — potrivire keyword pe mesaj.
     */
    public function buildRagSnippetForMessage(string $message, string $channel = 'external', int $maxEntries = 8): string
    {
        if (!ShopChatKnowledgeModel::tableExists()) {
            return '';
        }

        $messageNorm = $this->normalizeText($message);
        if ($messageNorm === '') {
            return '';
        }

        $entries = $this->model->findActiveForRag($channel, 120);
        if ($entries === []) {
            return '';
        }

        $scored = [];
        foreach ($entries as $row) {
            $score = $this->scoreEntryForMessage($row, $messageNorm);
            if ($score > 0) {
                $scored[] = ['score' => $score, 'row' => $row];
            }
        }

        usort($scored, static fn ($a, $b) => $b['score'] <=> $a['score']);
        $picked = array_slice($scored, 0, $maxEntries);
        if ($picked === []) {
            // Fallback: reguli sistem cu prioritate mare
            foreach ($entries as $row) {
                if ((string) ($row['entry_type'] ?? '') === 'system_rule' && (int) ($row['priority'] ?? 0) >= 70) {
                    $picked[] = ['score' => 1, 'row' => $row];
                }
            }
            $picked = array_slice($picked, 0, 3);
        }

        if ($picked === []) {
            return '';
        }

        $lines = ["\n--- Cunoștințe magazin (RAG admin) ---"];
        foreach ($picked as $item) {
            $lines[] = $this->formatEntryForPrompt($item['row']);
        }

        return implode("\n", $lines);
    }

    /** @return array{preview:string, entries:list<array<string,mixed>>, char_count:int} */
    public function exportRagPreview(string $channel = 'both'): array
    {
        $filters = [];
        if ($channel !== 'all' && $channel !== 'both') {
            $filters['channel'] = $channel;
        }

        $entries = $this->list($filters);
        $lines = ["# Baza cunoștințe chat Besoiu — export RAG", 'Canal: ' . $channel, 'Total: ' . count($entries), ''];

        foreach ($entries as $entry) {
            $lines[] = $this->formatEntryForPrompt($entry);
            $lines[] = '';
        }

        $preview = implode("\n", $lines);

        return [
            'preview' => $preview,
            'entries' => $entries,
            'char_count' => mb_strlen($preview),
        ];
    }

    /**
     * Test dry-run: decode reguli + AI + potriviri RAG.
     *
     * @return array<string, mixed>
     */
    public function testMessage(string $message, string $channel = 'external'): array
    {
        $message = trim($message);
        if ($message === '') {
            throw new ValidationException('Mesajul de test este gol.');
        }

        $decoder = ShopChatMessageDecoderService::create();
        $rulesPlan = $decoder->decodeWithRules($message);
        $aiPlan = null;
        try {
            $aiPlan = $decoder->decodeWithAi($message, [], []);
        } catch (\Throwable) {
            $aiPlan = null;
        }

        $ragSnippet = $this->buildRagSnippetForMessage($message, $channel);
        $matches = $this->findMatchingEntries($message, $channel, 12);

        return [
            'message' => $message,
            'rules_plan' => $rulesPlan,
            'ai_plan' => $aiPlan,
            'verified_preamble' => $decoder->buildVerifiedPreamble($aiPlan ?? $rulesPlan, $message),
            'rag_matches' => $matches,
            'rag_snippet' => $ragSnippet,
            'rag_char_count' => mb_strlen($ragSnippet),
        ];
    }

    /** @return list<array<string, mixed>> */
    public function systemCatalog(): array
    {
        return [
            [
                'key' => 'widget_init',
                'title' => 'Deschidere chat (init)',
                'channel' => 'external',
                'description' => 'La prima deschidere: salut scurt fără chip-uri tehnice. Revenit: salut cu prenume dacă e cunoscut.',
                'api' => 'POST robot/chat_widget_api.php action=init',
                'returns' => 'greeting, chips[], session_key, visitor_key',
            ],
            [
                'key' => 'widget_message',
                'title' => 'Mesaj liber client',
                'channel' => 'external',
                'description' => 'Orice text → decode AI/reguli → căutare catalog sau quiz comandă.',
                'api' => 'POST robot/chat_widget_api.php action=message',
                'returns' => 'reply, products[], chips[], intent_decoded, price_tiers[]',
            ],
            [
                'key' => 'chip_search',
                'title' => 'Chip-uri sugestie',
                'channel' => 'external',
                'description' => 'Butoane rapide după răspuns (ex: „Mai ieftin”, „Alt brand”). Trimit mesaj ca user.',
                'api' => 'La click → același flux message',
                'returns' => 'Rafinare listă sau căutare nouă',
            ],
            [
                'key' => 'product_card',
                'title' => 'Card produs în chat',
                'channel' => 'external',
                'description' => 'Afișează imagine, preț, stoc. Butoane: Detalii, Comandă, WhatsApp.',
                'api' => 'products[] din răspuns message',
                'returns' => 'Link product.php?id=randomn_id',
            ],
            [
                'key' => 'price_tiers',
                'title' => '3 variante preț',
                'channel' => 'external',
                'description' => 'Economic/Mediu/Premium — doar dacă există ≥2 produse distincte. Altfel ascuns.',
                'api' => 'price_tiers[] în răspuns',
                'returns' => 'Carduri tier cu preț diferit',
            ],
            [
                'key' => 'vehicle_set',
                'title' => 'Setare vehicul',
                'channel' => 'external',
                'description' => 'Marcă/model din mesaj sau flow dedicat. Filtrează compatibilitate text.',
                'api' => 'ShopChatVehicleService + sesiune',
                'returns' => 'vehicle.label în context decode',
            ],
            [
                'key' => 'checkout_quiz',
                'title' => 'Quiz comandă',
                'channel' => 'external',
                'description' => '„Vreau să comand” → confirm produs → livrare → plată → contact → plasare.',
                'api' => 'ShopChatCheckoutQuizService',
                'returns' => 'Pași quiz + formular comandă admin',
            ],
            [
                'key' => 'refine_list',
                'title' => 'Rafinare listă',
                'channel' => 'external',
                'description' => '„sub 100”, „Bosch”, „al 2-lea” pe produse deja afișate.',
                'api' => 'action refine în plan decode',
                'returns' => 'Listă filtrată, nu căutare nouă',
            ],
            [
                'key' => 'verified_reply',
                'title' => 'Răspuns verificat',
                'channel' => 'external',
                'description' => 'Prefix „Am înțeles: «…»” înainte de listă produse.',
                'api' => 'buildVerifiedPreamble()',
                'returns' => 'user_meaning din plan decode',
            ],
            [
                'key' => 'internal_inbox',
                'title' => 'Mesagerie admin',
                'channel' => 'internal',
                'description' => 'Inbox unificat WhatsApp/site — răspunsuri cu template-uri.',
                'api' => '/admin/messages + reply-templates',
                'returns' => 'Conversații operator',
            ],
        ];
    }

    /**
     * Încarcă catalog complet UI + 100 exemple extinse.
     *
     * @return array{created:int,updated:int,skipped:int,total:int}
     */
    public function seedFullSystem(bool $updateExisting = true): array
    {
        return $this->upsertSeedBatch(array_merge(
            ShopChatKnowledgeFullSeed::entries(),
            ShopChatKnowledgeExtendedSeed::entries()
        ), $updateExisting);
    }

    /**
     * Doar cele ~100 exemple extinse (intern + extern).
     *
     * @return array{created:int,updated:int,skipped:int,total:int}
     */
    public function seedExtended100(bool $updateExisting = true): array
    {
        return $this->upsertSeedBatch(ShopChatKnowledgeExtendedSeed::entries(), $updateExisting);
    }

    /**
     * Instalează o singură regulă creier (1–20) în shop_chat_knowledge.
     *
     * @return array{created:int,updated:int,skipped:int,total:int,rule_id:int,seed_key:string}
     */
    public function seedBrainRule(int $ruleId, bool $updateExisting = true): array
    {
        $entry = ShopChatKnowledgeBrainRulesSeed::entryForId($ruleId);
        if ($entry === null) {
            throw new ValidationException('Regulă creier invalidă (1–20).');
        }

        $meta = is_array($entry['metadata_json'] ?? null) ? $entry['metadata_json'] : [];
        $result = $this->upsertSeedBatch([$entry], $updateExisting);

        return array_merge($result, [
            'rule_id' => $ruleId,
            'seed_key' => (string) ($meta['seed_key'] ?? ''),
        ]);
    }

    /** @return array{created:int,updated:int,skipped:int,total:int} */
    public function seedAllBrainRules(bool $updateExisting = true): array
    {
        return $this->upsertSeedBatch(ShopChatKnowledgeBrainRulesSeed::entries(), $updateExisting);
    }

    /**
     * Status instalare reguli creier + următoarea de instalat.
     *
     * @return array{
     *   rules:list<array<string,mixed>>,
     *   installed:int,
     *   total:int,
     *   next_id:int|null,
     *   complete:bool
     * }
     */
    public function brainRulesStatus(): array
    {
        if (!ShopChatKnowledgeModel::tableExists()) {
            return ['rules' => [], 'installed' => 0, 'total' => 20, 'next_id' => 1, 'complete' => false];
        }

        $rules = [];
        $installed = 0;
        $nextId = null;

        foreach (AiCentralBrainRules::all() as $def) {
            $id = (int) ($def['id'] ?? 0);
            $meta = ShopChatKnowledgeBrainRulesSeed::ruleMeta($id);
            $seedKey = (string) ($meta['seed_key'] ?? '');
            $row = $seedKey !== '' ? $this->model->findBySeedKey($seedKey) : null;
            $isInstalled = $row !== null;
            if ($isInstalled) {
                ++$installed;
            } elseif ($nextId === null) {
                $nextId = $id;
            }

            $rules[] = [
                'id' => $id,
                'icon' => (string) ($def['icon'] ?? ''),
                'title' => (string) ($def['title'] ?? ''),
                'rule' => (string) ($def['rule'] ?? ''),
                'zone' => (string) ($def['zone'] ?? ''),
                'priority' => (string) ($def['priority'] ?? ''),
                'seed_key' => $seedKey,
                'test_message' => (string) ($meta['test_message'] ?? ''),
                'installed' => $isInstalled,
                'randomn_id' => $row['randomn_id'] ?? null,
            ];
        }

        return [
            'rules' => $rules,
            'installed' => $installed,
            'total' => count($rules),
            'next_id' => $nextId,
            'complete' => $installed >= count($rules) && count($rules) > 0,
        ];
    }

    /**
     * Verifică o regulă instalată: există în BD + potrivire RAG la mesaj test.
     *
     * @return array<string, mixed>
     */
    public function verifyBrainRule(int $ruleId): array
    {
        $meta = ShopChatKnowledgeBrainRulesSeed::ruleMeta($ruleId);
        if ($meta === null) {
            throw new ValidationException('Regulă creier invalidă (1–20).');
        }

        $row = $this->model->findBySeedKey((string) $meta['seed_key']);
        $testMessage = (string) $meta['test_message'];
        $matches = $this->findMatchingEntries($testMessage, 'both', 15);
        $matchedSelf = null;
        foreach ($matches as $m) {
            $mMeta = is_array($m['metadata_json'] ?? null)
                ? $m['metadata_json']
                : (is_string($m['metadata_json'] ?? null) ? json_decode((string) $m['metadata_json'], true) : []);
            if (!is_array($mMeta)) {
                $mMeta = [];
            }
            if ((int) ($mMeta['brain_rule_id'] ?? 0) === $ruleId) {
                $matchedSelf = $m;
                break;
            }
            if ((string) ($m['title'] ?? '') !== '' && str_contains((string) ($m['title'] ?? ''), (string) $meta['title'])) {
                $matchedSelf = $m;
                break;
            }
        }

        return [
            'rule_id' => $ruleId,
            'title' => (string) $meta['title'],
            'seed_key' => (string) $meta['seed_key'],
            'installed' => $row !== null,
            'randomn_id' => $row['randomn_id'] ?? null,
            'test_message' => $testMessage,
            'rag_match' => $matchedSelf !== null,
            'match_score' => $matchedSelf['match_score'] ?? null,
            'match_title' => $matchedSelf['title'] ?? null,
            'ok' => $row !== null && $matchedSelf !== null,
        ];
    }

    /**
     * @param list<array<string, mixed>> $seeds
     * @return array{created:int,updated:int,skipped:int,total:int}
     */
    private function upsertSeedBatch(array $seeds, bool $updateExisting = true): array
    {
        if (!ShopChatKnowledgeModel::tableExists()) {
            return ['created' => 0, 'updated' => 0, 'skipped' => 0, 'total' => 0];
        }

        $created = 0;
        $updated = 0;
        $skipped = 0;

        foreach ($seeds as $seed) {
            $meta = is_array($seed['metadata_json'] ?? null) ? $seed['metadata_json'] : [];
            $seedKey = (string) ($meta['seed_key'] ?? '');
            $existing = $seedKey !== '' ? $this->model->findBySeedKey($seedKey) : null;

            if ($existing !== null) {
                if (!$updateExisting) {
                    ++$skipped;
                    continue;
                }
                $rid = (string) ($existing['randomn_id'] ?? '');
                if ($rid === '') {
                    ++$skipped;
                    continue;
                }
                $this->update($rid, $seed);
                ++$updated;
                continue;
            }

            try {
                $this->createEntry($seed);
                ++$created;
            } catch (\Throwable) {
                ++$skipped;
            }
        }

        return [
            'created' => $created,
            'updated' => $updated,
            'skipped' => $skipped,
            'total' => $this->model->countActive(),
        ];
    }

    /** Inserează exemple implicite dacă tabela e goală. */
    public function seedDefaultsIfEmpty(): int
    {
        if (!ShopChatKnowledgeModel::tableExists() || $this->model->countActive() > 0) {
            return 0;
        }

        $seeds = [
            [
                'entry_type' => 'qa_pair',
                'channel' => 'external',
                'title' => 'Salut / deschidere',
                'question_examples' => ['salut', 'bună', 'hello', 'ce faci'],
                'expected_meaning' => 'Client salută — răspuns prietenos, fără presiune.',
                'expected_action' => 'greeting',
                'expected_intent' => 'general',
                'expected_response' => 'Salut! 👋 Cu ce te pot ajuta? Spune-mi ce piesă cauți sau codul OEM.',
                'priority' => 90,
            ],
            [
                'entry_type' => 'qa_pair',
                'channel' => 'external',
                'title' => 'Căutare ulei motor',
                'question_examples' => ['aveți ulei', 'ulei motor', 'ulei 5w30', 'dar aveti ulei'],
                'expected_meaning' => 'Client caută ulei motor — căutare nouă în catalog.',
                'expected_action' => 'search',
                'expected_intent' => 'oil',
                'expected_response' => 'Am înțeles: ulei motor. Listă produse ulei/lubrifiant (exclude combustibil).',
                'tags_json' => ['ulei', 'motor', 'filtru'],
                'priority' => 85,
            ],
            [
                'entry_type' => 'qa_pair',
                'channel' => 'external',
                'title' => 'Produse frână',
                'question_examples' => ['produse frana', 'discuri frana', 'placute frana', 'ceva de frana golf bosch'],
                'expected_meaning' => 'Client caută piese frână — disc, plăcuțe, tambur.',
                'expected_action' => 'search',
                'expected_intent' => 'brake',
                'expected_response' => 'Caut disc frână, plăcuțe, tambur. Opțional filtru brand/vehicul/preț.',
                'priority' => 85,
            ],
            [
                'entry_type' => 'qa_pair',
                'channel' => 'external',
                'title' => 'Cod OEM / articol',
                'question_examples' => ['30204A', 'cod 34116761244', 'aveți 5042520'],
                'expected_meaning' => 'Client dă cod OEM sau articol — căutare exactă.',
                'expected_action' => 'search',
                'expected_intent' => 'code',
                'expected_response' => 'Caut codul în stoc. Dacă există → card produs; altfel sugerez alternative.',
                'priority' => 88,
            ],
            [
                'entry_type' => 'qa_pair',
                'channel' => 'external',
                'title' => 'Vreau să comand',
                'question_examples' => ['vreau sa comand', 'plasez comanda', 'cum comand', 'il iau'],
                'expected_meaning' => 'Client vrea checkout — pornește quiz comandă.',
                'expected_action' => 'order',
                'expected_intent' => 'general',
                'expected_response' => 'Quiz: confirm produs → livrare → plată → date contact → trimite comandă.',
                'priority' => 92,
            ],
            [
                'entry_type' => 'qa_pair',
                'channel' => 'external',
                'title' => 'Rafinare preț / brand',
                'question_examples' => ['mai ieftin', 'sub 100', 'doar bosch', 'al doilea'],
                'expected_meaning' => 'Rafinare pe lista curentă — nu căutare nouă.',
                'expected_action' => 'refine',
                'expected_intent' => 'refine',
                'expected_response' => 'Filtrez produsele afișate după preț, brand sau poziție.',
                'priority' => 80,
            ],
            [
                'entry_type' => 'button_doc',
                'channel' => 'external',
                'title' => 'Buton Comandă pe card',
                'question_examples' => [],
                'expected_meaning' => 'User apasă Comandă pe un produs.',
                'expected_action' => 'order',
                'expected_intent' => 'general',
                'expected_response' => 'Deschide quiz cu produsul selectat pre-completat.',
                'metadata_json' => ['button' => 'Comandă', 'element' => 'product_card', 'triggers' => 'checkout_quiz'],
                'priority' => 75,
            ],
            [
                'entry_type' => 'button_doc',
                'channel' => 'external',
                'title' => 'Buton WhatsApp',
                'question_examples' => [],
                'expected_meaning' => 'Redirect conversație uman operator.',
                'expected_action' => 'whatsapp',
                'expected_intent' => 'general',
                'expected_response' => 'Deschide WhatsApp cu mesaj pre-completat despre produs.',
                'metadata_json' => ['button' => 'WhatsApp', 'element' => 'product_card'],
                'priority' => 70,
            ],
            [
                'entry_type' => 'system_rule',
                'channel' => 'both',
                'title' => 'Regulă: motor ≠ frână',
                'question_examples' => ['motor', 'piese motor'],
                'expected_meaning' => '„Motor” = ulei, filtru ulei, piston — NU frână/roți.',
                'expected_action' => 'search',
                'expected_intent' => 'engine',
                'expected_response' => 'Exclude combustibil și piese roată când intent=engine.',
                'priority' => 95,
            ],
            [
                'entry_type' => 'system_rule',
                'channel' => 'both',
                'title' => 'Regulă: tier-uri preț',
                'question_examples' => [],
                'expected_meaning' => 'Afișează Economic/Mediu/Premium doar cu ≥2 produse distincte.',
                'expected_action' => 'search',
                'expected_intent' => 'general',
                'expected_response' => 'Ascunde tier-uri dacă același produs repetat.',
                'priority' => 88,
            ],
        ];

        $count = 0;
        foreach ($seeds as $seed) {
            try {
                $this->createEntry($seed);
                ++$count;
            } catch (\Throwable) {
                // skip duplicate/errors
            }
        }

        return $count;
    }

    /** @param array<string, mixed> $row */
    private function scoreEntryForMessage(array $row, string $messageNorm): int
    {
        $score = (int) ($row['priority'] ?? 0) / 10;

        $examples = $this->decodeJsonList($row['question_examples'] ?? null);
        foreach ($examples as $ex) {
            $exNorm = $this->normalizeText($ex);
            if ($exNorm === '') {
                continue;
            }
            if ($messageNorm === $exNorm) {
                $score += 100;
            } elseif (str_contains($messageNorm, $exNorm) || str_contains($exNorm, $messageNorm)) {
                $score += 40;
            } else {
                $overlap = $this->tokenOverlap($messageNorm, $exNorm);
                $score += (int) ($overlap * 25);
            }
        }

        foreach ($this->decodeJsonList($row['tags_json'] ?? null) as $tag) {
            $tagNorm = $this->normalizeText($tag);
            if ($tagNorm !== '' && str_contains($messageNorm, $tagNorm)) {
                $score += 15;
            }
        }

        $meta = $this->decodeJsonObject($row['metadata_json'] ?? null);
        if (($meta['source'] ?? '') === 'dom_marker') {
            $score += 5;
            foreach (['label', 'text'] as $mk) {
                $mv = $this->normalizeText((string) ($meta[$mk] ?? ''));
                if ($mv !== '' && str_contains($messageNorm, $mv)) {
                    $score += 35;
                }
            }
        }

        $meaning = $this->normalizeText((string) ($row['expected_meaning'] ?? ''));
        if ($meaning !== '') {
            $score += (int) ($this->tokenOverlap($messageNorm, $meaning) * 20);
        }

        return (int) $score;
    }

    /** @return list<array<string, mixed>> */
    private function findMatchingEntries(string $message, string $channel, int $limit): array
    {
        $messageNorm = $this->normalizeText($message);
        $entries = $this->model->findActiveForRag($channel, 120);
        $scored = [];
        foreach ($entries as $row) {
            $score = $this->scoreEntryForMessage($row, $messageNorm);
            if ($score > 5) {
                $scored[] = ['score' => $score, 'entry' => $this->normalizeRow($row)];
            }
        }
        usort($scored, static fn ($a, $b) => $b['score'] <=> $a['score']);

        return array_slice(array_map(static fn ($x) => array_merge($x['entry'], ['match_score' => $x['score']]), $scored), 0, $limit);
    }

    /** @param array<string, mixed> $row */
    private function formatEntryForPrompt(array $row): string
    {
        $type = (string) ($row['entry_type'] ?? 'qa_pair');
        $title = (string) ($row['title'] ?? '');
        $parts = ['[' . $type . '] ' . $title];

        $examples = $row['question_examples'] ?? [];
        if (is_string($examples)) {
            $examples = $this->decodeJsonList($examples);
        }
        if (is_array($examples) && $examples !== []) {
            $parts[] = 'Exemple: ' . implode(' | ', array_slice($examples, 0, 5));
        }

        if (!empty($row['expected_meaning'])) {
            $parts[] = 'Sens: ' . (string) $row['expected_meaning'];
        }
        if (!empty($row['expected_action'])) {
            $parts[] = 'Acțiune: ' . (string) $row['expected_action'];
        }
        if (!empty($row['expected_intent'])) {
            $parts[] = 'Intent: ' . (string) $row['expected_intent'];
        }
        if (!empty($row['expected_response'])) {
            $parts[] = 'Răspuns așteptat: ' . (string) $row['expected_response'];
        }

        return implode("\n", $parts);
    }

    /** @param array<string, mixed> $row @return array<string, mixed> */
    private function normalizeRow(array $row): array
    {
        if ($row === []) {
            return [];
        }

        $row['question_examples'] = $this->decodeJsonList($row['question_examples'] ?? null);
        $row['metadata_json'] = $this->decodeJsonObject($row['metadata_json'] ?? null);
        $row['tags_json'] = $this->decodeJsonList($row['tags_json'] ?? null);
        $row['tags'] = $row['tags_json'];

        return $row;
    }

    /** @param array<string, mixed> $row @param array<string, mixed> $meta */
    private function isDomMarkerLookupCandidate(array $row, array $meta): bool
    {
        $tags = $this->decodeJsonList($row['tags_json'] ?? null);
        if (in_array('dom_marker', $tags, true)) {
            return true;
        }
        if (($meta['source'] ?? '') === 'dom_marker') {
            return true;
        }

        $seedKey = (string) ($meta['seed_key'] ?? '');
        if (($meta['source'] ?? '') === 'extended_seed_v1' || str_starts_with($seedKey, 'int_btn_')) {
            return !empty($meta['selector']) || !empty($meta['url']);
        }

        return !empty($meta['selector']) || !empty($meta['id']);
    }

    /** @param array<string, mixed> $dom @param array<string, mixed> $row */
    private function isDomMarkerMatchAccepted(array $dom, array $row, int $score): bool
    {
        if ($score < 40) {
            return false;
        }

        $meta = $this->decodeJsonObject($row['metadata_json'] ?? null);
        $elementScore = $this->scoreDomElementSignals($dom, $meta, $row);

        return $elementScore >= 35;
    }

    /** @param array<string, mixed> $dom @param array<string, mixed> $meta @param array<string, mixed> $row */
    private function scoreDomElementSignals(array $dom, array $meta, array $row): int
    {
        $score = 0;

        $selDom = trim((string) ($dom['selector'] ?? ''));
        $selMeta = trim((string) ($meta['selector'] ?? ''));
        if ($selDom !== '' && $selMeta !== '' && $selDom === $selMeta) {
            $score += 120;
        } elseif ($selDom !== '' && $selMeta !== '' && (str_contains($selDom, $selMeta) || str_contains($selMeta, $selDom))) {
            $score += 55;
        }

        $idDom = trim((string) ($dom['id'] ?? ''));
        $idMeta = trim((string) ($meta['id'] ?? ''));
        if ($idDom !== '' && $idMeta !== '' && $idDom === $idMeta) {
            $score += 90;
        }

        $onclickDom = trim((string) ($dom['onclick'] ?? ($dom['attrs']['onclick'] ?? '')));
        $onclickMeta = trim((string) ($meta['onclick'] ?? ($meta['attrs']['onclick'] ?? '')));
        if ($onclickDom !== '' && $onclickMeta !== '' && $onclickDom === $onclickMeta) {
            $score += 85;
        }

        $helpDom = trim((string) ($dom['data_bpa_help'] ?? ($dom['attrs']['data-bpa-help'] ?? '')));
        $helpMeta = trim((string) ($meta['data_bpa_help'] ?? ($meta['attrs']['data-bpa-help'] ?? '')));
        if ($helpDom !== '' && $helpMeta !== '' && $this->normalizeText($helpDom) === $this->normalizeText($helpMeta)) {
            $score += 95;
        }

        $labelDom = $this->normalizeText((string) ($dom['label'] ?? ''));
        $labelMeta = $this->normalizeText((string) ($meta['label'] ?? ''));
        if ($labelDom !== '' && $labelMeta !== '' && $labelDom === $labelMeta) {
            $score += 45;
        }

        $textDom = $this->normalizeText((string) ($dom['text'] ?? ''));
        $textMeta = $this->normalizeText((string) ($meta['text'] ?? ''));
        if ($textDom !== '' && $textMeta !== '' && $textDom === $textMeta) {
            $score += 40;
        }

        $titleNorm = $this->normalizeText((string) ($row['title'] ?? ''));
        if ($labelDom !== '' && $titleNorm !== '' && $labelDom === $titleNorm) {
            $score += 35;
        }

        foreach ($this->decodeJsonList($row['question_examples'] ?? null) as $ex) {
            if ($this->exampleMatchesDomElement((string) $ex, $dom)) {
                $score += 40;
                break;
            }
        }

        return $score;
    }

    /** @param array<string, mixed> $dom */
    private function exampleMatchesDomElement(string $example, array $dom): bool
    {
        $exNorm = $this->normalizeText($example);
        if ($exNorm === '') {
            return false;
        }

        // Cuvinte izolate din seed (ex: „categorii”, „vitrina”) — prea generice
        if (!str_contains($exNorm, ' ') && mb_strlen($exNorm, 'UTF-8') < 12) {
            return false;
        }

        $labelDom = $this->normalizeText((string) ($dom['label'] ?? ''));
        $textDom = $this->normalizeText((string) ($dom['text'] ?? ''));

        if ($labelDom !== '' && ($exNorm === $labelDom || str_contains($labelDom, $exNorm) || str_contains($exNorm, $labelDom))) {
            return true;
        }

        return $textDom !== '' && ($exNorm === $textDom || str_contains($textDom, $exNorm) || str_contains($exNorm, $textDom));
    }

    /** @param array<string, mixed> $dom @param array<string, mixed> $meta @param array<string, mixed> $row */
    private function scoreDomMetaMatch(array $dom, array $meta, array $row, string $scope = 'admin'): int
    {
        $elementScore = $this->scoreDomElementSignals($dom, $meta, $row);
        if ($elementScore < 20) {
            return 0;
        }

        $score = $elementScore + (int) ($row['priority'] ?? 0) / 10;

        $pageDom = $this->normalizePagePath((string) ($dom['page_url'] ?? ''));
        $pageMeta = $this->normalizePagePath((string) ($meta['page_url'] ?? ''));
        if ($pageMeta !== '' && $pageDom !== '' && $pageMeta !== $pageDom) {
            return 0;
        }
        if ($pageDom !== '' && $pageMeta !== '' && $pageMeta === $pageDom) {
            $score += 15;
        }

        $metaScope = trim((string) ($meta['scope'] ?? ''));
        if ($metaScope !== '' && $metaScope === $scope) {
            $score += 8;
        }

        $tags = $this->decodeJsonList($row['tags_json'] ?? null);
        if (in_array('dom_marker', $tags, true)) {
            $score += 5;
        }

        return (int) $score;
    }

    /** @param array<string, mixed> $dom @return array<string, mixed> */
    private function buildEmptyDomInstruction(array $dom): array
    {
        $label = trim((string) ($dom['label'] ?? 'Element UI'));
        $selector = trim((string) ($dom['selector'] ?? ''));

        $html = '<div class="bsa-dom-guide bsa-dom-guide--empty">'
            . '<p class="bsa-dom-guide__lead"><strong>' . htmlspecialchars($label, ENT_QUOTES, 'UTF-8') . '</strong></p>'
            . '<p>Nu există încă instrucțiuni de lucru pentru această zonă.</p>'
            . '<p>Administratorul poate adăuga explicații din modul Marker DOM (IT Specialist).</p>';
        $html .= '</div>';

        return [
            'title' => $label,
            'html' => $html,
            'text' => 'Nu există documentație RAG pentru: ' . $label,
            'selector' => $selector,
            'steps' => [],
        ];
    }

    /** @param array<string, mixed> $row @param array<string, mixed> $dom @return array<string, mixed> */
    private function formatDomInstruction(array $row, array $dom): array
    {
        $title = trim((string) ($row['title'] ?? 'Element UI'));
        $meaning = trim((string) ($row['expected_meaning'] ?? ''));
        $result = trim((string) ($row['expected_response'] ?? ''));
        $action = trim((string) ($row['expected_action'] ?? ''));
        $meta = $this->decodeJsonObject($row['metadata_json'] ?? null);

        $sections = $this->parseDomMeaningSections($meaning);
        $steps = [];

        $html = '<div class="bsa-dom-guide">';
        $html .= '<p class="bsa-dom-guide__lead">📋 <strong>Instrucțiune de lucru</strong> — ' . htmlspecialchars($title, ENT_QUOTES, 'UTF-8') . '</p>';
        $html .= '<dl class="bsa-dom-guide__dl">';

        if ($sections['what_is'] !== '') {
            $html .= '<dt>Ce este?</dt><dd>' . htmlspecialchars($sections['what_is'], ENT_QUOTES, 'UTF-8') . '</dd>';
            $steps[] = 'Ce este: ' . $sections['what_is'];
        }
        if ($sections['what_does'] !== '') {
            $html .= '<dt>Ce face?</dt><dd>' . htmlspecialchars($sections['what_does'], ENT_QUOTES, 'UTF-8') . '</dd>';
            $steps[] = 'Ce face: ' . $sections['what_does'];
        }
        if ($sections['why'] !== '') {
            $html .= '<dt>Pentru ce?</dt><dd>' . htmlspecialchars($sections['why'], ENT_QUOTES, 'UTF-8') . '</dd>';
            $steps[] = 'Scop: ' . $sections['why'];
        }
        if ($result !== '') {
            $html .= '<dt>Rezultat așteptat</dt><dd>' . htmlspecialchars($result, ENT_QUOTES, 'UTF-8') . '</dd>';
            $steps[] = 'Rezultat: ' . $result;
        }
        if ($action !== '') {
            $html .= '<dt>Acțiune recomandată</dt><dd>' . htmlspecialchars($action, ENT_QUOTES, 'UTF-8') . '</dd>';
        }

        $html .= '</dl></div>';

        $textParts = array_filter([$title, $sections['what_does'], $result]);
        $text = implode(' — ', $textParts);

        return [
            'title' => $title,
            'html' => $html,
            'text' => $text !== '' ? $text : $title,
            'selector' => $sel,
            'steps' => $steps,
        ];
    }

    /** @return array{what_is: string, what_does: string, why: string} */
    private function parseDomMeaningSections(string $meaning): array
    {
        $out = ['what_is' => '', 'what_does' => '', 'why' => ''];
        if ($meaning === '') {
            return $out;
        }

        foreach (preg_split('/\s·\s/u', $meaning) ?: [] as $part) {
            $part = trim($part);
            if ($part === '') {
                continue;
            }
            if (str_starts_with($part, 'Element:')) {
                $out['what_is'] = trim(substr($part, 8));
            } elseif (str_starts_with($part, 'Face:')) {
                $out['what_does'] = trim(substr($part, 5));
            } elseif (str_starts_with($part, 'Scop:')) {
                $out['why'] = trim(substr($part, 5));
            } elseif ($out['what_is'] === '') {
                $out['what_is'] = $part;
            } elseif ($out['what_does'] === '') {
                $out['what_does'] = $part;
            }
        }

        return $out;
    }

    private function normalizePagePath(string $url): string
    {
        $path = parse_url($url, PHP_URL_PATH);

        return $this->normalizeText(is_string($path) ? $path : $url);
    }

    /** @return list<string> */
    private function normalizeStringList(mixed $value): array
    {
        if (is_string($value)) {
            $value = preg_split('/[\n,;|]+/', $value) ?: [];
        }
        if (!is_array($value)) {
            return [];
        }

        $out = [];
        foreach ($value as $item) {
            $s = trim((string) $item);
            if ($s !== '') {
                $out[] = $s;
            }
        }

        return array_values(array_unique($out));
    }

    /** @return list<string> */
    private function decodeJsonList(mixed $raw): array
    {
        if (is_array($raw)) {
            return $this->normalizeStringList($raw);
        }
        if (!is_string($raw) || trim($raw) === '') {
            return [];
        }
        $decoded = json_decode($raw, true);

        return is_array($decoded) ? $this->normalizeStringList($decoded) : [];
    }

    /** @return array<string, mixed> */
    private function decodeJsonObject(mixed $raw): array
    {
        if (is_array($raw)) {
            return $raw;
        }
        if (!is_string($raw) || trim($raw) === '') {
            return [];
        }
        $decoded = json_decode($raw, true);

        return is_array($decoded) ? $decoded : [];
    }

    private function normalizeText(string $text): string
    {
        $text = mb_strtolower(trim($text));
        $text = preg_replace('/\s+/u', ' ', $text) ?? $text;

        return str_replace(['ă', 'â', 'î', 'ș', 'ț'], ['a', 'a', 'i', 's', 't'], $text);
    }

    private function tokenOverlap(string $a, string $b): float
    {
        $ta = array_filter(explode(' ', $a), static fn ($t) => mb_strlen($t) >= 3);
        $tb = array_filter(explode(' ', $b), static fn ($t) => mb_strlen($t) >= 3);
        if ($ta === [] || $tb === []) {
            return 0.0;
        }
        $inter = count(array_intersect($ta, $tb));

        return $inter / max(count($ta), count($tb));
    }

    private function normalizeType(string $type): string
    {
        $allowed = ['qa_pair', 'button_doc', 'system_rule', 'rag_chunk'];

        return in_array($type, $allowed, true) ? $type : 'qa_pair';
    }

    private function normalizeChannel(string $channel): string
    {
        $allowed = ['internal', 'external', 'both'];

        return in_array($channel, $allowed, true) ? $channel : 'both';
    }
}

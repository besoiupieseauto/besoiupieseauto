<?php

declare(strict_types=1);

namespace Besoiu\Services;

/**
 * Mesaje sociale / meta („salut”, „ce poți face”) — conversație naturală, fără SQL live.
 */
final class SectionAssistantConversationService
{
    public function __construct(
        private readonly string $projectRoot,
        private ?LlmRouterService $llm = null,
    ) {
        $this->llm = $llm ?? LlmRouterService::create($this->projectRoot);
    }

    public static function shouldSkipIntentRouter(string $message): bool
    {
        return self::classify($message) !== null;
    }

    /**
     * @param array<string, mixed> $catalog
     * @param array<string, mixed> $context
     * @param list<array{role:string,message:string}> $conversationHistory
     * @return array<string, mixed>|null
     */
    public function tryHandle(
        string $message,
        string $section,
        array $catalog,
        array $context,
        array $conversationHistory = [],
    ): ?array {
        $kind = self::classify($message);
        if ($kind === null) {
            return null;
        }

        $sectionLabel = (string) ($catalog['label'] ?? $section);
        $reply = $this->buildReply($kind, $message, $section, $sectionLabel, $conversationHistory);
        $source = $reply['source'];
        unset($reply['source']);

        $plan = [
            'source' => $source,
            'reply_ro' => $reply['text'],
            'intent' => 'chat',
            'model' => $reply['model'] ?? $this->llm->model(),
            'cheat_sheet' => null,
            'suggestions' => [],
            'next_steps' => $this->quickChipsForSection($section),
        ];

        if (in_array($kind, ['capabilities', 'capabilities_greeting'], true)) {
            $plan['attach_capabilities'] = true;
        }

        return $plan;
    }

    /** @return 'greeting'|'thanks'|'identity'|'capabilities'|'capabilities_greeting'|null */
    public static function classify(string $message): ?string
    {
        $message = SectionAssistantQueryHelper::normalizeComposerTypos(trim($message));
        if ($message === '') {
            return null;
        }

        $lower = mb_strtolower($message, 'UTF-8');

        if (preg_match('/^(salut|bun[aă]|hey|hello|hi|servus|noroc|ciao|buna\s+ziua)[\s!.,?]*$/u', $lower)) {
            return 'greeting';
        }

        if (preg_match('/^(multumesc|mulțumesc|mersi|ms|thank\s*you|thanks)[\s!.,?]*$/u', $lower)) {
            return 'thanks';
        }

        if (preg_match('/\b(cine\s+e[sș]ti|ce\s+e[sș]ti|who\s+are\s+you)\b/u', $lower)) {
            return 'identity';
        }

        $asksCapabilities = (bool) preg_match(
            '/\b(ce\s+poti|ce\s+poți|ce\s+faci|ce\s+stii|ce\s+știi|help|ajutor|cum\s+ma\s+poti|cum\s+mă\s+poți|cu\s+ce\s+ma\s+poti|cu\s+ce\s+mă\s+poți)\b/u',
            $lower
        );
        $hasDataIntent = (bool) preg_match(
            '/\b(produs\w*|lista\s+(de\s+)?produs|catalog(?:ul)?|client\w*|comenz\w*|factur\w*|furnizor\w*|online|live|c[aâ]te|cite|c[iî]te|inventar|badge|vitrin\w*|import\w*|fara\s+imagine|fără\s+imagine)\b/u',
            $lower
        );

        if ($asksCapabilities && !$hasDataIntent) {
            if (preg_match('/\b(salut|bun[aă]|hey|hello|hi)\b/u', $lower)) {
                return 'capabilities_greeting';
            }

            return 'capabilities';
        }

        if (preg_match('/\b(salut|bun[aă]|hey|hello|hi)\b/u', $lower)
            && $asksCapabilities
            && !$hasDataIntent) {
            return 'capabilities_greeting';
        }

        return null;
    }

    /**
     * @param list<array{role:string,message:string}> $history
     * @return array{text:string,source:string,model?:string}
     */
    private function buildReply(
        string $kind,
        string $message,
        string $section,
        string $sectionLabel,
        array $history,
    ): array {
        // Salut / capabilități / meta — răspuns instant, fără Ollama (evită blocarea sesiunii admin).
        if (in_array($kind, ['greeting', 'thanks', 'identity', 'capabilities', 'capabilities_greeting'], true)) {
            return [
                'text' => $this->staticReply($kind, $sectionLabel),
                'source' => 'conversation',
            ];
        }

        $ollama = $this->tryOllamaReply($kind, $message, $section, $sectionLabel, $history);
        if ($ollama !== null) {
            return $ollama;
        }

        return [
            'text' => $this->staticReply($kind, $sectionLabel),
            'source' => 'conversation',
        ];
    }

    /**
     * @param list<array{role:string,message:string}> $history
     * @return array{text:string,source:string,model:string}|null
     */
    private function tryOllamaReply(
        string $kind,
        string $message,
        string $section,
        string $sectionLabel,
        array $history,
    ): ?array {
        $ready = $this->llm->readiness();
        if (empty($ready['ready']) || !$this->llm->isConfigured()) {
            return null;
        }

        $system = <<<PROMPT
Ești besoiupieseauto — organismul live al magazinului Besoiu Piese Auto în panoul admin.
Vorbești natural, cald, în română (fără diacritice opțional). Ești concis: 2–4 propoziții.
Nu inventa cifre din catalog. Nu lista produse în tabele. Nu răspunde JSON.
Secțiunea curentă: {$sectionLabel} ({$section}).
La întrebări despre capabilități: explici pe scurt că poți căuta live în BD, filtra produse, vitrină, CRUD cu confirmare, navigare module.
PROMPT;

        $messages = [];
        foreach (array_slice($history, -8) as $turn) {
            $role = ($turn['role'] ?? '') === 'assistant' ? 'assistant' : 'user';
            $content = trim((string) ($turn['message'] ?? ''));
            if ($content === '') {
                continue;
            }
            $messages[] = ['role' => $role, 'content' => $content];
        }
        $messages[] = ['role' => 'user', 'content' => $message];

        $result = $this->llm->chat($messages, $system, 0.72, 12, 'section_assistant_chat');
        if (empty($result['ok'])) {
            return null;
        }

        $text = trim((string) ($result['content'] ?? ''));
        if ($text === '') {
            return null;
        }

        $text = preg_replace('/^```[\s\S]*?```/u', '', $text) ?? $text;
        $text = trim($text);

        return [
            'text' => $text,
            'source' => 'conversation+' . (string) ($result['provider'] ?? 'ollama'),
            'model' => (string) ($result['model'] ?? $this->llm->model()),
        ];
    }

    private function staticReply(string $kind, string $sectionLabel): string
    {
        return match ($kind) {
            'greeting' => 'Salut! Sunt besoiupieseauto — organismul tău live în admin. '
                . 'Spune-mi ce ai nevoie sau alege un exemplu rapid mai jos.',
            'thanks' => 'Cu plăcere! Sunt aici dacă mai ai nevoie de ceva în admin.',
            'identity' => 'Sunt besoiupieseauto, asistentul live al magazinului Besoiu Piese Auto. '
                . 'Te ajut în panoul admin: produse, filtre, vitrină, acțiuni cu confirmare — vorbești natural, eu execut sau îți arăt date live.',
            'capabilities', 'capabilities_greeting' => 'Salut! Pot vorbi natural și, când ceri explicit, interoghez live baza de date. '
                . 'Pe secțiunea ' . $sectionLabel . ' pot lista sau filtra produse, vitrină, fără imagine, inventar, '
                . 'modifica produse (cu confirmare) și te duc la modulele admin. Ce vrei să facem acum?',
            default => 'Salut! Cu ce te pot ajuta?',
        };
    }

    /** @return list<string> */
    private function quickChipsForSection(string $section): array
    {
        return match ($section) {
            'produse' => ['ce e pe vitrina', 'produse fara imagine', 'inventar produse', 'lista tuturor produselor'],
            'comenzi' => ['cate comenzi sunt', 'comenzi noi', 'cosuri abandonate'],
            'furnizori' => ['lista furnizori', 'comparare furnizori'],
            'import' => ['coada import', 'status import'],
            default => ['ce poti face aici', 'inventar', 'ajutor'],
        };
    }
}

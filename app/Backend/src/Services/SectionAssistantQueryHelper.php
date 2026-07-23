<?php

declare(strict_types=1);

namespace Besoiu\Services;

/** Utilitare comune — extragere termeni, întrebări inventar, sanitizare (toate secțiunile Composer). */
final class SectionAssistantQueryHelper
{
    public static function sanitize(string $text): string
    {
        return SectionAssistantOrdersQueries::sanitizeClientQuery(trim($text));
    }

    public static function normalizeComposerTypos(string $message): string
    {
        $replacements = [
            '/\bcomezi\b/u' => 'comenzi',
            '/\bcomentzi\b/u' => 'comenzi',
            '/\bcomenz\b/u' => 'comenzi',
            '/\bfurnizro\w*\b/u' => 'furnizor',
            '/\bmesag\w*\b/u' => 'mesaje',
            '/\bmarkting\b/u' => 'marketing',
            '/\bwhatsap\b/u' => 'whatsapp',
            '/\bclinet\w*\b/u' => 'clienti',
            '/\bciti\b/u' => 'cate',
            '/\bsitem\b/u' => 'sistem',
            '/\bacuma\b/u' => 'acum',
            '/\bcatagori\w*\b/u' => 'categorii',
        ];
        foreach ($replacements as $pattern => $replacement) {
            $message = (string) preg_replace($pattern, $replacement, $message);
        }

        return trim($message);
    }

    /** «am eu», «sunt», etc. — nu sunt nume de client. */
    public static function isBoilerplateClientName(string $name): bool
    {
        $lower = mb_strtolower(trim($name), 'UTF-8');
        if ($lower === '') {
            return true;
        }

        static $phrases = [
            'am eu', 'am', 'eu', 'noi', 'mine', 'me', 'sunt', 'suntem', 'avem', 'acum',
            'in sistem', 'din sistem', 'sistem', 'te rog', 'va rog', 'proiect', 'in proiect',
        ];
        if (in_array($lower, $phrases, true)) {
            return true;
        }

        $first = explode(' ', $lower)[0] ?? '';

        return in_array($first, ['am', 'eu', 'ce', 'care', 'cate', 'cite', 'sunt', 'avem', 'in', 'din', 'pe'], true);
    }

    /** Mesaj colocvial — merită trecut prin decoder LLM. */
    public static function hasNaturalLanguageNoise(string $message): bool
    {
        $lower = mb_strtolower($message, 'UTF-8');

        return (bool) preg_match(
            '/\b(am\s+eu|as\s+vrea|vreau\s+sa|nu\s+stiu|te\s+rog|spune|zimi|zi-mi|imi\s+poti|poti\s+sa)\b/u',
            $lower
        );
    }

    public static function hasLiveDataAsk(string $message): bool
    {
        $lower = mb_strtolower(trim($message), 'UTF-8');

        return (bool) preg_match(
            '/\b(ce|c[aâ]te|cite|c[iî]te|care|sunt|exist[aă]|avem|acum|lista|list[aă]|arata|arat[aă]|overview|rezumat|total|live|online|in\s+site|din\s+site|d[aă]mi|dami|imi\s+dai|status|necitit\w*|pending|active|activ\w*)\b/u',
            $lower
        );
    }

    /** Cerere de rânduri (listă selectabilă), nu doar cifre. */
    public static function wantsDetailedList(string $message): bool
    {
        $lower = mb_strtolower(trim($message), 'UTF-8');

        return (bool) preg_match(
            '/\b(lista|list[aă]|listez|enumera|enumăr|arata|arat[aă]|afișeaz[aă]|afiseaz[aă]|toate|tot\s+catalogul|catalog\s+complet|d[aă]mi\s+lista|dami\s+lista|lista\s+de|lista\s+cu)\b/u',
            $lower
        );
    }

    /** Doar totaluri / rezumat — fără enumerare produse. */
    public static function wantsSummaryOnly(string $message): bool
    {
        if (self::wantsDetailedList($message)) {
            return false;
        }

        $lower = mb_strtolower(trim($message), 'UTF-8');

        return (bool) preg_match(
            '/\b(c[aâ]te|cite|c[iî]te|total|rezumat|overview|statistici|num[aă]r|inventar)\b/u',
            $lower
        );
    }

    /**
     * Mod interogare produse: listă completă, filtru, rezumat sau cod.
     *
     * @return 'list'|'filter'|'summary'|'code'|null
     */
    public static function resolveProductQueryMode(string $message): ?string
    {
        $message = self::normalizeComposerTypos(trim($message));
        if ($message === '') {
            return null;
        }

        if (self::extractProductCode($message) !== null && self::wantsProductCodeLookup($message)) {
            return 'code';
        }

        $lower = mb_strtolower($message, 'UTF-8');
        if (!preg_match('/\b(produs\w*|catalog(?:ul)?|magazin|inventar)\b/u', $lower)) {
            return null;
        }

        if (preg_match('/\b(fara|fără)\s+imagine/u', $lower)
            || preg_match('/\b(filtre\w*|badge|brand\s+\w+|subcategor\w*)\b/u', $lower)) {
            if (self::wantsDetailedList($message) || preg_match('/\b(fara|fără|filtre|badge|brand|subcategor|categor\w*)\b/u', $lower)) {
                return 'filter';
            }
        }

        if (preg_match('/\b(fara|fără)\s+imagine/u', $lower)) {
            return 'filter';
        }

        if (self::wantsDetailedList($message)) {
            return 'list';
        }

        if (self::wantsSummaryOnly($message)) {
            return 'summary';
        }

        if (preg_match('/\b(doar|numai)\s+(ulei|frana|filtru|baterie|consumabil)\b/u', $lower)) {
            return 'filter';
        }

        if (self::hasLiveDataAsk($message)) {
            return 'summary';
        }

        return null;
    }

    /** Cerere de produs după cod/OEM — răspuns SQL rapid, fără LLM. */
    public static function wantsProductCodeLookup(string $message): bool
    {
        $code = self::extractProductCode($message);
        if ($code === null) {
            return false;
        }

        $trimmed = trim($message);
        if (preg_match('/^[A-Z0-9][A-Z0-9\-\.]{3,}$/iu', $trimmed)
            || preg_match('/^[0-9]{6,15}$/u', $trimmed)) {
            return true;
        }

        $lower = mb_strtolower($trimmed, 'UTF-8');

        return (bool) preg_match(
            '/\b(produs\w*|piesa\w*|oem|cod|referint\w*|caut\w*|gas\w*|găs\w*|arata|arat[aă]|d[aă]mi|imi\s+dai|nevoie|vreau|as[aă]\s+produs|la\s+fel|similar)\b/u',
            $lower
        );
    }

    /** @return list<string> */
    public static function words(string $text, int $minLen = 2): array
    {
        $text = self::sanitize($text);
        $parts = preg_split('/\s+/u', mb_strtolower($text, 'UTF-8')) ?: [];

        return array_values(array_filter(
            $parts,
            static fn ($w) => mb_strlen(trim((string) $w), 'UTF-8') >= $minLen
        ));
    }

    /** Extrage cod OEM / produs din mesaj (ex. 30204A, W712, 8710270010, 1457434437). */
    public static function extractProductCode(string $message): ?string
    {
        $message = trim($message);
        if ($message === '') {
            return null;
        }

        if (preg_match('/\b(?:cod|oem|referint[aă]|ref|sku|piesa)\s*[:\s-]*([A-Z0-9][A-Z0-9\-\.]{2,})\b/iu', $message, $m)) {
            return self::finalizeExtractedCode($m[1]);
        }
        if (preg_match('/\bprodus(?:ul)?\s+(?:cu\s+)?cod\s*[:\s-]*([A-Z0-9][A-Z0-9\-\.]{2,})\b/iu', $message, $m)) {
            return self::finalizeExtractedCode($m[1]);
        }
        if (preg_match('/\b(?:cod|oem|referint[aă]|ref)\s*[:\s]?\s*([0-9]{4,15})\b/iu', $message, $m)) {
            return self::finalizeExtractedCode($m[1]);
        }

        $upper = strtoupper($message);
        $candidates = [];

        if (preg_match_all('/\b([A-Z]{1,4}[0-9]{2,}[A-Z0-9\-]{0,8})\b/u', $upper, $ms, PREG_OFFSET_CAPTURE)) {
            foreach ($ms[1] as [$token, $offset]) {
                if (!self::isPriceContext($message, (int) $offset, $token)) {
                    $candidates[] = ['code' => $token, 'offset' => (int) $offset, 'score' => 2];
                }
            }
        }

        if (preg_match_all('/\b([0-9]{4,}[A-Z0-9]{0,6})\b/u', $upper, $ms, PREG_OFFSET_CAPTURE)) {
            foreach ($ms[1] as [$token, $offset]) {
                if (!self::isPriceContext($message, (int) $offset, $token)) {
                    $candidates[] = ['code' => $token, 'offset' => (int) $offset, 'score' => 3];
                }
            }
        }

        if (preg_match_all('/\b([0-9]{6,15})\b/u', $message, $ms, PREG_OFFSET_CAPTURE)) {
            foreach ($ms[1] as [$token, $offset]) {
                if (!self::isPriceContext($message, (int) $offset, $token)) {
                    $candidates[] = ['code' => $token, 'offset' => (int) $offset, 'score' => 1];
                }
            }
        }

        if ($candidates === []) {
            return null;
        }

        usort($candidates, static function (array $a, array $b): int {
            if ($a['score'] !== $b['score']) {
                return $b['score'] <=> $a['score'];
            }

            return $a['offset'] <=> $b['offset'];
        });

        foreach ($candidates as $candidate) {
            $code = self::finalizeExtractedCode((string) $candidate['code']);
            if ($code !== null) {
                return $code;
            }
        }

        return null;
    }

    public static function messageRefersToSelectedProduct(string $message): bool
    {
        $lower = mb_strtolower(trim($message), 'UTF-8');

        return (bool) preg_match(
            '/\b(acest(?:a)?\s+produs|produsul\s+(?:selectat|bifat|acesta)|produs\s+bifat|produs\s+selectat)\b/u',
            $lower
        );
    }

    private static function finalizeExtractedCode(string $code): ?string
    {
        $code = strtoupper(trim($code));
        $code = (string) preg_replace('/[^A-Z0-9\-\.]/', '', $code);
        if ($code === '' || mb_strlen($code, 'UTF-8') < 3) {
            return null;
        }
        if (!preg_match('/\d/u', $code)) {
            return null;
        }
        if (preg_match('/^\d{1,3}$/', $code)) {
            return null;
        }

        return $code;
    }

    private static function isPriceContext(string $message, int $offset, string $token): bool
    {
        $before = mb_strtolower(mb_substr($message, max(0, $offset - 24), min(24, $offset), 'UTF-8'), 'UTF-8');
        if (preg_match('/(?:pret|preț|prețul|pretul|ron|lei)\s*(?:la|de|=)?\s*$/u', $before)) {
            return true;
        }

        return preg_match('/^\d{1,3}$/', $token) === 1;
    }

    /** Extrage termen după «pentru», «despre», «cu numele». */
    public static function extractTermAfterPreposition(string $message): ?string
    {
        if (preg_match(
            '/\b(?:pentru|despre|cu\s+numele|named|called)\s+((?:[A-Za-zÀ-ÿ0-9][A-Za-zÀ-ÿ0-9\-\.\s]{1,40}))/u',
            $message,
            $m
        )) {
            $term = self::sanitize(trim($m[1]));
            if (preg_match('/^(.+?)\s+(?:am|sunt|care|ce|c[aâ]te|comenz\w*|produs\w*)\b/iu', $term, $cut)) {
                $term = trim($cut[1]);
            }

            return $term !== '' ? $term : null;
        }

        return null;
    }

    /** @param list<string> $words */
    public static function haystackContainsAllWords(string $haystack, array $words): bool
    {
        $hay = mb_strtolower($haystack, 'UTF-8');
        foreach ($words as $word) {
            $word = trim(mb_strtolower($word, 'UTF-8'));
            if ($word === '' || mb_strlen($word, 'UTF-8') < 2) {
                continue;
            }
            if (!str_contains($hay, $word)) {
                return false;
            }
        }

        return $words !== [];
    }
}

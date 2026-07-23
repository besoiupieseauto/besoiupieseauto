<?php

declare(strict_types=1);

namespace Besoiu\Services\AiRag;

use Besoiu\Services\OllamaLlmClient;
use Throwable;

/**
 * Procesare Ollama — extragere structurată din text scrapuit (preț, SEO, tendințe).
 */
final class AiMarketResearchProcessService
{
    public function __construct(
        private readonly string $projectRoot = '',
    ) {
    }

    private function root(): string
    {
        return $this->projectRoot !== ''
            ? $this->projectRoot
            : (defined('BESOIU_ROOT') ? (string) BESOIU_ROOT : dirname(__DIR__, 4));
    }

    /**
     * @return array{ok:bool,continut_procesat:array<string,mixed>,confidence:float,raw:string,error:string}
     */
    public function extract(string $tipSursa, string $plainText, string $url = ''): array
    {
        $plainText = trim($plainText);
        if ($plainText === '') {
            return ['ok' => false, 'continut_procesat' => [], 'confidence' => 0.0, 'raw' => '', 'error' => 'Text gol'];
        }

        $client = new OllamaLlmClient($this->root() . '/app');
        if (!$client->isEnabled()) {
            return ['ok' => false, 'continut_procesat' => [], 'confidence' => 0.0, 'raw' => '', 'error' => 'Ollama dezactivat'];
        }

        $system = $this->systemPrompt($tipSursa);
        $user = mb_substr($plainText, 0, 12000);
        if ($url !== '') {
            $user = "URL: {$url}\n\n" . $user;
        }

        try {
            $result = $client->complete($system, $user, 0.05, 90, 'qwen2.5:7b');
        } catch (Throwable $e) {
            return ['ok' => false, 'continut_procesat' => [], 'confidence' => 0.0, 'raw' => '', 'error' => $e->getMessage()];
        }

        $raw = (string) ($result['content'] ?? '');
        if (empty($result['ok']) || $raw === '') {
            return ['ok' => false, 'continut_procesat' => [], 'confidence' => 0.0, 'raw' => $raw, 'error' => (string) ($result['error'] ?? 'LLM fără răspuns')];
        }

        $parsed = $this->parseJson($raw);
        if ($parsed === null) {
            return ['ok' => false, 'continut_procesat' => [], 'confidence' => 0.0, 'raw' => $raw, 'error' => 'JSON invalid de la LLM'];
        }

        $confidence = (float) ($parsed['confidence'] ?? $parsed['confidence_extractie'] ?? 0.75);

        return [
            'ok' => true,
            'continut_procesat' => $parsed,
            'confidence' => max(0.0, min(1.0, $confidence)),
            'raw' => $raw,
            'error' => '',
        ];
    }

    private function systemPrompt(string $tipSursa): string
    {
        return match ($tipSursa) {
            'pret_concurenta' => <<<'PROMPT'
Ești un extractor de date structurate. Primești text brut extras de pe o pagină web
de comerț electronic cu piese auto. Extrage EXCLUSIV informațiile cerute în formatul JSON
de mai jos. Dacă o informație lipsește, pune null. NU inventa date.

Format output (JSON strict, fără alt text):
{
  "nume_produs": "string",
  "pret": number sau null,
  "moneda": "string",
  "cod_oe": "string sau null",
  "disponibilitate": "string sau null",
  "marca": "string sau null",
  "confidence": 0.0-1.0
}
PROMPT,
            'seo_keyword' => <<<'PROMPT'
Analizezi rezultate de căutare Google pentru un termen legat de piese auto.
Identifică pattern-uri comune în titluri și descrieri (ce cuvinte/formulări apar des).
Output JSON strict:
{"cuvinte_cheie_frecvente":["..."],"formulari_titluri_comune":["..."],"observatii":"text scurt","confidence":0.0-1.0}
PROMPT,
            'specificatie_tehnica' => <<<'PROMPT'
Extrage specificații tehnice piese auto din text. JSON strict:
{"entitate":"string","cod_oe":"string sau null","specificatii":{},"confidence":0.0-1.0}
Nu inventa valori.
PROMPT,
            'catalog_produse' => <<<'PROMPT'
Extragi catalog piese auto din pagină web scrapuită. Respectă misiunea din text.
JSON strict (fără alt text):
{
  "produse": [{"nume":"string","pret":null,"moneda":"RON","sku":null,"marca":null,"url":null}],
  "cuvinte_seo": ["max 10"],
  "categorie_detectata": "string",
  "observatii": "string scurt",
  "confidence": 0.0-1.0
}
NU inventa prețuri sau coduri — doar din text. Max 8 produse în listă.
PROMPT,
            default => <<<'PROMPT'
Rezumă în română, în maxim 3 propoziții, informația relevantă pentru un
magazin de piese auto din textul de mai jos. Concentrează-te pe: ce problemă/nevoie
descrie utilizatorul, ce piesă e implicată, dacă hint-uiește o tendință de cerere.

Output JSON: {"rezumat":"...","piesa":"string sau null","tendinta":"string sau null","confidence":0.0-1.0}
PROMPT,
        };
    }

    /** @return array<string, mixed>|null */
    private function parseJson(string $raw): ?array
    {
        $raw = trim($raw);
        if (preg_match('/```(?:json)?\s*([\s\S]*?)```/i', $raw, $m)) {
            $raw = trim($m[1]);
        }
        $start = strpos($raw, '{');
        $end = strrpos($raw, '}');
        if ($start === false || $end === false) {
            return null;
        }
        $decoded = json_decode(substr($raw, $start, $end - $start + 1), true);

        return is_array($decoded) ? $decoded : null;
    }
}

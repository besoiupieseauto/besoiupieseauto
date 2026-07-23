<?php

declare(strict_types=1);

namespace Besoiu\Services;

/**
 * Ingestie cunoștințe agent — text manual, scrape URL, fragmentare + Ollama opțional.
 */
final class AiAgentKnowledgeIngestService
{
    public function __construct(
        private readonly string $projectRoot = '',
        private ?AiAgentContextLibraryService $library = null,
        private ?OllamaLlmClient $ollama = null,
    ) {
    }

    private function root(): string
    {
        return $this->projectRoot !== ''
            ? $this->projectRoot
            : (defined('BESOIU_ROOT') ? (string) BESOIU_ROOT : dirname(__DIR__, 3));
    }

    private function library(): AiAgentContextLibraryService
    {
        return $this->library ??= new AiAgentContextLibraryService($this->root());
    }

    private function ollama(): OllamaLlmClient
    {
        return $this->ollama ??= new OllamaLlmClient($this->root());
    }

    /**
     * @param array<string, mixed> $options summarize, max_chars, pinned, note
     * @return array<string, mixed>
     */
    public function scrapeUrl(string $slug, string $url, array $options = []): array
    {
        $slug = trim($slug);
        $url = trim($url);
        if ($slug === '' || $url === '') {
            return ['ok' => false, 'error' => 'Lipsește agent sau URL'];
        }
        if (!filter_var($url, FILTER_VALIDATE_URL)) {
            return ['ok' => false, 'error' => 'URL invalid'];
        }
        $scheme = strtolower((string) parse_url($url, PHP_URL_SCHEME));
        if (!in_array($scheme, ['http', 'https'], true)) {
            return ['ok' => false, 'error' => 'Doar http/https'];
        }

        $fetch = $this->fetchUrl($url);
        if (empty($fetch['ok'])) {
            return $fetch;
        }

        $html = (string) ($fetch['html'] ?? '');
        $parser = new AiLibraryPageParserService();
        $product = $parser->parseProductFromHtml($html, $url);
        if ($product !== null) {
            $line = $parser->formatProductLine($product);
            $entry = $this->library()->appendEntry($slug, [
                'text' => $line,
                'source' => 'operator',
                'tags' => ['scrape', 'url', 'product-parsed', (string) parse_url($url, PHP_URL_HOST)],
                'pinned' => !empty($options['pinned']),
            ]);
            if ($entry !== null) {
                return [
                    'ok' => true,
                    'url' => $url,
                    'title' => (string) ($product['name'] ?? $fetch['title'] ?? ''),
                    'mode' => 'product_parsed',
                    'product' => $product,
                    'fragments_added' => 1,
                    'entries' => [$entry],
                    'summary' => $this->library()->summary($slug),
                ];
            }
        }

        $plain = $this->htmlToText($html);
        $maxChars = max(500, min(50000, (int) ($options['max_chars'] ?? 12000)));
        if (mb_strlen($plain) > $maxChars) {
            $plain = mb_substr($plain, 0, $maxChars) . '…';
        }
        if (mb_strlen(trim($plain)) < 40) {
            return ['ok' => false, 'error' => 'Prea puțin text extras din pagină'];
        }

        $fragments = [];
        $summarize = !empty($options['summarize']);
        if ($summarize && $this->ollama()->isEnabled()) {
            $fragments = $this->summarizeToFragments($plain, $url, (string) ($options['note'] ?? ''));
        }
        if ($fragments === []) {
            $fragments = $this->chunkText($plain, 900);
        }

        $tags = ['scrape', 'url'];
        $host = (string) parse_url($url, PHP_URL_HOST);
        if ($host !== '') {
            $tags[] = $host;
        }
        $added = [];
        foreach ($fragments as $fragment) {
            $text = trim($fragment);
            if ($text === '') {
                continue;
            }
            $entry = $this->library()->appendEntry($slug, [
                'text' => $text,
                'source' => 'operator',
                'tags' => $tags,
                'pinned' => !empty($options['pinned']),
                'meta' => [
                    'url' => $url,
                    'title' => (string) ($fetch['title'] ?? ''),
                    'fetched_at' => date('c'),
                ],
            ]);
            if ($entry !== null) {
                $added[] = $entry;
            }
        }

        return [
            'ok' => count($added) > 0,
            'url' => $url,
            'title' => (string) ($fetch['title'] ?? ''),
            'bytes' => (int) ($fetch['bytes'] ?? 0),
            'extracted_chars' => mb_strlen($plain),
            'fragments_added' => count($added),
            'entries' => $added,
            'summary' => $this->library()->summary($slug),
            'preview' => mb_substr($plain, 0, 400),
        ];
    }

    /** @return array<string, mixed> */
    public function testRetrieval(string $slug, string $query, int $limit = 8): array
    {
        $slug = trim($slug);
        $query = trim($query);
        if ($slug === '' || $query === '') {
            return ['ok' => false, 'error' => 'Lipsește agent sau întrebare'];
        }
        $hits = $this->library()->retrieveForUserQuery($slug, $query, max(1, min(20, $limit)));
        $lines = $this->library()->formatForRuntime($hits);

        return [
            'ok' => true,
            'query' => $query,
            'hits' => $hits,
            'formatted' => $lines,
            'summary' => $this->library()->summary($slug),
        ];
    }

    /** @return array{ok:bool,html?:string,title?:string,bytes?:int,error?:string} */
    private function fetchUrl(string $url): array
    {
        $ch = curl_init($url);
        if ($ch === false) {
            return ['ok' => false, 'error' => 'curl_init eșuat'];
        }
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS => 4,
            CURLOPT_TIMEOUT => 25,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_USERAGENT => 'BesoiuAgentTrainer/1.0 (+https://besoiupieseauto.ro)',
            CURLOPT_HTTPHEADER => ['Accept: text/html,application/xhtml+xml;q=0.9,*/*;q=0.8'],
        ]);
        $html = curl_exec($ch);
        $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if (!is_string($html) || $html === '' || $code >= 400) {
            return ['ok' => false, 'error' => 'HTTP ' . $code . ' la fetch URL'];
        }

        $title = '';
        if (preg_match('/<title[^>]*>(.*?)<\/title>/is', $html, $m)) {
            $title = trim(html_entity_decode(strip_tags($m[1]), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
        }

        return [
            'ok' => true,
            'html' => $html,
            'title' => $title,
            'bytes' => strlen($html),
        ];
    }

    private function htmlToText(string $html): string
    {
        $html = preg_replace('/<(script|style|noscript|svg|iframe)[^>]*>.*?<\/\1>/is', ' ', $html) ?? $html;
        $html = preg_replace('/<!--.*?-->/s', ' ', $html) ?? $html;
        $html = str_replace(['</p>', '</div>', '</li>', '<br>', '<br/>', '<br />', '</h1>', '</h2>', '</h3>'], "\n", $html);
        $text = html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = preg_replace('/[ \t\x0B\f\r]+/u', ' ', $text) ?? $text;
        $text = preg_replace('/\n{3,}/u', "\n\n", $text) ?? $text;

        return trim($text);
    }

    /** @return list<string> */
    private function chunkText(string $text, int $maxLen = 900): array
    {
        $text = trim($text);
        if ($text === '') {
            return [];
        }
        $paragraphs = preg_split('/\n{2,}/u', $text) ?: [$text];
        $chunks = [];
        $buf = '';
        foreach ($paragraphs as $p) {
            $p = trim($p);
            if ($p === '') {
                continue;
            }
            if (mb_strlen($p) > $maxLen) {
                if ($buf !== '') {
                    $chunks[] = $buf;
                    $buf = '';
                }
                $offset = 0;
                $len = mb_strlen($p);
                while ($offset < $len) {
                    $chunks[] = mb_substr($p, $offset, $maxLen);
                    $offset += $maxLen;
                }
                continue;
            }
            if ($buf === '') {
                $buf = $p;
            } elseif (mb_strlen($buf) + 2 + mb_strlen($p) <= $maxLen) {
                $buf .= "\n\n" . $p;
            } else {
                $chunks[] = $buf;
                $buf = $p;
            }
        }
        if ($buf !== '') {
            $chunks[] = $buf;
        }

        return array_slice($chunks, 0, 12);
    }

    /** @return list<string> */
    private function summarizeToFragments(string $plain, string $url, string $note): array
    {
        $prompt = "Extrage fragmente de cunoștințe utile pentru un agent piese auto Besoiu.\n"
            . "Sursă: {$url}\n"
            . ($note !== '' ? "Notă operator: {$note}\n" : '')
            . "Returnează DOAR JSON: {\"fragments\":[\"...\",\"...\"]}\n"
            . "Max 8 fragmente, română, fără inventat, fiecare 1-3 propoziții.\n\n"
            . "TEXT:\n" . mb_substr($plain, 0, 6000);

        try {
            $resp = $this->ollama()->chat(
                [['role' => 'user', 'content' => $prompt]],
                'Ești extractor de cunoștințe. Răspunde DOAR JSON valid.',
                0.1,
                90
            );
            if (empty($resp['ok'])) {
                return [];
            }
            $raw = trim((string) ($resp['content'] ?? ''));
            if ($raw === '') {
                return [];
            }
            $json = json_decode($raw, true);
            $frags = is_array($json['fragments'] ?? null) ? $json['fragments'] : [];
            $out = [];
            foreach ($frags as $f) {
                $t = trim((string) $f);
                if ($t !== '') {
                    $out[] = $t;
                }
            }

            return array_slice($out, 0, 10);
        } catch (\Throwable) {
            return [];
        }
    }
}

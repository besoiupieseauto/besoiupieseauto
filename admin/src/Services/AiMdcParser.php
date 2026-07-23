<?php

declare(strict_types=1);

namespace Besoiu\Services;

/**
 * Parse fișiere .mdc (YAML frontmatter + body markdown).
 */
final class AiMdcParser
{
    /** @return array{meta: array<string, mixed>, body: string} */
    public static function parse(string $content): array
    {
        $content = str_replace("\r\n", "\n", $content);
        if (!preg_match('/^---\s*\n(.*?)\n---\s*\n?(.*)$/s', $content, $m)) {
            return ['meta' => [], 'body' => trim($content)];
        }

        $meta = [];
        foreach (preg_split('/\n/', trim($m[1])) ?: [] as $line) {
            if (!str_contains($line, ':')) {
                continue;
            }
            [$key, $val] = array_map('trim', explode(':', $line, 2));
            if ($key === '') {
                continue;
            }
            $meta[$key] = self::parseYamlValue($val);
        }

        return ['meta' => $meta, 'body' => trim($m[2])];
    }

    /** @param array<string, mixed> $meta */
    public static function compose(array $meta, string $body): string
    {
        $lines = ['---'];
        foreach ($meta as $key => $val) {
            if (is_bool($val)) {
                $lines[] = $key . ': ' . ($val ? 'true' : 'false');
            } elseif (is_array($val)) {
                $lines[] = $key . ': ' . json_encode($val, JSON_UNESCAPED_UNICODE);
            } else {
                $lines[] = $key . ': ' . (string) $val;
            }
        }
        $lines[] = '---';
        $lines[] = '';
        $lines[] = trim($body);

        return implode("\n", $lines) . "\n";
    }

    private static function parseYamlValue(string $val): mixed
    {
        $val = trim($val);
        if ($val === 'true') {
            return true;
        }
        if ($val === 'false') {
            return false;
        }
        if (is_numeric($val)) {
            return str_contains($val, '.') ? (float) $val : (int) $val;
        }
        if (str_starts_with($val, '[') || str_starts_with($val, '{')) {
            $json = json_decode($val, true);

            return is_array($json) ? $json : $val;
        }

        return trim($val, " \t\"'");
    }
}

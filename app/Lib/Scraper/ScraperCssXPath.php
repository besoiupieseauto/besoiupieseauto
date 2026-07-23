<?php

declare(strict_types=1);

/** Conversie selectori CSS simplă → XPath (tag.class, .class, descendenți). */
final class ScraperCssXPath
{
    public static function toXPath(string $css, bool $relative = false): string
    {
        $css = trim($css);
        if ($css === '') {
            return '';
        }
        if (str_starts_with($css, '//') || str_starts_with($css, './/')) {
            return $css;
        }
        if (str_contains($css, ',')) {
            $parts = array_map(
                static fn (string $part): string => self::toXPath(trim($part), $relative),
                explode(',', $css)
            );

            return implode(' | ', $parts);
        }

        if (str_contains($css, ' ')) {
            $segments = preg_split('/\s+/', $css) ?: [];
            if (count($segments) > 1) {
                $base = $relative ? '.' : '';
                $xparts = array_map(
                    static fn (string $segment): string => self::singleSelector($segment),
                    $segments
                );

                return $base . '//' . implode('//', $xparts);
            }
        }

        $single = self::singleSelector($css);

        return ($relative ? './/' : '//') . $single;
    }

    public static function singleSelector(string $sel): string
    {
        $sel = trim($sel);
        if ($sel === '') {
            return '*';
        }

        $tag = '*';
        $classes = [];

        if (str_starts_with($sel, '.')) {
            $classes = array_values(array_filter(explode('.', substr($sel, 1))));
        } elseif (preg_match('/^([a-zA-Z][a-zA-Z0-9]*)(.*)$/', $sel, $m)) {
            $tag = $m[1];
            $rest = (string) ($m[2] ?? '');
            if ($rest !== '' && str_starts_with($rest, '.')) {
                $classes = array_values(array_filter(explode('.', ltrim($rest, '.'))));
            }
        }

        if ($classes === []) {
            return $tag;
        }

        $conds = [];
        foreach ($classes as $class) {
            $conds[] = "contains(concat(' ', normalize-space(@class), ' '), ' {$class} ')";
        }

        return $tag . '[' . implode(' and ', $conds) . ']';
    }
}

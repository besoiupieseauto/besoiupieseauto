<?php
declare(strict_types=1);

if (!function_exists('besoiu_note_prepare')) {
    function besoiu_note_prepare(string $note): string
    {
        $note = trim($note);
        if ($note === '') {
            return '';
        }

        for ($pass = 0; $pass < 2; $pass++) {
            $decoded = html_entity_decode($note, ENT_QUOTES | ENT_HTML5, 'UTF-8');
            if ($decoded === $note) {
                break;
            }
            $note = $decoded;
        }

        return $note;
    }
}

if (!function_exists('besoiu_note_is_html')) {
    function besoiu_note_is_html(string $note): bool
    {
        $note = besoiu_note_prepare($note);
        if ($note === '') {
            return false;
        }

        return (bool) preg_match('/<\s*(p|ul|ol|li|div|dl|dt|dd|table|thead|tbody|tr|th|td|h[1-6]|br|strong|b|em|i|span)\b/i', $note);
    }
}

if (!function_exists('besoiu_note_plain_text')) {
    function besoiu_note_plain_text(string $note): string
    {
        $note = besoiu_note_prepare($note);
        $note = preg_replace('/<\s*(br|hr)\s*\/?>/i', ' ', $note) ?? $note;
        $note = preg_replace('/<\/\s*(p|li|tr|h[1-6])\s*>/i', ' ', $note) ?? $note;
        $note = strip_tags($note);
        $note = preg_replace('/\s+/u', ' ', $note) ?? $note;

        return trim($note);
    }
}

if (!function_exists('besoiu_note_sanitize_html')) {
    function besoiu_note_sanitize_html(string $html): string
    {
        $html = besoiu_note_prepare($html);
        if ($html === '') {
            return '';
        }

        $allowed = '<p><br><b><strong><i><em><ul><ol><li><h2><h3><h4><dl><dt><dd><table><thead><tbody><tr><th><td><span><div>';
        $html = strip_tags($html, $allowed);

        return preg_replace('/<(\/?)(\w+)(\s[^>]*)>/', '<$1$2>', $html) ?? $html;
    }
}

if (!function_exists('besoiu_note_render')) {
    function besoiu_note_render(string $note): string
    {
        $note = besoiu_note_prepare($note);
        if ($note === '') {
            return '';
        }

        if (besoiu_note_is_html($note)) {
            return besoiu_note_sanitize_html($note);
        }

        return nl2br(htmlspecialchars($note, ENT_QUOTES, 'UTF-8'));
    }
}

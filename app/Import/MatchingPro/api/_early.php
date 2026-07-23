<?php
declare(strict_types=1);

/**
 * Răspuns JSON + fatal guard — încărcat înainte de orice bootstrap greu.
 */

@ini_set('memory_limit', '768M');

if (!function_exists('import_utf8_sanitize_string')) {
    /**
     * Convertește / curăță text din CSV-uri furnizor (adesea Windows-1250) la UTF-8 valid.
     */
    function import_utf8_sanitize_string(string $value): string
    {
        if ($value === '') {
            return '';
        }
        if (mb_check_encoding($value, 'UTF-8')) {
            return $value;
        }

        $candidates = ['Windows-1250', 'CP1250', 'ISO-8859-2', 'CP1252', 'ISO-8859-1'];
        $available = array_flip(array_map('strtoupper', mb_list_encodings()));

        foreach ($candidates as $encoding) {
            if (!isset($available[strtoupper($encoding)])) {
                $converted = @iconv($encoding, 'UTF-8//IGNORE', $value);
                if (is_string($converted) && $converted !== '' && mb_check_encoding($converted, 'UTF-8')) {
                    return $converted;
                }
                continue;
            }
            $converted = @mb_convert_encoding($value, 'UTF-8', $encoding);
            if (is_string($converted) && $converted !== '' && mb_check_encoding($converted, 'UTF-8')) {
                return $converted;
            }
        }

        $scrubbed = @mb_convert_encoding($value, 'UTF-8', 'UTF-8');
        if (is_string($scrubbed) && mb_check_encoding($scrubbed, 'UTF-8')) {
            return $scrubbed;
        }

        $iconv = @iconv('UTF-8', 'UTF-8//IGNORE', $value);
        if (is_string($iconv)) {
            return $iconv;
        }

        return '';
    }
}

if (!function_exists('import_utf8_sanitize_value')) {
    /** @return mixed */
    function import_utf8_sanitize_value(mixed $value): mixed
    {
        if (is_string($value)) {
            return import_utf8_sanitize_string($value);
        }
        if (is_array($value)) {
            return import_utf8_sanitize_deep($value);
        }

        return $value;
    }
}

if (!function_exists('import_utf8_sanitize_deep')) {
    /**
     * @param array<mixed> $data
     * @return array<mixed>
     */
    function import_utf8_sanitize_deep(array $data): array
    {
        $out = [];
        foreach ($data as $key => $value) {
            $safeKey = is_string($key) ? import_utf8_sanitize_string($key) : $key;
            $out[$safeKey] = import_utf8_sanitize_value($value);
        }

        return $out;
    }
}

if (!function_exists('import_json_response')) {
    function import_json_response(array $data, int $code = 200): void
    {
        while (ob_get_level() > 0) {
            ob_end_clean();
        }

        if (!headers_sent()) {
            http_response_code($code);
            header('Content-Type: application/json; charset=utf-8');
            header('Access-Control-Allow-Origin: *');
            header('Cache-Control: no-store');
        }

        $data = import_utf8_sanitize_deep($data);
        $jsonFlags = JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT;
        if (defined('JSON_INVALID_UTF8_SUBSTITUTE')) {
            $jsonFlags |= JSON_INVALID_UTF8_SUBSTITUTE;
        }

        $json = json_encode($data, $jsonFlags);
        if ($json === false) {
            $json = json_encode([
                'success' => false,
                'error' => 'json_encode eșuat: ' . json_last_error_msg(),
            ], JSON_UNESCAPED_UNICODE | (defined('JSON_INVALID_UTF8_SUBSTITUTE') ? JSON_INVALID_UTF8_SUBSTITUTE : 0));
        }
        echo $json;
        exit;
    }
}

if (!function_exists('import_motor_api_fatal_guard')) {
    function import_motor_api_fatal_guard(): void
    {
        $error = error_get_last();
        if ($error === null || !in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR], true)) {
            return;
        }
        if (headers_sent()) {
            return;
        }

        while (ob_get_level() > 0) {
            ob_end_clean();
        }

        http_response_code(500);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode([
            'success' => false,
            'error' => 'Fatal API: ' . ($error['message'] ?? 'unknown'),
            'file' => basename((string) ($error['file'] ?? '')),
        ], JSON_UNESCAPED_UNICODE);
    }

    register_shutdown_function('import_motor_api_fatal_guard');
}

if (!function_exists('import_motor_api_run')) {
    /**
     * @param callable(): void $handler
     */
    function import_motor_api_run(callable $handler, string $context = 'api'): void
    {
        try {
            $handler();
        } catch (Throwable $e) {
            import_json_response([
                'success' => false,
                'error' => $context . ': ' . $e->getMessage(),
            ], 500);
        }
    }
}

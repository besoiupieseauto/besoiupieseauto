<?php

declare(strict_types=1);

namespace Besoiu\Services\AiRag;

/**
 * Validare output structurat JSON — fără scriere în DB dacă schema e invalidă.
 */
final class AiStructuredOutputValidator
{
    /**
     * @param array<string, mixed> $schema
     * @return array{ok:bool,errors:list<string>,data:array<string,mixed>}
     */
    public function validate(array $data, array $schema): array
    {
        $errors = [];
        $required = (array) ($schema['required'] ?? []);
        foreach ($required as $key) {
            if (!array_key_exists((string) $key, $data)) {
                $errors[] = 'Lipsește câmpul obligatoriu: ' . $key;
            }
        }

        $properties = (array) ($schema['properties'] ?? []);
        foreach ($properties as $key => $rule) {
            if (!array_key_exists($key, $data)) {
                continue;
            }
            if (!is_array($rule)) {
                continue;
            }
            $type = (string) ($rule['type'] ?? '');
            $value = $data[$key];
            if ($type === 'string' && !is_string($value)) {
                $errors[] = $key . ' trebuie string';
            }
            if ($type === 'number' && !is_numeric($value)) {
                $errors[] = $key . ' trebuie number';
            }
            if ($type === 'array' && !is_array($value)) {
                $errors[] = $key . ' trebuie array';
            }
            if ($type === 'object' && !is_array($value)) {
                $errors[] = $key . ' trebuie object';
            }
            if (isset($rule['enum']) && is_array($rule['enum']) && !in_array($value, $rule['enum'], true)) {
                $errors[] = $key . ' valoare invalidă';
            }
            if (isset($rule['minimum']) && is_numeric($value) && (float) $value < (float) $rule['minimum']) {
                $errors[] = $key . ' sub minim';
            }
            if (isset($rule['maximum']) && is_numeric($value) && (float) $value > (float) $rule['maximum']) {
                $errors[] = $key . ' peste maxim';
            }
        }

        return [
            'ok' => $errors === [],
            'errors' => $errors,
            'data' => $data,
        ];
    }

    /**
     * @return array{ok:bool,data:array<string,mixed>,raw:string,error:string}
     */
    public function parseJsonFromLlm(string $raw): array
    {
        $raw = trim($raw);
        if ($raw === '') {
            return ['ok' => false, 'data' => [], 'raw' => '', 'error' => 'Răspuns gol'];
        }

        $json = json_decode($raw, true);
        if (is_array($json)) {
            return ['ok' => true, 'data' => $json, 'raw' => $raw, 'error' => ''];
        }

        if (preg_match('/\{[\s\S]*\}/', $raw, $m)) {
            $json = json_decode($m[0], true);
            if (is_array($json)) {
                return ['ok' => true, 'data' => $json, 'raw' => $m[0], 'error' => ''];
            }
        }

        return ['ok' => false, 'data' => [], 'raw' => $raw, 'error' => 'JSON invalid'];
    }
}

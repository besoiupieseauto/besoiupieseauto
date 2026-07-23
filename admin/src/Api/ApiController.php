<?php declare(strict_types=1);

namespace Besoiu\Api;

/**
 * Controller REST API standardizat
 * 
 * @version 2.0.0 
 * @created 2026-07-02
 */
abstract class ApiController
{
    protected function jsonResponse(mixed $data, int $statusCode = 200): void
    {
        http_response_code($statusCode);
        header('Content-Type: application/json');
        echo json_encode([
            'success' => $statusCode < 400,
            'data' => $data,
            'timestamp' => date('c')
        ]);
    }

    protected function errorResponse(string $message, int $code = 400): void
    {
        $this->jsonResponse(['error' => $message], $code);
    }

    protected function validateRequest(array $required, array $input): array
    {
        $missing = [];
        foreach ($required as $field) {
            if (!isset($input[$field])) {
                $missing[] = $field;
            }
        }
        
        if (!empty($missing)) {
            throw new \InvalidArgumentException('Missing fields: ' . implode(', ', $missing));
        }
        
        return $input;
    }
}
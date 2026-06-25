<?php

declare(strict_types=1);

namespace Evasystem\Core\Comunicare;

/**
 * Randare variabile în template-uri: {client_name}, {order_number}, etc.
 */
final class ReplyTemplateRenderer
{
    /** @return list<string> */
    public static function availableVariables(): array
    {
        return [
            'client_name', 'phone', 'email', 'order_number', 'product_name', 'oem_code',
            'total_amount', 'delivery_method', 'awb_number', 'courier_name',
            'price_economic', 'price_medium', 'price_premium', 'shop_url', 'vin',
        ];
    }

    /** @param array<string, string> $variables */
    public static function render(string $body, array $variables = []): string
    {
        return (string) preg_replace_callback(
            '/\{([a-z0-9_]+)\}/i',
            static function (array $matches) use ($variables): string {
                $key = strtolower($matches[1]);

                return array_key_exists($key, $variables) ? (string) $variables[$key] : $matches[0];
            },
            $body
        );
    }

    public static function slugify(string $title): string
    {
        $slug = strtolower(trim($title));
        $slug = preg_replace('/[^a-z0-9]+/i', '-', $slug) ?? '';
        $slug = trim($slug, '-');

        return $slug !== '' ? $slug : 'template-' . bin2hex(random_bytes(4));
    }

    public static function newRandomId(): string
    {
        return 'tpl_' . bin2hex(random_bytes(8));
    }
}

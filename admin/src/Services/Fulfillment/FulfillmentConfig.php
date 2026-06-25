<?php

declare(strict_types=1);

namespace Evasystem\Services\Fulfillment;

/**
 * Config integrari SmartBill + curieri pentru fulfillment comenzi site.
 */
final class FulfillmentConfig
{
    /** @return array<string, mixed> */
    public static function all(): array
    {
        static $config = null;
        if ($config === null) {
            $config = require dirname(__DIR__, 3) . '/config/fulfillment.php';
        }

        return $config;
    }

    public static function smartbillEnabled(): bool
    {
        return (bool) (self::all()['smartbill_enabled'] ?? false);
    }

    public static function awbEnabled(): bool
    {
        return (bool) (self::all()['awb_enabled'] ?? false);
    }

    public static function isTestMode(): bool
    {
        return (bool) (self::all()['test_mode'] ?? false);
    }

    public static function testSeries(): string
    {
        return (string) (self::all()['test']['series'] ?? 'TEST');
    }

    public static function testInvoicePrefix(): string
    {
        return (string) (self::all()['test']['invoice_prefix'] ?? 'TEST-INV');
    }

    public static function testAwbPrefix(): string
    {
        return (string) (self::all()['test']['awb_prefix'] ?? 'TEST-AWB');
    }

    /** @return array<string, mixed> */
    public static function publicStatus(): array
    {
        return [
            'test_mode' => self::isTestMode(),
            'smartbill_enabled' => self::smartbillEnabled(),
            'awb_enabled' => self::awbEnabled(),
            'smartbill_configured' => self::smartbillConfigured(),
            'fancourier_configured' => self::fancourierConfigured(),
            'sameday_configured' => self::samedayConfigured(),
            'default_courier' => self::defaultCourier(),
        ];
    }

    public static function defaultCourier(): string
    {
        return (string) (self::all()['default_courier'] ?? 'fancourier');
    }

    /** @return array<string, string> */
    public static function smartbill(): array
    {
        /** @var array<string, string> $cfg */
        $cfg = self::all()['smartbill'] ?? [];

        return $cfg;
    }

    /** @return array<string, string|int> */
    public static function fancourier(): array
    {
        /** @var array<string, string|int> $cfg */
        $cfg = self::all()['fancourier'] ?? [];

        return $cfg;
    }

    /** @return array<string, string|int> */
    public static function sameday(): array
    {
        /** @var array<string, string|int> $cfg */
        $cfg = self::all()['sameday'] ?? [];

        return $cfg;
    }

    public static function smartbillConfigured(): bool
    {
        $cfg = self::smartbill();

        return ($cfg['api_key'] ?? '') !== '' && ($cfg['user_email'] ?? '') !== '';
    }

    public static function fancourierConfigured(): bool
    {
        $cfg = self::fancourier();

        return ($cfg['username'] ?? '') !== ''
            && ($cfg['password'] ?? '') !== ''
            && (string) ($cfg['client_id'] ?? '') !== '';
    }

    public static function samedayConfigured(): bool
    {
        $cfg = self::sameday();

        return ($cfg['username'] ?? '') !== ''
            && ($cfg['password'] ?? '') !== ''
            && (int) ($cfg['pickup_point_id'] ?? 0) > 0;
    }
}

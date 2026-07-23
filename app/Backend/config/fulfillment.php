<?php

declare(strict_types=1);

return [
    // Mod test factura/AWB — dezactivat implicit (0). Activati doar la nevoie: FULFILLMENT_TEST_MODE=1
    'test_mode' => filter_var($_ENV['FULFILLMENT_TEST_MODE'] ?? '0', FILTER_VALIDATE_BOOLEAN),
    'smartbill_enabled' => filter_var($_ENV['FULFILLMENT_SMARTBILL_ENABLED'] ?? '1', FILTER_VALIDATE_BOOLEAN),
    'awb_enabled' => filter_var($_ENV['FULFILLMENT_AWB_ENABLED'] ?? '1', FILTER_VALIDATE_BOOLEAN),
    'default_courier' => strtolower(trim((string) ($_ENV['FULFILLMENT_DEFAULT_COURIER'] ?? 'fancourier'))),
    'test' => [
        'series' => trim((string) ($_ENV['FULFILLMENT_TEST_SERIES'] ?? 'TEST')),
        'invoice_prefix' => trim((string) ($_ENV['FULFILLMENT_TEST_INVOICE_PREFIX'] ?? 'TEST-INV')),
        'awb_prefix' => trim((string) ($_ENV['FULFILLMENT_TEST_AWB_PREFIX'] ?? 'TEST-AWB')),
    ],
    'smartbill' => [
        'api_url' => rtrim(trim((string) ($_ENV['SMARTBILL_API_URL'] ?? 'https://ws.smartbill.ro/SBZ/api')), '/'),
        'api_key' => trim((string) ($_ENV['SMARTBILL_API_KEY'] ?? '')),
        'user_email' => trim((string) ($_ENV['SMARTBILL_USER_EMAIL'] ?? '')),
        'company_vat' => trim((string) ($_ENV['SMARTBILL_COMPANY_VAT'] ?? 'RO31298897')),
        'series_name' => trim((string) ($_ENV['SMARTBILL_SERIES_NAME'] ?? 'BPA_CAI')),
    ],
    'fancourier' => [
        'api_url' => rtrim(trim((string) ($_ENV['FANCOURIER_API_URL'] ?? 'https://api.fancourier.ro')), '/'),
        'username' => trim((string) ($_ENV['FANCOURIER_USERNAME'] ?? '')),
        'password' => trim((string) ($_ENV['FANCOURIER_PASSWORD'] ?? '')),
        'client_id' => trim((string) ($_ENV['FANCOURIER_CLIENT_ID'] ?? '')),
        'service_standard' => trim((string) ($_ENV['FANCOURIER_SERVICE_STANDARD'] ?? 'Standard')),
        'service_cod' => trim((string) ($_ENV['FANCOURIER_SERVICE_COD'] ?? 'Cont Colector')),
        'cost_center' => trim((string) ($_ENV['FANCOURIER_COST_CENTER'] ?? 'WEB')),
    ],
    'sameday' => [
        'api_url' => rtrim(trim((string) ($_ENV['SAMEDAY_API_URL'] ?? 'https://api.sameday.ro')), '/'),
        'username' => trim((string) ($_ENV['SAMEDAY_USERNAME'] ?? '')),
        'password' => trim((string) ($_ENV['SAMEDAY_PASSWORD'] ?? '')),
        'pickup_point_id' => (int) ($_ENV['SAMEDAY_PICKUP_POINT_ID'] ?? 0),
        'service_id' => (int) ($_ENV['SAMEDAY_SERVICE_ID'] ?? 7),
    ],
];

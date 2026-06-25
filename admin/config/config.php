<?php

return [
    'db_host' => $_ENV['DB_HOST'] ?? '127.0.0.1',
    'db_name' => $_ENV['DB_NAME'] ?? 'besoiupieseauto.ro',
    'db_user' => $_ENV['DB_USER'] ?? 'root',
    'db_pass' => $_ENV['DB_PASS'] ?? '',
    'legacy_db_host' => $_ENV['LEGACY_DB_HOST'] ?? ($_ENV['DB_HOST'] ?? '127.0.0.1'),
    'legacy_db_name' => $_ENV['LEGACY_DB_NAME'] ?? '',
    'legacy_db_user' => $_ENV['LEGACY_DB_USER'] ?? ($_ENV['DB_USER'] ?? 'root'),
    'legacy_db_pass' => $_ENV['LEGACY_DB_PASS'] ?? ($_ENV['DB_PASS'] ?? ''),
];

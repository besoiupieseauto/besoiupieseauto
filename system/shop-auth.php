<?php

declare(strict_types=1);

use Config\Database;

if (!function_exists('shop_auth_session_start')) {
    function shop_auth_session_start(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            return;
        }

        session_name('bpa_shop');
        session_set_cookie_params([
            'lifetime' => 0,
            'path' => '/',
            'secure' => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
        session_start();
    }

    function shop_auth_h($value): string
    {
        return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
    }

    function shop_auth_session_user(): ?array
    {
        shop_auth_session_start();

        $id = (int) ($_SESSION['shop_customer_id'] ?? 0);
        if ($id <= 0) {
            return null;
        }

        return [
            'id' => $id,
            'name' => (string) ($_SESSION['shop_customer_name'] ?? ''),
            'email' => (string) ($_SESSION['shop_customer_email'] ?? ''),
        ];
    }

    function shop_auth_load_env(): void
    {
        static $loaded = false;
        if ($loaded) {
            return;
        }

        $envPath = __DIR__ . '/../admin/.env';
        if (!is_readable($envPath)) {
            $loaded = true;
            return;
        }

        $lines = file($envPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        if (!is_array($lines)) {
            $loaded = true;
            return;
        }

        foreach ($lines as $line) {
            $line = trim((string) $line);
            if ($line === '' || str_starts_with($line, '#') || !str_contains($line, '=')) {
                continue;
            }

            [$name, $value] = explode('=', $line, 2);
            $name = trim($name);
            $value = trim($value);

            if (
                strlen($value) >= 2
                && (
                    ($value[0] === '"' && str_ends_with($value, '"'))
                    || ($value[0] === "'" && str_ends_with($value, "'"))
                )
            ) {
                $value = substr($value, 1, -1);
            }

            $_ENV[$name] = $value;
            putenv($name . '=' . $value);
        }

        $loaded = true;
    }

    function shop_auth_bootstrap_db(): void
    {
        static $bootstrapped = false;
        if ($bootstrapped) {
            return;
        }

        require_once __DIR__ . '/../admin/config/Database.php';
        shop_auth_load_env();

        if (!Database::hasConnection()) {
            $config = require __DIR__ . '/../admin/config/config.php';
            Database::getInstance(
                $config['db_host'],
                $config['db_name'],
                $config['db_user'],
                $config['db_pass']
            );
        }

        shop_auth_ensure_table();
        $bootstrapped = true;
    }

    function shop_auth_ensure_table(): void
    {
        static $done = false;
        if ($done) {
            return;
        }

        $pdo = Database::getDB();
        $pdo->exec(
            'CREATE TABLE IF NOT EXISTS shop_customers (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                name VARCHAR(160) NOT NULL,
                email VARCHAR(255) NOT NULL,
                password_hash VARCHAR(255) NOT NULL,
                phone VARCHAR(50) NULL,
                city VARCHAR(120) NULL,
                address VARCHAR(255) NULL,
                postal_code VARCHAR(20) NULL,
                status TINYINT(1) NOT NULL DEFAULT 1,
                created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                last_login_at DATETIME NULL,
                UNIQUE KEY uq_shop_customers_email (email),
                INDEX idx_shop_customers_status (status)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
        );

        $done = true;
    }

    function shop_auth_set_session(array $customer): void
    {
        shop_auth_session_start();
        $_SESSION['shop_customer_id'] = (int) $customer['id'];
        $_SESSION['shop_customer_name'] = (string) $customer['name'];
        $_SESSION['shop_customer_email'] = (string) $customer['email'];
    }

    function shop_auth_clear_session(): void
    {
        shop_auth_session_start();
        unset(
            $_SESSION['shop_customer_id'],
            $_SESSION['shop_customer_name'],
            $_SESSION['shop_customer_email']
        );
    }

    function shop_auth_public_customer(array $row): array
    {
        return [
            'id' => (int) ($row['id'] ?? 0),
            'name' => (string) ($row['name'] ?? ''),
            'email' => (string) ($row['email'] ?? ''),
            'phone' => (string) ($row['phone'] ?? ''),
            'city' => (string) ($row['city'] ?? ''),
            'address' => (string) ($row['address'] ?? ''),
            'postal_code' => (string) ($row['postal_code'] ?? ''),
            'created_at' => (string) ($row['created_at'] ?? ''),
            'last_login_at' => (string) ($row['last_login_at'] ?? ''),
        ];
    }

    function shop_auth_find_by_email(string $email): ?array
    {
        shop_auth_bootstrap_db();
        $pdo = Database::getDB();
        $stmt = $pdo->prepare('SELECT * FROM shop_customers WHERE email = :email LIMIT 1');
        $stmt->execute([':email' => mb_strtolower(trim($email))]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);

        return is_array($row) ? $row : null;
    }

    function shop_auth_find_by_id(int $id): ?array
    {
        shop_auth_bootstrap_db();
        $pdo = Database::getDB();
        $stmt = $pdo->prepare('SELECT * FROM shop_customers WHERE id = :id AND status = 1 LIMIT 1');
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);

        return is_array($row) ? $row : null;
    }

    function shop_auth_validate_password(string $password): ?string
    {
        if (strlen($password) < 8) {
            return 'Parola trebuie să aibă cel puțin 8 caractere.';
        }

        return null;
    }

    function shop_auth_register(array $payload): array
    {
        shop_auth_bootstrap_db();

        $name = trim((string) ($payload['name'] ?? ''));
        $email = mb_strtolower(trim((string) ($payload['email'] ?? '')));
        $phone = trim((string) ($payload['phone'] ?? ''));
        $password = (string) ($payload['password'] ?? '');
        $passwordConfirm = (string) ($payload['password_confirm'] ?? '');

        if ($name === '' || mb_strlen($name) < 2) {
            throw new InvalidArgumentException('Introdu numele complet.');
        }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new InvalidArgumentException('Adresa de email nu este validă.');
        }
        if ($password !== $passwordConfirm) {
            throw new InvalidArgumentException('Parolele nu coincid.');
        }
        $passwordError = shop_auth_validate_password($password);
        if ($passwordError !== null) {
            throw new InvalidArgumentException($passwordError);
        }
        if (shop_auth_find_by_email($email) !== null) {
            throw new InvalidArgumentException('Există deja un cont cu acest email.');
        }

        $pdo = Database::getDB();
        $stmt = $pdo->prepare(
            'INSERT INTO shop_customers (name, email, password_hash, phone, status)
             VALUES (:name, :email, :password_hash, :phone, 1)'
        );
        $stmt->execute([
            ':name' => $name,
            ':email' => $email,
            ':password_hash' => password_hash($password, PASSWORD_DEFAULT),
            ':phone' => $phone !== '' ? $phone : null,
        ]);

        $customer = shop_auth_find_by_id((int) $pdo->lastInsertId());
        if ($customer === null) {
            throw new RuntimeException('Contul nu a putut fi creat.');
        }

        shop_auth_set_session($customer);

        $pdo->prepare('UPDATE shop_customers SET last_login_at = NOW() WHERE id = :id')
            ->execute([':id' => (int) $customer['id']]);

        return shop_auth_public_customer($customer);
    }

    function shop_auth_login(string $email, string $password): array
    {
        shop_auth_bootstrap_db();

        $email = mb_strtolower(trim($email));
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new InvalidArgumentException('Adresa de email nu este validă.');
        }
        if ($password === '') {
            throw new InvalidArgumentException('Introdu parola.');
        }

        $customer = shop_auth_find_by_email($email);
        if ($customer === null || (int) ($customer['status'] ?? 0) !== 1) {
            throw new InvalidArgumentException('Email sau parolă incorectă.');
        }
        if (!password_verify($password, (string) ($customer['password_hash'] ?? ''))) {
            throw new InvalidArgumentException('Email sau parolă incorectă.');
        }

        shop_auth_set_session($customer);

        Database::getDB()->prepare('UPDATE shop_customers SET last_login_at = NOW() WHERE id = :id')
            ->execute([':id' => (int) $customer['id']]);

        return shop_auth_public_customer($customer);
    }

    function shop_auth_logout(): void
    {
        shop_auth_clear_session();
    }

    function shop_auth_update_profile(int $customerId, array $payload): array
    {
        shop_auth_bootstrap_db();

        $customer = shop_auth_find_by_id($customerId);
        if ($customer === null) {
            throw new InvalidArgumentException('Contul nu a fost găsit.');
        }

        $name = trim((string) ($payload['name'] ?? $customer['name']));
        $phone = trim((string) ($payload['phone'] ?? ''));
        $city = trim((string) ($payload['city'] ?? ''));
        $address = trim((string) ($payload['address'] ?? ''));
        $postalCode = trim((string) ($payload['postal_code'] ?? ''));

        if ($name === '' || mb_strlen($name) < 2) {
            throw new InvalidArgumentException('Introdu numele complet.');
        }

        $pdo = Database::getDB();
        $stmt = $pdo->prepare(
            'UPDATE shop_customers
             SET name = :name, phone = :phone, city = :city, address = :address, postal_code = :postal_code
             WHERE id = :id'
        );
        $stmt->execute([
            ':name' => $name,
            ':phone' => $phone !== '' ? $phone : null,
            ':city' => $city !== '' ? $city : null,
            ':address' => $address !== '' ? $address : null,
            ':postal_code' => $postalCode !== '' ? $postalCode : null,
            ':id' => $customerId,
        ]);

        $updated = shop_auth_find_by_id($customerId);
        if ($updated === null) {
            throw new RuntimeException('Profilul nu a putut fi actualizat.');
        }

        shop_auth_set_session($updated);

        return shop_auth_public_customer($updated);
    }

    function shop_auth_change_password(int $customerId, array $payload): void
    {
        shop_auth_bootstrap_db();

        $customer = shop_auth_find_by_id($customerId);
        if ($customer === null) {
            throw new InvalidArgumentException('Contul nu a fost găsit.');
        }

        $current = (string) ($payload['current_password'] ?? '');
        $newPassword = (string) ($payload['new_password'] ?? '');
        $confirm = (string) ($payload['new_password_confirm'] ?? '');

        if (!password_verify($current, (string) ($customer['password_hash'] ?? ''))) {
            throw new InvalidArgumentException('Parola actuală este incorectă.');
        }
        if ($newPassword !== $confirm) {
            throw new InvalidArgumentException('Parolele noi nu coincid.');
        }
        $passwordError = shop_auth_validate_password($newPassword);
        if ($passwordError !== null) {
            throw new InvalidArgumentException($passwordError);
        }

        Database::getDB()->prepare('UPDATE shop_customers SET password_hash = :hash WHERE id = :id')
            ->execute([
                ':hash' => password_hash($newPassword, PASSWORD_DEFAULT),
                ':id' => $customerId,
            ]);
    }

    function shop_auth_orders_for_email(string $email): array
    {
        shop_auth_bootstrap_db();
        $pdo = Database::getDB();
        $stmt = $pdo->prepare(
            'SELECT id, randomn_id, order_number, name, product_image, client_name, email, phone,
                    order_status, payment_status, delivery_method, delivery_status,
                    quantity, total_amount, notes, created_at
             FROM comenzi
             WHERE email = :email
             ORDER BY id DESC
             LIMIT 100'
        );
        $stmt->execute([':email' => mb_strtolower(trim($email))]);

        return array_map(static function (array $row): array {
            $productName = (string) ($row['name'] ?? '');
            $notes = (string) ($row['notes'] ?? '');
            $orderStatus = (string) ($row['order_status'] ?? '');
            $paymentStatus = (string) ($row['payment_status'] ?? '');
            $deliveryMethod = (string) ($row['delivery_method'] ?? '');
            $deliveryStatus = (string) ($row['delivery_status'] ?? '');
            $paymentInfo = shop_auth_order_payment_info($paymentStatus, $orderStatus);
            $items = shop_auth_parse_order_items($productName, $notes, (string) ($row['product_image'] ?? ''));

            return [
                'id' => (int) ($row['id'] ?? 0),
                'order_number' => (string) ($row['order_number'] ?? ''),
                'product_name' => $productName,
                'product_image' => (string) ($row['product_image'] ?? ''),
                'client_name' => (string) ($row['client_name'] ?? ''),
                'order_status' => $orderStatus,
                'order_status_label' => shop_auth_order_status_label($orderStatus),
                'payment_status' => $paymentStatus,
                'payment_method_label' => $paymentInfo['method_label'],
                'payment_state' => $paymentInfo['state'],
                'payment_state_label' => $paymentInfo['state_label'],
                'delivery_method' => $deliveryMethod,
                'delivery_method_label' => shop_auth_delivery_method_label($deliveryMethod),
                'delivery_status' => $deliveryStatus,
                'delivery_status_label' => shop_auth_delivery_status_label($deliveryStatus),
                'quantity' => (int) ($row['quantity'] ?? 0),
                'total_amount' => (float) ($row['total_amount'] ?? 0),
                'created_at' => (string) ($row['created_at'] ?? ''),
                'can_cancel' => shop_auth_order_can_cancel($orderStatus),
                'items' => $items,
            ];
        }, $stmt->fetchAll(\PDO::FETCH_ASSOC) ?: []);
    }

    function shop_auth_order_can_cancel(string $orderStatus): bool
    {
        return in_array($orderStatus, ['noua', 'in_lucru'], true);
    }

    function shop_auth_cancel_order(string $email, int $orderId): void
    {
        shop_auth_bootstrap_db();
        $pdo = Database::getDB();
        $stmt = $pdo->prepare(
            'SELECT id, order_status FROM comenzi WHERE id = :id AND email = :email LIMIT 1'
        );
        $stmt->execute([
            ':id' => $orderId,
            ':email' => mb_strtolower(trim($email)),
        ]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);
        if (!$row) {
            throw new InvalidArgumentException('Comanda nu a fost găsită.');
        }

        $orderStatus = (string) ($row['order_status'] ?? '');
        if (!shop_auth_order_can_cancel($orderStatus)) {
            throw new InvalidArgumentException('Această comandă nu mai poate fi anulată.');
        }

        $update = $pdo->prepare(
            'UPDATE comenzi SET order_status = :status, updated_at = NOW() WHERE id = :id AND email = :email'
        );
        $update->execute([
            ':status' => 'anulata',
            ':id' => $orderId,
            ':email' => mb_strtolower(trim($email)),
        ]);
    }

    function shop_auth_parse_order_items(string $productName, string $notes, string $fallbackImage = ''): array
    {
        $items = [];

        if (preg_match('/__BPA_ITEMS__(.+)$/s', $notes, $matches)) {
            $decoded = json_decode(trim($matches[1]), true);
            if (is_array($decoded)) {
                foreach ($decoded as $item) {
                    if (!is_array($item)) {
                        continue;
                    }
                    $name = trim((string) ($item['product_name'] ?? ''));
                    if ($name === '') {
                        continue;
                    }
                    $productId = trim((string) ($item['product_id'] ?? ''));
                    $oem = trim((string) ($item['oem'] ?? ''));
                    $items[] = [
                        'product_id' => $productId,
                        'product_name' => $name,
                        'quantity' => max(1, (int) ($item['quantity'] ?? 1)),
                        'price' => (float) ($item['price'] ?? 0),
                        'oem' => $oem,
                        'product_image' => trim((string) ($item['product_image'] ?? '')) ?: $fallbackImage,
                        'product_url' => shop_auth_find_product_link($productId, $name, $oem),
                    ];
                }
            }
        }

        if ($items !== []) {
            return $items;
        }

        $parts = preg_split('/;\s*/', trim($productName)) ?: [];
        foreach ($parts as $part) {
            $part = trim($part);
            if ($part === '') {
                continue;
            }
            if (preg_match('/^(\d+)\s*x\s*(.+)$/iu', $part, $match)) {
                $name = trim($match[2]);
                $items[] = [
                    'product_id' => '',
                    'product_name' => $name,
                    'quantity' => max(1, (int) $match[1]),
                    'price' => 0.0,
                    'oem' => shop_auth_extract_oem_from_name($name),
                    'product_image' => $fallbackImage,
                    'product_url' => shop_auth_find_product_link('', $name, shop_auth_extract_oem_from_name($name)),
                ];
                continue;
            }

            $items[] = [
                'product_id' => '',
                'product_name' => $part,
                'quantity' => 1,
                'price' => 0.0,
                'oem' => shop_auth_extract_oem_from_name($part),
                'product_image' => $fallbackImage,
                'product_url' => shop_auth_find_product_link('', $part, shop_auth_extract_oem_from_name($part)),
            ];
        }

        return $items;
    }

    function shop_auth_extract_oem_from_name(string $name): string
    {
        if (preg_match('/\b([A-Z0-9]{6,})\b/u', $name, $match)) {
            return $match[1];
        }

        return '';
    }

    function shop_auth_find_product_link(string $productId, string $name, string $oem = ''): string
    {
        $productId = trim($productId);
        if ($productId !== '') {
            return '/produs?id=' . rawurlencode($productId);
        }

        shop_auth_bootstrap_db();
        $pdo = Database::getDB();

        if ($oem !== '') {
            $stmt = $pdo->prepare(
                'SELECT randomn_id FROM produse WHERE pCode = :term OR pOem = :term LIMIT 1'
            );
            $stmt->execute([':term' => $oem]);
            $row = $stmt->fetch(\PDO::FETCH_ASSOC);
            if ($row && !empty($row['randomn_id'])) {
                return '/produs?id=' . rawurlencode((string) $row['randomn_id']);
            }
        }

        $name = trim($name);
        if ($name !== '') {
            $stmt = $pdo->prepare('SELECT randomn_id FROM produse WHERE pName = :name LIMIT 1');
            $stmt->execute([':name' => $name]);
            $row = $stmt->fetch(\PDO::FETCH_ASSOC);
            if ($row && !empty($row['randomn_id'])) {
                return '/produs?id=' . rawurlencode((string) $row['randomn_id']);
            }

            $stmt = $pdo->prepare('SELECT randomn_id FROM produse WHERE pName LIKE :name LIMIT 1');
            $stmt->execute([':name' => '%' . $name . '%']);
            $row = $stmt->fetch(\PDO::FETCH_ASSOC);
            if ($row && !empty($row['randomn_id'])) {
                return '/produs?id=' . rawurlencode((string) $row['randomn_id']);
            }
        }

        return '/catalog?q=' . rawurlencode($name !== '' ? $name : $oem);
    }

    function shop_auth_order_payment_info(string $paymentStatus, string $orderStatus): array
    {
        $methodMap = [
            'ramburs' => 'Ramburs (la livrare)',
            'card_online' => 'Card online',
            'card_fizic' => 'Card fizic (POS)',
            'numerar' => 'Numerar (la ridicare)',
            'confirmata' => 'Plată confirmată',
            'esuata' => 'Plată eșuată',
            'platita' => 'Plată confirmată',
        ];

        $methodLabel = $methodMap[$paymentStatus] ?? ucfirst(str_replace('_', ' ', $paymentStatus));

        if ($paymentStatus === 'esuata') {
            return [
                'method_label' => $methodLabel,
                'state' => 'failed',
                'state_label' => 'Plată eșuată',
            ];
        }

        $isPaid = in_array($orderStatus, ['platita', 'expediata', 'finalizata'], true)
            || in_array($paymentStatus, ['confirmata', 'platita'], true);

        if ($isPaid) {
            return [
                'method_label' => $methodLabel,
                'state' => 'paid',
                'state_label' => 'Achitat',
            ];
        }

        return [
            'method_label' => $methodLabel,
            'state' => 'pending',
            'state_label' => 'De achitat',
        ];
    }

    function shop_auth_delivery_method_label(string $method): string
    {
        $map = [
            'ridicare_locala' => 'Ridicare din magazin',
            'tarif_fix' => 'Curier rapid',
        ];

        return $map[$method] ?? ($method !== '' ? ucfirst(str_replace('_', ' ', $method)) : '—');
    }

    function shop_auth_delivery_status_label(string $status): string
    {
        $map = [
            'neexpediata' => 'Neexpediată',
            'in_pregatire' => 'În pregătire',
            'expediata' => 'Expediată',
            'livrata' => 'Livrată',
            'ridicata' => 'Ridicată',
        ];

        return $map[$status] ?? ($status !== '' ? ucfirst(str_replace('_', ' ', $status)) : '—');
    }

    function shop_auth_order_status_label(string $status): string
    {
        $map = [
            'noua' => 'Comandă nouă',
            'in_lucru' => 'În lucru',
            'confirmata' => 'Confirmată',
            'platita' => 'Plătită',
            'expediata' => 'Expediată',
            'finalizata' => 'Finalizată',
            'livrata' => 'Livrată',
            'retur' => 'Retur',
            'anulata' => 'Anulată',
        ];

        return $map[$status] ?? ucfirst(str_replace('_', ' ', $status));
    }
}

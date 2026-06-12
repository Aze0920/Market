<?php
/**
 * 关系型数据存储：业务数据写入 MySQL 独立表，不再使用 data/*.json 或 kn_records JSON  blob。
 */
class RelationalStore {
    private $pdo;
    private $ready = false;

    // 注意：此列表仅包含通用数据表。
    // system_config 通过独立的 kn_system_config 表处理（loadSystemConfig/saveSystemConfig），不在此列表中。
    // membership_levels 通过独立的 kn_membership_levels 表处理，也不在此列表中。
    private $tables = [
        'users',
        'products',
        'orders',
        'comments',
        'messages',
        'deposit_requests',
        'card_codes',
        'payment_configs',
        'payment_orders',
        'withdraw_requests',
        'system_config',
    ];

    public function __construct(PDO $pdo) {
        $this->pdo = $pdo;
        $this->ensureSchema();
        $this->migrateIfNeeded();
        $this->ready = true;
    }

    public function isReady() {
        return $this->ready;
    }

    private function ensureSchema() {
        $statements = [
            "CREATE TABLE IF NOT EXISTS `kn_system_meta` (
                `meta_key` varchar(64) NOT NULL,
                `meta_value` longtext NOT NULL,
                `updated_at` int unsigned NOT NULL DEFAULT 0,
                PRIMARY KEY (`meta_key`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
            "CREATE TABLE IF NOT EXISTS `kn_users` (
                `id` varchar(80) NOT NULL,
                `username` varchar(80) NOT NULL,
                `password` varchar(255) NOT NULL,
                `email` varchar(190) NOT NULL DEFAULT '',
                `balance` decimal(14,2) NOT NULL DEFAULT 0.00,
                `frozen_balance` decimal(14,2) NOT NULL DEFAULT 0.00,
                `role` varchar(20) NOT NULL DEFAULT 'user',
                `membership_level` varchar(50) NOT NULL DEFAULT 'Free',
                `avatar` varchar(255) NOT NULL DEFAULT '',
                `qq_openid` varchar(128) NOT NULL DEFAULT '',
                `qq_nickname` varchar(120) NOT NULL DEFAULT '',
                `qq_bound_at` int unsigned NOT NULL DEFAULT 0,
                `merchant_status` varchar(20) NOT NULL DEFAULT 'none',
                `merchant_rules_accepted` tinyint(1) NOT NULL DEFAULT 0,
                `merchant_rules_accepted_at` int unsigned NOT NULL DEFAULT 0,
                `merchant_opened_once` tinyint(1) NOT NULL DEFAULT 0,
                `merchant_approved_at` int unsigned NOT NULL DEFAULT 0,
                `merchant_reapply_at` int unsigned NOT NULL DEFAULT 0,
                `custom_label_text` varchar(20) NOT NULL DEFAULT '',
                `custom_label_icon` varchar(50) NOT NULL DEFAULT '',
                `custom_label_gradient` varchar(255) NOT NULL DEFAULT '',
                `payment_methods_json` longtext,
                `created_at` int unsigned NOT NULL DEFAULT 0,
                `last_login` int unsigned NOT NULL DEFAULT 0,
                PRIMARY KEY (`id`),
                UNIQUE KEY `uk_users_username` (`username`),
                KEY `idx_users_email` (`email`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
            "CREATE TABLE IF NOT EXISTS `kn_products` (
                `id` varchar(80) NOT NULL,
                `seller_id` varchar(80) NOT NULL,
                `seller_name` varchar(80) NOT NULL DEFAULT '',
                `title` varchar(200) NOT NULL DEFAULT '',
                `category` varchar(80) NOT NULL DEFAULT '',
                `price` decimal(14,2) NOT NULL DEFAULT 0.00,
                `stock` int unsigned NOT NULL DEFAULT 0,
                `sales` int unsigned NOT NULL DEFAULT 0,
                `description` mediumtext,
                `image` varchar(255) NOT NULL DEFAULT '',
                `pickup_password_enabled` tinyint(1) NOT NULL DEFAULT 0,
                `pickup_password` varchar(255) NOT NULL DEFAULT '',
                `account_list_json` longtext NOT NULL,
                `created_at` int unsigned NOT NULL DEFAULT 0,
                `updated_at` int unsigned NOT NULL DEFAULT 0,
                PRIMARY KEY (`id`),
                KEY `idx_products_seller` (`seller_id`),
                KEY `idx_products_category` (`category`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
            "CREATE TABLE IF NOT EXISTS `kn_orders` (
                `id` varchar(80) NOT NULL,
                `buyer_id` varchar(80) NOT NULL DEFAULT '',
                `buyer_name` varchar(80) NOT NULL DEFAULT '',
                `seller_id` varchar(80) NOT NULL DEFAULT '',
                `seller_name` varchar(80) NOT NULL DEFAULT '',
                `product_id` varchar(80) NOT NULL DEFAULT '',
                `product_title` varchar(200) NOT NULL DEFAULT '',
                `price` decimal(14,2) NOT NULL DEFAULT 0.00,
                `unit_price` decimal(14,2) NOT NULL DEFAULT 0.00,
                `quantity` int unsigned NOT NULL DEFAULT 1,
                `fee` decimal(14,2) NOT NULL DEFAULT 0.00,
                `seller_amount` decimal(14,2) NOT NULL DEFAULT 0.00,
                `pay_method` varchar(50) NOT NULL DEFAULT '',
                `guest_order` tinyint(1) NOT NULL DEFAULT 0,
                `guest_token` varchar(120) NOT NULL DEFAULT '',
                `guest_email` varchar(190) NOT NULL DEFAULT '',
                `guest_query_code` char(8) NOT NULL DEFAULT '',
                `balance_frozen` tinyint(1) NOT NULL DEFAULT 0,
                `frozen_amount` decimal(14,2) NOT NULL DEFAULT 0.00,
                `frozen_released_at` int unsigned NOT NULL DEFAULT 0,
                `complaint_withdrawn_at` int unsigned NOT NULL DEFAULT 0,
                `purchase_date` int unsigned NOT NULL DEFAULT 0,
                `delivery_info_json` longtext,
                `complaint_json` longtext,
                PRIMARY KEY (`id`),
                KEY `idx_orders_buyer` (`buyer_id`),
                KEY `idx_orders_seller` (`seller_id`),
                KEY `idx_orders_product` (`product_id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
            "CREATE TABLE IF NOT EXISTS `kn_comments` (
                `id` varchar(80) NOT NULL,
                `user_id` varchar(80) NOT NULL DEFAULT '',
                `username` varchar(80) NOT NULL DEFAULT '',
                `product_id` varchar(80) NOT NULL DEFAULT '',
                `order_id` varchar(80) NOT NULL DEFAULT '',
                `rating` varchar(20) NOT NULL DEFAULT 'good',
                `content` text,
                `created_at` int unsigned NOT NULL DEFAULT 0,
                PRIMARY KEY (`id`),
                KEY `idx_comments_product` (`product_id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
            "CREATE TABLE IF NOT EXISTS `kn_messages` (
                `id` varchar(80) NOT NULL,
                `msg_from` varchar(80) NOT NULL DEFAULT '',
                `msg_to` varchar(80) NOT NULL DEFAULT '',
                `content` text,
                `is_read` tinyint(1) NOT NULL DEFAULT 0,
                `created_at` int unsigned NOT NULL DEFAULT 0,
                PRIMARY KEY (`id`),
                KEY `idx_messages_from` (`msg_from`),
                KEY `idx_messages_to` (`msg_to`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
            "CREATE TABLE IF NOT EXISTS `kn_deposit_requests` (
                `id` varchar(80) NOT NULL,
                `user_id` varchar(80) NOT NULL DEFAULT '',
                `username` varchar(80) NOT NULL DEFAULT '',
                `amount` decimal(14,2) NOT NULL DEFAULT 0.00,
                `request_type` varchar(20) NOT NULL DEFAULT 'deposit',
                `status` varchar(20) NOT NULL DEFAULT 'pending',
                `created_at` int unsigned NOT NULL DEFAULT 0,
                PRIMARY KEY (`id`),
                KEY `idx_deposit_user` (`user_id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
            "CREATE TABLE IF NOT EXISTS `kn_card_codes` (
                `id` varchar(80) NOT NULL,
                `code` varchar(64) NOT NULL,
                `amount` decimal(14,2) NOT NULL DEFAULT 0.00,
                `card_type` varchar(20) NOT NULL DEFAULT 'balance',
                `target_level` varchar(50) NOT NULL DEFAULT '',
                `is_used` tinyint(1) NOT NULL DEFAULT 0,
                `used_by` varchar(80) DEFAULT NULL,
                `used_at` int unsigned DEFAULT NULL,
                `created_by` varchar(80) NOT NULL DEFAULT '',
                `created_at` int unsigned NOT NULL DEFAULT 0,
                PRIMARY KEY (`id`),
                UNIQUE KEY `uk_card_code` (`code`),
                KEY `idx_card_used` (`is_used`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
            "CREATE TABLE IF NOT EXISTS `kn_payment_configs` (
                `id` varchar(80) NOT NULL,
                `name` varchar(120) NOT NULL DEFAULT '',
                `type` varchar(30) NOT NULL DEFAULT 'yipay',
                `api_url` varchar(255) NOT NULL DEFAULT '',
                `partner_id` varchar(120) NOT NULL DEFAULT '',
                `secret_key` varchar(255) NOT NULL DEFAULT '',
                `fee_rate` decimal(10,4) NOT NULL DEFAULT 0.0000,
                `enabled` tinyint(1) NOT NULL DEFAULT 1,
                `pay_methods_json` longtext,
                `submit_mode` varchar(30) NOT NULL DEFAULT 'url_redirect',
                `api_mode` varchar(30) NOT NULL DEFAULT 'submit_page',
                `sort_order` int NOT NULL DEFAULT 0,
                `remark` varchar(255) NOT NULL DEFAULT '',
                `created_at` int unsigned NOT NULL DEFAULT 0,
                PRIMARY KEY (`id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
            "CREATE TABLE IF NOT EXISTS `kn_payment_orders` (
                `id` varchar(80) NOT NULL,
                `trade_no` varchar(80) NOT NULL DEFAULT '',
                `user_id` varchar(80) NOT NULL DEFAULT '',
                `payment_config_id` varchar(80) NOT NULL DEFAULT '',
                `pay_type` varchar(30) NOT NULL DEFAULT '',
                `amount` decimal(14,2) NOT NULL DEFAULT 0.00,
                `actual_amount` decimal(14,2) NOT NULL DEFAULT 0.00,
                `fee` decimal(14,2) NOT NULL DEFAULT 0.00,
                `status` varchar(20) NOT NULL DEFAULT 'pending',
                `order_type` varchar(30) NOT NULL DEFAULT 'recharge',
                `title` varchar(200) NOT NULL DEFAULT '',
                `description` varchar(255) NOT NULL DEFAULT '',
                `target_level` varchar(50) NOT NULL DEFAULT '',
                `product_id` varchar(80) NOT NULL DEFAULT '',
                `quantity` int unsigned NOT NULL DEFAULT 0,
                `pickup_password_hash` varchar(255) NOT NULL DEFAULT '',
                `guest_token` varchar(120) NOT NULL DEFAULT '',
                `guest_order` tinyint(1) NOT NULL DEFAULT 0,
                `guest_email` varchar(190) NOT NULL DEFAULT '',
                `guest_query_code` char(8) NOT NULL DEFAULT '',
                `buyer_name` varchar(80) NOT NULL DEFAULT '',
                `related_id` varchar(80) NOT NULL DEFAULT '',
                `delivery_status` varchar(30) NOT NULL DEFAULT '',
                `delivery_error` varchar(255) NOT NULL DEFAULT '',
                `refund_applied` tinyint(1) NOT NULL DEFAULT 0,
                `refunded_amount` decimal(14,2) NOT NULL DEFAULT 0.00,
                `refunded_at` int unsigned DEFAULT NULL,
                `created_at` int unsigned NOT NULL DEFAULT 0,
                `paid_at` int unsigned DEFAULT NULL,
                PRIMARY KEY (`id`),
                UNIQUE KEY `uk_payment_trade_no` (`trade_no`),
                KEY `idx_payment_user` (`user_id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
            "CREATE TABLE IF NOT EXISTS `kn_withdraw_requests` (
                `id` varchar(80) NOT NULL,
                `user_id` varchar(80) NOT NULL DEFAULT '',
                `username` varchar(80) NOT NULL DEFAULT '',
                `amount` decimal(14,2) NOT NULL DEFAULT 0.00,
                `actual_amount` decimal(14,2) NOT NULL DEFAULT 0.00,
                `fee` decimal(14,2) NOT NULL DEFAULT 0.00,
                `payment_method` varchar(30) NOT NULL DEFAULT '',
                `payment_account` varchar(120) NOT NULL DEFAULT '',
                `qrcode_url` varchar(255) NOT NULL DEFAULT '',
                `status` varchar(20) NOT NULL DEFAULT 'pending',
                `admin_note` varchar(255) NOT NULL DEFAULT '',
                `processed_by` varchar(80) DEFAULT NULL,
                `processed_at` int unsigned DEFAULT NULL,
                `created_at` int unsigned NOT NULL DEFAULT 0,
                `deadline` int unsigned NOT NULL DEFAULT 0,
                PRIMARY KEY (`id`),
                KEY `idx_withdraw_user` (`user_id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
            "CREATE TABLE IF NOT EXISTS `kn_system_config` (
                `config_key` varchar(64) NOT NULL DEFAULT 'default',
                `config_json` longtext NOT NULL,
                `updated_at` int unsigned NOT NULL DEFAULT 0,
                PRIMARY KEY (`config_key`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        ];
        foreach ($statements as $sql) {
            $this->pdo->exec($sql);
        }
        $this->ensureColumn('kn_orders', 'guest_email', "varchar(190) NOT NULL DEFAULT ''");
        $this->ensureColumn('kn_orders', 'guest_query_code', "char(8) NOT NULL DEFAULT ''");
        $this->ensureColumn('kn_orders', 'payment_trade_no', "varchar(80) NOT NULL DEFAULT ''");
        $this->ensureColumn('kn_payment_orders', 'guest_email', "varchar(190) NOT NULL DEFAULT ''");
        $this->ensureColumn('kn_payment_orders', 'guest_query_code', "char(8) NOT NULL DEFAULT ''");
        $this->ensureColumn('kn_payment_orders', 'refund_applied', "tinyint(1) NOT NULL DEFAULT 0");
        $this->ensureColumn('kn_payment_orders', 'refunded_amount', "decimal(14,2) NOT NULL DEFAULT 0.00");
        $this->ensureColumn('kn_payment_orders', 'refunded_at', "int unsigned DEFAULT NULL");
        $this->ensureColumn('kn_payment_orders', 'balance_applied', "tinyint(1) NOT NULL DEFAULT 0");
        $this->ensureColumn('kn_card_codes', 'card_type', "varchar(20) NOT NULL DEFAULT 'balance'");
        $this->ensureColumn('kn_card_codes', 'target_level', "varchar(50) NOT NULL DEFAULT ''");
        $this->ensureSignedDecimalColumn('kn_users', 'balance', 'decimal(14,2)', '0.00');
        $this->ensureSignedDecimalColumn('kn_users', 'frozen_balance', 'decimal(14,2)', '0.00');
    }

    private function ensureSignedDecimalColumn($table, $column, $type, $default) {
        if (!preg_match('/^kn_[a-z0-9_]+$/', (string)$table) || !preg_match('/^[a-z0-9_]+$/', (string)$column)) {
            return;
        }
        $stmt = $this->pdo->prepare('SELECT COLUMN_TYPE, IS_NULLABLE, COLUMN_DEFAULT FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ? LIMIT 1');
        $stmt->execute([$table, $column]);
        $row = $stmt->fetch();
        if (!$row) {
            return;
        }
        $columnType = strtolower((string)($row['COLUMN_TYPE'] ?? ''));
        if (strpos($columnType, 'unsigned') !== false) {
            $this->pdo->exec('ALTER TABLE `' . $table . '` MODIFY COLUMN `' . $column . '` ' . $type . " NOT NULL DEFAULT " . $this->pdo->quote($default));
        }
    }

    private function ensureColumn($table, $column, $definition) {
        if (!preg_match('/^kn_[a-z0-9_]+$/', (string)$table) || !preg_match('/^[a-z0-9_]+$/', (string)$column)) {
            return;
        }
        $stmt = $this->pdo->prepare('SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?');
        $stmt->execute([$table, $column]);
        if ((int)$stmt->fetchColumn() === 0) {
            $this->pdo->exec('ALTER TABLE `' . $table . '` ADD COLUMN `' . $column . '` ' . $definition);
        }
    }

    private function getMeta($key, $default = '') {
        $stmt = $this->pdo->prepare('SELECT meta_value FROM kn_system_meta WHERE meta_key = ? LIMIT 1');
        $stmt->execute([$key]);
        $row = $stmt->fetch();
        return $row ? (string)$row['meta_value'] : $default;
    }

    private function setMeta($key, $value) {
        $stmt = $this->pdo->prepare(
            'INSERT INTO kn_system_meta (meta_key, meta_value, updated_at) VALUES (?, ?, ?)
             ON DUPLICATE KEY UPDATE meta_value = VALUES(meta_value), updated_at = VALUES(updated_at)'
        );
        return $stmt->execute([$key, $value, time()]);
    }

    private function migrateIfNeeded() {
        if ($this->getMeta('storage_engine') === 'relational_v1') {
            return;
        }

        $this->pdo->beginTransaction();
        try {
            $legacyPath = dirname(__DIR__) . '/data';
            $imported = false;

            foreach ($this->tables as $table) {
                if ($this->countLegacyRecords($table) > 0) {
                    $this->importLegacyRecordsTable($table);
                    $imported = true;
                    continue;
                }
                $file = $legacyPath . '/' . $table . '.json';
                if (is_file($file)) {
                    $this->importLegacyJsonFile($table, $file);
                    $imported = true;
                }
            }

            if ($this->countLegacyRecords('system_config') > 0) {
                $config = $this->loadLegacySystemConfig();
                if (!empty($config)) {
                    $this->saveSystemConfig($config);
                    $imported = true;
                }
            } else {
                $configFile = $legacyPath . '/system_config.json';
                if (is_file($configFile)) {
                    $content = file_get_contents($configFile);
                    $config = json_decode((string)$content, true);
                    if (is_array($config) && !empty($config)) {
                        $this->saveSystemConfig($config);
                        $imported = true;
                    }
                }
            }

            if (!$imported && $this->countRelationalUsers() === 0) {
                // 新库：保持空表，由 Database 初始化 admin
            }

            $this->setMeta('storage_engine', 'relational_v1');
            $this->setMeta('migrated_at', (string)time());
            $this->pdo->commit();
        } catch (Throwable $e) {
            $this->pdo->rollBack();
            throw $e;
        }
    }

    private function countRelationalUsers() {
        return (int)$this->pdo->query('SELECT COUNT(*) FROM kn_users')->fetchColumn();
    }

    private function countLegacyRecords($table) {
        $stmt = $this->pdo->prepare('SELECT COUNT(*) FROM kn_records WHERE table_name = ?');
        $stmt->execute([$table]);
        return (int)$stmt->fetchColumn();
    }

    private function loadLegacySystemConfig() {
        $stmt = $this->pdo->prepare('SELECT data FROM kn_records WHERE table_name = ? AND record_id = ? LIMIT 1');
        $stmt->execute(['system_config', 'default']);
        $row = $stmt->fetch();
        if (!$row) {
            return [];
        }
        $config = json_decode($row['data'], true);
        return is_array($config) ? $config : [];
    }

    private function importLegacyRecordsTable($table) {
        $stmt = $this->pdo->prepare('SELECT data FROM kn_records WHERE table_name = ? ORDER BY created_at ASC');
        $stmt->execute([$table]);
        while ($row = $stmt->fetch()) {
            $record = json_decode($row['data'], true);
            if (is_array($record) && !empty($record['id'])) {
                $this->saveRecord($table, $record);
            }
        }
    }

    private function importLegacyJsonFile($table, $file) {
        $content = file_get_contents($file);
        $legacyData = json_decode((string)$content, true);
        if (json_last_error() !== JSON_ERROR_NONE || !is_array($legacyData)) {
            return;
        }
        if ($table === 'system_config') {
            $this->saveSystemConfig($legacyData);
            return;
        }
        foreach ($legacyData as $record) {
            if (is_array($record) && !empty($record['id'])) {
                $this->saveRecord($table, $record);
            }
        }
    }

    private function encodeJson($value) {
        $json = json_encode($value ?? [], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE);
        return $json === false ? '[]' : $json;
    }

    private function decodeJson($value, $default = []) {
        if ($value === null || $value === '') {
            return $default;
        }
        $decoded = json_decode((string)$value, true);
        return is_array($decoded) ? $decoded : $default;
    }

    public function loadTable($table) {
        switch ($table) {
            case 'users':
                $stmt = $this->pdo->query('SELECT * FROM kn_users ORDER BY created_at ASC');
                $rows = [];
                while ($row = $stmt->fetch()) {
                    $rows[] = $this->rowToUser($row);
                }
                return $rows;
            case 'products':
                $stmt = $this->pdo->query('SELECT * FROM kn_products ORDER BY created_at ASC');
                $rows = [];
                while ($row = $stmt->fetch()) {
                    $rows[] = $this->rowToProduct($row);
                }
                return $rows;
            case 'orders':
                $stmt = $this->pdo->query('SELECT * FROM kn_orders ORDER BY purchase_date ASC');
                $rows = [];
                while ($row = $stmt->fetch()) {
                    $rows[] = $this->rowToOrder($row);
                }
                return $rows;
            case 'comments':
                $stmt = $this->pdo->query('SELECT * FROM kn_comments ORDER BY created_at ASC');
                $rows = [];
                while ($row = $stmt->fetch()) {
                    $rows[] = $this->rowToComment($row);
                }
                return $rows;
            case 'messages':
                $stmt = $this->pdo->query('SELECT * FROM kn_messages ORDER BY created_at ASC');
                $rows = [];
                while ($row = $stmt->fetch()) {
                    $rows[] = $this->rowToMessage($row);
                }
                return $rows;
            case 'deposit_requests':
                $stmt = $this->pdo->query('SELECT * FROM kn_deposit_requests ORDER BY created_at ASC');
                $rows = [];
                while ($row = $stmt->fetch()) {
                    $rows[] = $this->rowToDepositRequest($row);
                }
                return $rows;
            case 'card_codes':
                $stmt = $this->pdo->query('SELECT * FROM kn_card_codes ORDER BY created_at ASC');
                $rows = [];
                while ($row = $stmt->fetch()) {
                    $rows[] = $this->rowToCardCode($row);
                }
                return $rows;
            case 'payment_configs':
                $stmt = $this->pdo->query('SELECT * FROM kn_payment_configs ORDER BY sort_order ASC, created_at ASC');
                $rows = [];
                while ($row = $stmt->fetch()) {
                    $rows[] = $this->rowToPaymentConfig($row);
                }
                return $rows;
            case 'payment_orders':
                $stmt = $this->pdo->query('SELECT * FROM kn_payment_orders ORDER BY created_at ASC');
                $rows = [];
                while ($row = $stmt->fetch()) {
                    $rows[] = $this->rowToPaymentOrder($row);
                }
                return $rows;
            case 'withdraw_requests':
                $stmt = $this->pdo->query('SELECT * FROM kn_withdraw_requests ORDER BY created_at ASC');
                $rows = [];
                while ($row = $stmt->fetch()) {
                    $rows[] = $this->rowToWithdrawRequest($row);
                }
                return $rows;
            default:
                return [];
        }
    }

    public function loadSystemConfig() {
        $stmt = $this->pdo->prepare('SELECT config_json FROM kn_system_config WHERE config_key = ? LIMIT 1');
        $stmt->execute(['default']);
        $row = $stmt->fetch();
        if (!$row) {
            return [];
        }
        return $this->decodeJson($row['config_json'], []);
    }

    public function saveSystemConfig(array $config) {
        $stmt = $this->pdo->prepare(
            'INSERT INTO kn_system_config (config_key, config_json, updated_at) VALUES (?, ?, ?)
             ON DUPLICATE KEY UPDATE config_json = VALUES(config_json), updated_at = VALUES(updated_at)'
        );
        return $stmt->execute(['default', $this->encodeJson($config), time()]);
    }

    public function saveRecord($table, array $record) {
        if (empty($record['id'])) {
            return false;
        }
        switch ($table) {
            case 'users': return $this->upsertUser($record);
            case 'products': return $this->upsertProduct($record);
            case 'orders': return $this->upsertOrder($record);
            case 'comments': return $this->upsertComment($record);
            case 'messages': return $this->upsertMessage($record);
            case 'deposit_requests': return $this->upsertDepositRequest($record);
            case 'card_codes': return $this->upsertCardCode($record);
            case 'payment_configs': return $this->upsertPaymentConfig($record);
            case 'payment_orders': return $this->upsertPaymentOrder($record);
            case 'withdraw_requests': return $this->upsertWithdrawRequest($record);
            default: return false;
        }
    }

    public function deleteRecord($table, $id) {
        $map = [
            'users' => 'kn_users',
            'products' => 'kn_products',
            'orders' => 'kn_orders',
            'comments' => 'kn_comments',
            'messages' => 'kn_messages',
            'deposit_requests' => 'kn_deposit_requests',
            'card_codes' => 'kn_card_codes',
            'payment_configs' => 'kn_payment_configs',
            'payment_orders' => 'kn_payment_orders',
            'withdraw_requests' => 'kn_withdraw_requests',
        ];
        if (!isset($map[$table])) {
            return false;
        }
        $stmt = $this->pdo->prepare('DELETE FROM `' . $map[$table] . '` WHERE id = ?');
        return $stmt->execute([$id]);
    }

    public function saveTable($table, array $records) {
        $map = [
            'users' => 'kn_users',
            'products' => 'kn_products',
            'orders' => 'kn_orders',
            'comments' => 'kn_comments',
            'messages' => 'kn_messages',
            'deposit_requests' => 'kn_deposit_requests',
            'card_codes' => 'kn_card_codes',
            'payment_configs' => 'kn_payment_configs',
            'payment_orders' => 'kn_payment_orders',
            'withdraw_requests' => 'kn_withdraw_requests',
        ];
        if (!isset($map[$table])) {
            return false;
        }
        $this->pdo->beginTransaction();
        try {
            $this->pdo->exec('DELETE FROM `' . $map[$table] . '`');
            foreach ($records as $record) {
                if (!is_array($record)) {
                    continue;
                }
                $this->saveRecord($table, $record);
            }
            $this->pdo->commit();
            return true;
        } catch (Throwable $e) {
            $this->pdo->rollBack();
            return false;
        }
    }

    private function rowToUser(array $row) {
        $user = [
            'id' => $row['id'],
            'username' => $row['username'],
            'password' => $row['password'],
            'email' => $row['email'],
            'balance' => floatval($row['balance']),
            'frozen_balance' => floatval($row['frozen_balance']),
            'role' => $row['role'],
            'membership_level' => $row['membership_level'],
            'avatar' => $row['avatar'],
            'qq_openid' => $row['qq_openid'],
            'qq_nickname' => $row['qq_nickname'],
            'qq_bound_at' => intval($row['qq_bound_at']),
            'merchant_status' => $row['merchant_status'],
            'merchant_rules_accepted' => !empty($row['merchant_rules_accepted']),
            'merchant_rules_accepted_at' => intval($row['merchant_rules_accepted_at']),
            'merchant_opened_once' => !empty($row['merchant_opened_once']),
            'merchant_approved_at' => intval($row['merchant_approved_at']),
            'merchant_reapply_at' => intval($row['merchant_reapply_at']),
            'custom_label_text' => $row['custom_label_text'],
            'custom_label_icon' => $row['custom_label_icon'],
            'custom_label_gradient' => $row['custom_label_gradient'],
            'payment_methods' => $this->decodeJson($row['payment_methods_json'], []),
            'created_at' => intval($row['created_at']),
            'last_login' => intval($row['last_login']),
        ];
        return $user;
    }

    private function upsertUser(array $user) {
        $stmt = $this->pdo->prepare(
            'INSERT INTO kn_users (id, username, password, email, balance, frozen_balance, role, membership_level, avatar, qq_openid, qq_nickname, qq_bound_at, merchant_status, merchant_rules_accepted, merchant_rules_accepted_at, merchant_opened_once, merchant_approved_at, merchant_reapply_at, custom_label_text, custom_label_icon, custom_label_gradient, payment_methods_json, created_at, last_login)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE username = VALUES(username), password = VALUES(password), email = VALUES(email), balance = VALUES(balance), frozen_balance = VALUES(frozen_balance), role = VALUES(role), membership_level = VALUES(membership_level), avatar = VALUES(avatar), qq_openid = VALUES(qq_openid), qq_nickname = VALUES(qq_nickname), qq_bound_at = VALUES(qq_bound_at), merchant_status = VALUES(merchant_status), merchant_rules_accepted = VALUES(merchant_rules_accepted), merchant_rules_accepted_at = VALUES(merchant_rules_accepted_at), merchant_opened_once = VALUES(merchant_opened_once), merchant_approved_at = VALUES(merchant_approved_at), merchant_reapply_at = VALUES(merchant_reapply_at), custom_label_text = VALUES(custom_label_text), custom_label_icon = VALUES(custom_label_icon), custom_label_gradient = VALUES(custom_label_gradient), payment_methods_json = VALUES(payment_methods_json), created_at = VALUES(created_at), last_login = VALUES(last_login)'
        );
        return $stmt->execute([
            $user['id'],
            $user['username'] ?? '',
            $user['password'] ?? '',
            $user['email'] ?? '',
            floatval($user['balance'] ?? 0),
            floatval($user['frozen_balance'] ?? 0),
            ($user['role'] ?? '') === 'admin' ? 'admin' : 'user',
            $user['membership_level'] ?? 'Free',
            $user['avatar'] ?? '',
            $user['qq_openid'] ?? '',
            $user['qq_nickname'] ?? '',
            intval($user['qq_bound_at'] ?? 0),
            $user['merchant_status'] ?? 'none',
            !empty($user['merchant_rules_accepted']) ? 1 : 0,
            intval($user['merchant_rules_accepted_at'] ?? 0),
            !empty($user['merchant_opened_once']) ? 1 : 0,
            intval($user['merchant_approved_at'] ?? 0),
            intval($user['merchant_reapply_at'] ?? 0),
            $user['custom_label_text'] ?? '',
            $user['custom_label_icon'] ?? '',
            $user['custom_label_gradient'] ?? '',
            $this->encodeJson($user['payment_methods'] ?? []),
            intval($user['created_at'] ?? time()),
            intval($user['last_login'] ?? 0),
        ]);
    }

    private function rowToProduct(array $row) {
        return [
            'id' => $row['id'],
            'seller_id' => $row['seller_id'],
            'seller_name' => $row['seller_name'],
            'title' => $row['title'],
            'category' => $row['category'],
            'price' => floatval($row['price']),
            'stock' => intval($row['stock']),
            'sales' => intval($row['sales']),
            'description' => $row['description'] ?? '',
            'image' => $row['image'] ?? '',
            'pickup_password_enabled' => !empty($row['pickup_password_enabled']),
            'pickup_password' => $row['pickup_password'] ?? '',
            'account_list' => $this->decodeJson($row['account_list_json'], []),
            'created_at' => intval($row['created_at']),
            'updated_at' => intval($row['updated_at']),
        ];
    }

    private function upsertProduct(array $product) {
        $stmt = $this->pdo->prepare(
            'INSERT INTO kn_products (id, seller_id, seller_name, title, category, price, stock, sales, description, image, pickup_password_enabled, pickup_password, account_list_json, created_at, updated_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE seller_id = VALUES(seller_id), seller_name = VALUES(seller_name), title = VALUES(title), category = VALUES(category), price = VALUES(price), stock = VALUES(stock), sales = VALUES(sales), description = VALUES(description), image = VALUES(image), pickup_password_enabled = VALUES(pickup_password_enabled), pickup_password = VALUES(pickup_password), account_list_json = VALUES(account_list_json), created_at = VALUES(created_at), updated_at = VALUES(updated_at)'
        );
        return $stmt->execute([
            $product['id'],
            $product['seller_id'] ?? '',
            $product['seller_name'] ?? '',
            $product['title'] ?? '',
            $product['category'] ?? '',
            floatval($product['price'] ?? 0),
            intval($product['stock'] ?? 0),
            intval($product['sales'] ?? 0),
            $product['description'] ?? '',
            $product['image'] ?? '',
            !empty($product['pickup_password_enabled']) ? 1 : 0,
            $product['pickup_password'] ?? '',
            $this->encodeJson($product['account_list'] ?? []),
            intval($product['created_at'] ?? time()),
            intval($product['updated_at'] ?? 0),
        ]);
    }

    private function rowToOrder(array $row) {
        $order = [
            'id' => $row['id'],
            'buyer_id' => $row['buyer_id'],
            'buyer_name' => $row['buyer_name'],
            'seller_id' => $row['seller_id'],
            'seller_name' => $row['seller_name'],
            'product_id' => $row['product_id'],
            'product_title' => $row['product_title'],
            'price' => floatval($row['price']),
            'unit_price' => floatval($row['unit_price']),
            'quantity' => intval($row['quantity']),
            'fee' => floatval($row['fee']),
            'seller_amount' => floatval($row['seller_amount']),
            'pay_method' => $row['pay_method'],
            'guest_order' => !empty($row['guest_order']),
            'guest_token' => $row['guest_token'],
            'guest_email' => $row['guest_email'] ?? '',
            'guest_query_code' => $row['guest_query_code'] ?? '',
            'balance_frozen' => !empty($row['balance_frozen']),
            'frozen_amount' => floatval($row['frozen_amount']),
            'frozen_released_at' => intval($row['frozen_released_at']),
            'complaint_withdrawn_at' => intval($row['complaint_withdrawn_at']),
            'purchase_date' => intval($row['purchase_date']),
            'payment_trade_no' => $row['payment_trade_no'] ?? '',
            'delivery_info' => $this->decodeJson($row['delivery_info_json'], []),
        ];
        $complaint = $this->decodeJson($row['complaint_json'], []);
        if (!empty($complaint)) {
            $order['complaint'] = $complaint;
        }
        return $order;
    }

    private function upsertOrder(array $order) {
        $complaint = $order['complaint'] ?? [];
        $stmt = $this->pdo->prepare(
            'INSERT INTO kn_orders (id, buyer_id, buyer_name, seller_id, seller_name, product_id, product_title, price, unit_price, quantity, fee, seller_amount, pay_method, guest_order, guest_token, guest_email, guest_query_code, balance_frozen, frozen_amount, frozen_released_at, complaint_withdrawn_at, purchase_date, payment_trade_no, delivery_info_json, complaint_json)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE buyer_id = VALUES(buyer_id), buyer_name = VALUES(buyer_name), seller_id = VALUES(seller_id), seller_name = VALUES(seller_name), product_id = VALUES(product_id), product_title = VALUES(product_title), price = VALUES(price), unit_price = VALUES(unit_price), quantity = VALUES(quantity), fee = VALUES(fee), seller_amount = VALUES(seller_amount), pay_method = VALUES(pay_method), guest_order = VALUES(guest_order), guest_token = VALUES(guest_token), guest_email = VALUES(guest_email), guest_query_code = VALUES(guest_query_code), balance_frozen = VALUES(balance_frozen), frozen_amount = VALUES(frozen_amount), frozen_released_at = VALUES(frozen_released_at), complaint_withdrawn_at = VALUES(complaint_withdrawn_at), purchase_date = VALUES(purchase_date), payment_trade_no = VALUES(payment_trade_no), delivery_info_json = VALUES(delivery_info_json), complaint_json = VALUES(complaint_json)'
        );
        return $stmt->execute([
            $order['id'],
            $order['buyer_id'] ?? '',
            $order['buyer_name'] ?? '',
            $order['seller_id'] ?? '',
            $order['seller_name'] ?? '',
            $order['product_id'] ?? '',
            $order['product_title'] ?? '',
            floatval($order['price'] ?? 0),
            floatval($order['unit_price'] ?? 0),
            intval($order['quantity'] ?? 1),
            floatval($order['fee'] ?? 0),
            floatval($order['seller_amount'] ?? 0),
            $order['pay_method'] ?? '',
            !empty($order['guest_order']) ? 1 : 0,
            $order['guest_token'] ?? '',
            $order['guest_email'] ?? '',
            $order['guest_query_code'] ?? '',
            !empty($order['balance_frozen']) ? 1 : 0,
            floatval($order['frozen_amount'] ?? 0),
            intval($order['frozen_released_at'] ?? 0),
            intval($order['complaint_withdrawn_at'] ?? 0),
            intval($order['purchase_date'] ?? time()),
            $order['payment_trade_no'] ?? '',
            $this->encodeJson($order['delivery_info'] ?? []),
            $this->encodeJson($complaint),
        ]);
    }

    private function rowToComment(array $row) {
        return [
            'id' => $row['id'],
            'user_id' => $row['user_id'],
            'username' => $row['username'],
            'product_id' => $row['product_id'],
            'order_id' => $row['order_id'],
            'rating' => $row['rating'],
            'content' => $row['content'],
            'created_at' => intval($row['created_at']),
        ];
    }

    private function upsertComment(array $comment) {
        $stmt = $this->pdo->prepare(
            'INSERT INTO kn_comments (id, user_id, username, product_id, order_id, rating, content, created_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE user_id = VALUES(user_id), username = VALUES(username), product_id = VALUES(product_id), order_id = VALUES(order_id), rating = VALUES(rating), content = VALUES(content), created_at = VALUES(created_at)'
        );
        return $stmt->execute([
            $comment['id'],
            $comment['user_id'] ?? '',
            $comment['username'] ?? '',
            $comment['product_id'] ?? '',
            $comment['order_id'] ?? '',
            intval($comment['rating'] ?? 0),
            $comment['content'] ?? '',
            intval($comment['created_at'] ?? time()),
        ]);
    }

    private function rowToMessage(array $row) {
        return [
            'id' => $row['id'],
            'from' => $row['msg_from'],
            'to' => $row['msg_to'],
            'content' => $row['content'],
            'read' => !empty($row['is_read']),
            'created_at' => intval($row['created_at']),
        ];
    }

    private function upsertMessage(array $message) {
        $stmt = $this->pdo->prepare(
            'INSERT INTO kn_messages (id, msg_from, msg_to, content, is_read, created_at)
             VALUES (?, ?, ?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE msg_from = VALUES(msg_from), msg_to = VALUES(msg_to), content = VALUES(content), is_read = VALUES(is_read), created_at = VALUES(created_at)'
        );
        return $stmt->execute([
            $message['id'],
            $message['from'] ?? '',
            $message['to'] ?? '',
            $message['content'] ?? '',
            !empty($message['read']) ? 1 : 0,
            intval($message['created_at'] ?? time()),
        ]);
    }

    private function rowToDepositRequest(array $row) {
        return [
            'id' => $row['id'],
            'user_id' => $row['user_id'],
            'username' => $row['username'],
            'amount' => floatval($row['amount']),
            'type' => $row['request_type'],
            'status' => $row['status'],
            'created_at' => intval($row['created_at']),
        ];
    }

    private function upsertDepositRequest(array $request) {
        $stmt = $this->pdo->prepare(
            'INSERT INTO kn_deposit_requests (id, user_id, username, amount, request_type, status, created_at)
             VALUES (?, ?, ?, ?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE user_id = VALUES(user_id), username = VALUES(username), amount = VALUES(amount), request_type = VALUES(request_type), status = VALUES(status), created_at = VALUES(created_at)'
        );
        return $stmt->execute([
            $request['id'],
            $request['user_id'] ?? '',
            $request['username'] ?? '',
            floatval($request['amount'] ?? 0),
            $request['type'] ?? 'deposit',
            $request['status'] ?? 'pending',
            intval($request['created_at'] ?? time()),
        ]);
    }

    private function rowToCardCode(array $row) {
        return [
            'id' => $row['id'],
            'code' => $row['code'],
            'amount' => floatval($row['amount']),
            'card_type' => $row['card_type'] ?? 'balance',
            'target_level' => $row['target_level'] ?? '',
            'used' => !empty($row['is_used']),
            'used_by' => $row['used_by'],
            'used_at' => $row['used_at'] !== null ? intval($row['used_at']) : null,
            'created_by' => $row['created_by'],
            'created_at' => intval($row['created_at']),
        ];
    }

    private function upsertCardCode(array $card) {
        $cardType = ($card['card_type'] ?? 'balance') === 'membership' ? 'membership' : 'balance';
        $stmt = $this->pdo->prepare(
            'INSERT INTO kn_card_codes (id, code, amount, card_type, target_level, is_used, used_by, used_at, created_by, created_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE code = VALUES(code), amount = VALUES(amount), card_type = VALUES(card_type), target_level = VALUES(target_level), is_used = VALUES(is_used), used_by = VALUES(used_by), used_at = VALUES(used_at), created_by = VALUES(created_by), created_at = VALUES(created_at)'
        );
        return $stmt->execute([
            $card['id'],
            $card['code'] ?? '',
            floatval($card['amount'] ?? 0),
            $cardType,
            $cardType === 'membership' ? trim((string)($card['target_level'] ?? '')) : '',
            !empty($card['used']) ? 1 : 0,
            $card['used_by'] ?? null,
            isset($card['used_at']) ? intval($card['used_at']) : null,
            $card['created_by'] ?? '',
            intval($card['created_at'] ?? time()),
        ]);
    }

    private function rowToPaymentConfig(array $row) {
        return [
            'id' => $row['id'],
            'name' => $row['name'],
            'type' => $row['type'],
            'api_url' => $row['api_url'],
            'partner_id' => $row['partner_id'],
            'key' => $row['secret_key'],
            'fee_rate' => floatval($row['fee_rate']),
            'enabled' => !empty($row['enabled']),
            'pay_methods' => $this->decodeJson($row['pay_methods_json'], ['alipay', 'wxpay']),
            'submit_mode' => $row['submit_mode'],
            'api_mode' => $row['api_mode'],
            'sort_order' => intval($row['sort_order']),
            'remark' => $row['remark'],
            'created_at' => intval($row['created_at']),
        ];
    }

    private function upsertPaymentConfig(array $config) {
        $stmt = $this->pdo->prepare(
            'INSERT INTO kn_payment_configs (id, name, type, api_url, partner_id, secret_key, fee_rate, enabled, pay_methods_json, submit_mode, api_mode, sort_order, remark, created_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE name = VALUES(name), type = VALUES(type), api_url = VALUES(api_url), partner_id = VALUES(partner_id), secret_key = VALUES(secret_key), fee_rate = VALUES(fee_rate), enabled = VALUES(enabled), pay_methods_json = VALUES(pay_methods_json), submit_mode = VALUES(submit_mode), api_mode = VALUES(api_mode), sort_order = VALUES(sort_order), remark = VALUES(remark), created_at = VALUES(created_at)'
        );
        return $stmt->execute([
            $config['id'],
            $config['name'] ?? '',
            $config['type'] ?? 'yipay',
            $config['api_url'] ?? '',
            $config['partner_id'] ?? '',
            $config['key'] ?? '',
            floatval($config['fee_rate'] ?? 0),
            !empty($config['enabled']) ? 1 : 0,
            $this->encodeJson($config['pay_methods'] ?? ['alipay', 'wxpay']),
            $config['submit_mode'] ?? 'url_redirect',
            $config['api_mode'] ?? 'submit_page',
            intval($config['sort_order'] ?? 0),
            $config['remark'] ?? '',
            intval($config['created_at'] ?? time()),
        ]);
    }

    private function rowToPaymentOrder(array $row) {
        return [
            'id' => $row['id'],
            'trade_no' => $row['trade_no'],
            'user_id' => $row['user_id'],
            'payment_config_id' => $row['payment_config_id'],
            'pay_type' => $row['pay_type'],
            'amount' => floatval($row['amount']),
            'actual_amount' => floatval($row['actual_amount']),
            'fee' => floatval($row['fee']),
            'status' => $row['status'],
            'type' => $row['order_type'],
            'title' => $row['title'],
            'description' => $row['description'],
            'target_level' => $row['target_level'],
            'product_id' => $row['product_id'],
            'quantity' => intval($row['quantity']),
            'pickup_password_hash' => $row['pickup_password_hash'],
            'guest_token' => $row['guest_token'],
            'guest_order' => !empty($row['guest_order']),
            'guest_email' => $row['guest_email'] ?? '',
            'guest_query_code' => $row['guest_query_code'] ?? '',
            'buyer_name' => $row['buyer_name'],
            'related_id' => $row['related_id'],
            'delivery_status' => $row['delivery_status'],
            'delivery_error' => $row['delivery_error'],
            'refund_applied' => !empty($row['refund_applied']),
            'refunded_amount' => floatval($row['refunded_amount'] ?? 0),
            'refunded_at' => isset($row['refunded_at']) && $row['refunded_at'] !== null ? intval($row['refunded_at']) : null,
            'balance_applied' => !empty($row['balance_applied']),
            'created_at' => intval($row['created_at']),
            'paid_at' => $row['paid_at'] !== null ? intval($row['paid_at']) : null,
        ];
    }

    private function upsertPaymentOrder(array $order) {
        $stmt = $this->pdo->prepare(
            'INSERT INTO kn_payment_orders (id, trade_no, user_id, payment_config_id, pay_type, amount, actual_amount, fee, status, order_type, title, description, target_level, product_id, quantity, pickup_password_hash, guest_token, guest_order, guest_email, guest_query_code, buyer_name, related_id, delivery_status, delivery_error, refund_applied, refunded_amount, refunded_at, balance_applied, created_at, paid_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE trade_no = VALUES(trade_no), user_id = VALUES(user_id), payment_config_id = VALUES(payment_config_id), pay_type = VALUES(pay_type), amount = VALUES(amount), actual_amount = VALUES(actual_amount), fee = VALUES(fee), status = VALUES(status), order_type = VALUES(order_type), title = VALUES(title), description = VALUES(description), target_level = VALUES(target_level), product_id = VALUES(product_id), quantity = VALUES(quantity), pickup_password_hash = VALUES(pickup_password_hash), guest_token = VALUES(guest_token), guest_order = VALUES(guest_order), guest_email = VALUES(guest_email), guest_query_code = VALUES(guest_query_code), buyer_name = VALUES(buyer_name), related_id = VALUES(related_id), delivery_status = VALUES(delivery_status), delivery_error = VALUES(delivery_error), refund_applied = VALUES(refund_applied), refunded_amount = VALUES(refunded_amount), refunded_at = VALUES(refunded_at), balance_applied = VALUES(balance_applied), created_at = VALUES(created_at), paid_at = VALUES(paid_at)'
        );
        return $stmt->execute([
            $order['id'],
            $order['trade_no'] ?? '',
            $order['user_id'] ?? '',
            $order['payment_config_id'] ?? '',
            $order['pay_type'] ?? '',
            floatval($order['amount'] ?? 0),
            floatval($order['actual_amount'] ?? ($order['amount'] ?? 0)),
            floatval($order['fee'] ?? 0),
            $order['status'] ?? 'pending',
            $order['type'] ?? 'recharge',
            $order['title'] ?? '',
            $order['description'] ?? '',
            $order['target_level'] ?? '',
            $order['product_id'] ?? '',
            intval($order['quantity'] ?? 0),
            $order['pickup_password_hash'] ?? '',
            $order['guest_token'] ?? '',
            !empty($order['guest_order']) ? 1 : 0,
            $order['guest_email'] ?? '',
            $order['guest_query_code'] ?? '',
            $order['buyer_name'] ?? '',
            $order['related_id'] ?? '',
            $order['delivery_status'] ?? '',
            $order['delivery_error'] ?? '',
            !empty($order['refund_applied']) ? 1 : 0,
            floatval($order['refunded_amount'] ?? 0),
            isset($order['refunded_at']) ? intval($order['refunded_at']) : null,
            !empty($order['balance_applied']) ? 1 : 0,
            intval($order['created_at'] ?? time()),
            isset($order['paid_at']) ? intval($order['paid_at']) : null,
        ]);
    }

    private function rowToWithdrawRequest(array $row) {
        return [
            'id' => $row['id'],
            'user_id' => $row['user_id'],
            'username' => $row['username'],
            'amount' => floatval($row['amount']),
            'actual_amount' => floatval($row['actual_amount']),
            'fee' => floatval($row['fee']),
            'payment_method' => $row['payment_method'],
            'payment_account' => $row['payment_account'],
            'qrcode_url' => $row['qrcode_url'],
            'status' => $row['status'],
            'admin_note' => $row['admin_note'],
            'processed_by' => $row['processed_by'],
            'processed_at' => $row['processed_at'] !== null ? intval($row['processed_at']) : null,
            'created_at' => intval($row['created_at']),
            'deadline' => intval($row['deadline']),
        ];
    }

    private function upsertWithdrawRequest(array $request) {
        $stmt = $this->pdo->prepare(
            'INSERT INTO kn_withdraw_requests (id, user_id, username, amount, actual_amount, fee, payment_method, payment_account, qrcode_url, status, admin_note, processed_by, processed_at, created_at, deadline)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE user_id = VALUES(user_id), username = VALUES(username), amount = VALUES(amount), actual_amount = VALUES(actual_amount), fee = VALUES(fee), payment_method = VALUES(payment_method), payment_account = VALUES(payment_account), qrcode_url = VALUES(qrcode_url), status = VALUES(status), admin_note = VALUES(admin_note), processed_by = VALUES(processed_by), processed_at = VALUES(processed_at), created_at = VALUES(created_at), deadline = VALUES(deadline)'
        );
        return $stmt->execute([
            $request['id'],
            $request['user_id'] ?? '',
            $request['username'] ?? '',
            floatval($request['amount'] ?? 0),
            floatval($request['actual_amount'] ?? 0),
            floatval($request['fee'] ?? 0),
            $request['payment_method'] ?? '',
            $request['payment_account'] ?? '',
            $request['qrcode_url'] ?? '',
            $request['status'] ?? 'pending',
            $request['admin_note'] ?? '',
            $request['processed_by'] ?? null,
            isset($request['processed_at']) ? intval($request['processed_at']) : null,
            intval($request['created_at'] ?? time()),
            intval($request['deadline'] ?? 0),
        ]);
    }
}

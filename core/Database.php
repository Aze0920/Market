<?php
/**
 * MySQL 数据存储类
 *
 * 为了兼容现有 API，本类仍然暴露原来的数组式方法，但底层改为 MySQL/MariaDB。
 */
class Database {
    private static $instance = null;
    private $pdo;
    private $data = [];
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
        'system_config'
    ];

    private function __construct() {
        require_once dirname(__DIR__) . '/config/install.php';
        keynest_require_installed(true);

        $this->connect();
        $this->ensureSchema();
        $this->loadAll();
    }

    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function connect() {
        $configFile = dirname(__DIR__) . '/config/database.php';
        $config = file_exists($configFile) ? require $configFile : [];

        $host = $config['host'] ?? '127.0.0.1';
        $port = (int)($config['port'] ?? 3306);
        $dbname = $config['database'] ?? 'keynest';
        $username = $config['username'] ?? 'root';
        $password = $config['password'] ?? '';
        $charset = $config['charset'] ?? 'utf8mb4';

        $dsn = "mysql:host={$host};port={$port};dbname={$dbname};charset={$charset}";
        try {
            $this->pdo = new PDO($dsn, $username, $password, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ]);
        } catch (PDOException $e) {
            http_response_code(500);
            $message = '数据库连接失败，请检查 config/database.php 配置和 MySQL 数据库是否已创建。错误：' . $e->getMessage();
            $isApiRequest = strpos($_SERVER['REQUEST_URI'] ?? '', '/api/') !== false;
            if ($isApiRequest) {
                header('Content-Type: application/json; charset=utf-8');
                echo json_encode([
                    'success' => false,
                    'message' => $message
                ], JSON_UNESCAPED_UNICODE);
                exit;
            }
            exit(htmlspecialchars($message, ENT_QUOTES, 'UTF-8'));
        }
    }

    private function ensureSchema() {
        $sql = "CREATE TABLE IF NOT EXISTS `kn_records` (
            `table_name` varchar(64) NOT NULL,
            `record_id` varchar(80) NOT NULL,
            `username` varchar(80) DEFAULT NULL,
            `data` longtext NOT NULL,
            `created_at` int unsigned NOT NULL DEFAULT 0,
            `updated_at` int unsigned NOT NULL DEFAULT 0,
            PRIMARY KEY (`table_name`, `record_id`),
            KEY `idx_table_username` (`table_name`, `username`),
            KEY `idx_table_created` (`table_name`, `created_at`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
        $this->pdo->exec($sql);

        $membershipSql = "CREATE TABLE IF NOT EXISTS `kn_membership_levels` (
            `name` varchar(50) NOT NULL,
            `description` varchar(255) NOT NULL DEFAULT '',
            `max_accounts_per_product` int unsigned NOT NULL DEFAULT 0,
            `max_products` int unsigned NOT NULL DEFAULT 0,
            `priority` int NOT NULL DEFAULT 0,
            `fee_rate` decimal(10,4) NOT NULL DEFAULT 0.0000,
            `cost` decimal(10,2) NOT NULL DEFAULT 0.00,
            `publish_fee_per_account` decimal(10,2) NOT NULL DEFAULT 0.00,
            `enabled` tinyint(1) NOT NULL DEFAULT 1,
            `can_upgrade` tinyint(1) NOT NULL DEFAULT 1,
            `icon` varchar(50) NOT NULL DEFAULT 'bi-gem',
            `gradient` varchar(255) NOT NULL DEFAULT '',
            `custom_label_enabled` tinyint(1) NOT NULL DEFAULT 0,
            `created_at` int unsigned NOT NULL DEFAULT 0,
            `updated_at` int unsigned NOT NULL DEFAULT 0,
            PRIMARY KEY (`name`),
            KEY `idx_membership_priority` (`priority`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
        $this->pdo->exec($membershipSql);
        $this->ensureColumn('kn_membership_levels', 'custom_label_enabled', 'tinyint(1) NOT NULL DEFAULT 0');
    }

    private function ensureColumn($table, $column, $definition) {
        $stmt = $this->pdo->prepare('SHOW COLUMNS FROM `' . str_replace('`', '', $table) . '` LIKE ?');
        $stmt->execute([$column]);
        if (!$stmt->fetch()) {
            $this->pdo->exec('ALTER TABLE `' . str_replace('`', '', $table) . '` ADD COLUMN `' . str_replace('`', '', $column) . '` ' . $definition);
        }
    }

    private function loadAll() {
        foreach ($this->tables as $table) {
            if ($table === 'system_config') {
                $this->data[$table] = $this->loadSystemConfig();
            } else {
                $this->data[$table] = $this->loadTable($table);
            }
        }

        $this->importLegacyJsonIfEmpty();

        $this->data['membership_levels'] = $this->loadMembershipLevels();
        if (empty($this->data['membership_levels'])) {
            $this->data['membership_levels'] = $this->getDefaultMembershipLevels();
            $this->saveMembershipLevels();
        }

        if (empty($this->data['users'])) {
            $this->initDefaultData();
        }

        if (empty($this->data['system_config'])) {
            $this->data['system_config'] = $this->getDefaultSystemConfig();
            $this->saveSystemConfig();
        }
    }

    private function normalizeTableName($name) {
        return preg_replace('/[^a-z0-9_]/', '', strtolower((string)$name));
    }

    private function importLegacyJsonIfEmpty() {
        $hasDatabaseData = false;
        foreach ($this->tables as $table) {
            if ($table !== 'system_config' && !empty($this->data[$table])) {
                $hasDatabaseData = true;
                break;
            }
        }
        if ($hasDatabaseData) {
            return;
        }

        $legacyPath = dirname(__DIR__) . '/data';
        if (!is_dir($legacyPath)) {
            return;
        }

        foreach ($this->tables as $table) {
            $file = $legacyPath . '/' . $table . '.json';
            if (!is_file($file)) {
                continue;
            }
            $content = file_get_contents($file);
            $legacyData = json_decode($content, true);
            if (json_last_error() !== JSON_ERROR_NONE || !is_array($legacyData)) {
                continue;
            }

            if ($table === 'system_config') {
                $this->data['system_config'] = array_merge($this->getDefaultSystemConfig(), $legacyData);
                $this->saveSystemConfig();
                continue;
            }

            foreach ($legacyData as $record) {
                if (is_array($record) && !empty($record['id'])) {
                    $this->data[$table][] = $record;
                    $this->saveRecord($table, $record);
                }
            }
        }
    }

    private function loadTable($name) {
        $name = $this->normalizeTableName($name);
        $stmt = $this->pdo->prepare('SELECT data FROM kn_records WHERE table_name = ? ORDER BY created_at ASC');
        $stmt->execute([$name]);

        $rows = [];
        while ($row = $stmt->fetch()) {
            $data = json_decode($row['data'], true);
            if (is_array($data)) {
                $rows[] = $data;
            }
        }
        return $rows;
    }

    private function loadSystemConfig() {
        $stmt = $this->pdo->prepare('SELECT data FROM kn_records WHERE table_name = ? AND record_id = ? LIMIT 1');
        $stmt->execute(['system_config', 'default']);
        $row = $stmt->fetch();
        if (!$row) {
            return [];
        }
        $config = json_decode($row['data'], true);
        return is_array($config) ? $config : [];
    }

    private function normalizeMembershipInteger($value, $default = 1, $min = 0, $max = 999999) {
        if ($value === null || $value === '') {
            return $default;
        }
        if (!is_numeric($value)) {
            return $default;
        }
        return max($min, min($max, intval($value)));
    }

    private function normalizeMembershipLevel($level) {
        $name = trim((string)($level['name'] ?? ''));
        if ($name === '') {
            return null;
        }
        return [
            'name' => substr($name, 0, 50),
            'description' => substr(trim((string)($level['description'] ?? '')), 0, 255),
            'max_accounts_per_product' => $this->normalizeMembershipInteger($level['max_accounts_per_product'] ?? null, 1),
            'max_products' => $this->normalizeMembershipInteger($level['max_products'] ?? null, 1),
            'priority' => $this->normalizeMembershipInteger($level['priority'] ?? null, 0),
            'fee_rate' => max(0, min(1, floatval($level['fee_rate'] ?? 0))),
            'cost' => max(0, floatval($level['cost'] ?? 0)),
            'publish_fee_per_account' => max(0, floatval($level['publish_fee_per_account'] ?? 0)),
            'enabled' => !empty($level['enabled']),
            'can_upgrade' => array_key_exists('can_upgrade', $level) ? !empty($level['can_upgrade']) : true,
            'icon' => preg_replace('/[^a-zA-Z0-9\- ]/', '', (string)($level['icon'] ?? 'bi-gem')) ?: 'bi-gem',
            'gradient' => substr(trim((string)($level['gradient'] ?? '')), 0, 255) ?: 'linear-gradient(135deg, #6366f1 0%, #8b5cf6 100%)',
            'custom_label_enabled' => !empty($level['custom_label_enabled']),
        ];
    }

    private function loadMembershipLevels() {
        $stmt = $this->pdo->query('SELECT * FROM kn_membership_levels ORDER BY priority ASC, name ASC');
        $levels = [];
        while ($row = $stmt->fetch()) {
            $level = $this->normalizeMembershipLevel($row);
            if ($level) {
                $levels[$level['name']] = $level;
            }
        }
        return $levels;
    }

    private function saveMembershipLevel($level) {
        $level = $this->normalizeMembershipLevel($level);
        if (!$level) {
            return false;
        }
        $now = time();
        $stmt = $this->pdo->prepare(
            'INSERT INTO kn_membership_levels (name, description, max_accounts_per_product, max_products, priority, fee_rate, cost, publish_fee_per_account, enabled, can_upgrade, icon, gradient, custom_label_enabled, created_at, updated_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE description = VALUES(description), max_accounts_per_product = VALUES(max_accounts_per_product), max_products = VALUES(max_products), priority = VALUES(priority), fee_rate = VALUES(fee_rate), cost = VALUES(cost), publish_fee_per_account = VALUES(publish_fee_per_account), enabled = VALUES(enabled), can_upgrade = VALUES(can_upgrade), icon = VALUES(icon), gradient = VALUES(gradient), custom_label_enabled = VALUES(custom_label_enabled), updated_at = VALUES(updated_at)'
        );
        return $stmt->execute([
            $level['name'],
            $level['description'],
            $level['max_accounts_per_product'],
            $level['max_products'],
            $level['priority'],
            $level['fee_rate'],
            $level['cost'],
            $level['publish_fee_per_account'],
            $level['enabled'] ? 1 : 0,
            $level['can_upgrade'] ? 1 : 0,
            $level['icon'],
            $level['gradient'],
            !empty($level['custom_label_enabled']) ? 1 : 0,
            $now,
            $now
        ]);
    }

    private function saveMembershipLevels() {
        $this->pdo->beginTransaction();
        try {
            $this->pdo->exec('DELETE FROM kn_membership_levels');
            foreach ($this->data['membership_levels'] as $level) {
                $level = $this->normalizeMembershipLevel($level);
                if (!$level) {
                    continue;
                }
                $now = time();
                $stmt = $this->pdo->prepare(
                    'INSERT INTO kn_membership_levels (name, description, max_accounts_per_product, max_products, priority, fee_rate, cost, publish_fee_per_account, enabled, can_upgrade, icon, gradient, custom_label_enabled, created_at, updated_at)
                     VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
                );
                $stmt->execute([
                    $level['name'],
                    $level['description'],
                    $level['max_accounts_per_product'],
                    $level['max_products'],
                    $level['priority'],
                    $level['fee_rate'],
                    $level['cost'],
                    $level['publish_fee_per_account'],
                    $level['enabled'] ? 1 : 0,
                    $level['can_upgrade'] ? 1 : 0,
                    $level['icon'],
                    $level['gradient'],
                    !empty($level['custom_label_enabled']) ? 1 : 0,
                    $now,
                    $now
                ]);
            }
            $this->pdo->commit();
            return true;
        } catch (Throwable $e) {
            $this->pdo->rollBack();
            return false;
        }
    }

    private function saveRecord($table, $record) {
        $table = $this->normalizeTableName($table);
        if (!is_array($record) || empty($record['id'])) {
            return false;
        }

        $now = time();
        $username = $table === 'users' ? ($record['username'] ?? null) : null;
        $createdAt = (int)($record['created_at'] ?? $record['purchase_date'] ?? $now);
        $json = json_encode($record, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE);
        if ($json === false) {
            $json = json_encode($this->sanitizeRecordForJson($record), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE);
        }
        if ($json === false) {
            return false;
        }

        $stmt = $this->pdo->prepare(
            'INSERT INTO kn_records (table_name, record_id, username, data, created_at, updated_at)
             VALUES (?, ?, ?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE username = VALUES(username), data = VALUES(data), updated_at = VALUES(updated_at)'
        );
        return $stmt->execute([$table, $record['id'], $username, $json, $createdAt, $now]);
    }

    private function sanitizeRecordForJson($value) {
        if (is_array($value)) {
            $clean = [];
            foreach ($value as $k => $v) {
                $clean[$k] = $this->sanitizeRecordForJson($v);
            }
            return $clean;
        }
        if (is_string($value)) {
            return mb_convert_encoding($value, 'UTF-8', 'UTF-8');
        }
        return $value;
    }

    private function deleteRecord($table, $id) {
        $table = $this->normalizeTableName($table);
        $stmt = $this->pdo->prepare('DELETE FROM kn_records WHERE table_name = ? AND record_id = ?');
        return $stmt->execute([$table, $id]);
    }

    private function saveSystemConfig() {
        $now = time();
        $json = json_encode($this->data['system_config'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $stmt = $this->pdo->prepare(
            'INSERT INTO kn_records (table_name, record_id, username, data, created_at, updated_at)
             VALUES (?, ?, NULL, ?, ?, ?)
             ON DUPLICATE KEY UPDATE data = VALUES(data), updated_at = VALUES(updated_at)'
        );
        return $stmt->execute(['system_config', 'default', $json, $now, $now]);
    }

    private function getDefaultMembershipLevels() {
        return [
            'Free' => [
                'name' => 'Free',
                'max_accounts_per_product' => 5,
                'max_products' => 1,
                'priority' => 0,
                'fee_rate' => 0,
                'cost' => 0,
                'publish_fee_per_account' => 0,
                'enabled' => true,
                'can_upgrade' => false,
                'icon' => 'bi-person',
                'gradient' => 'linear-gradient(135deg, #6c757d 0%, #495057 100%)',
                'description' => '免费会员'
            ],
            'VIP' => [
                'name' => 'VIP',
                'max_accounts_per_product' => 50,
                'max_products' => 3,
                'priority' => 1,
                'fee_rate' => 0.03,
                'cost' => 0,
                'publish_fee_per_account' => 0,
                'enabled' => true,
                'can_upgrade' => true,
                'icon' => 'bi-star',
                'gradient' => 'linear-gradient(135deg, #f59e0b 0%, #d97706 100%)',
                'description' => 'VIP会员'
            ],
            'PRO' => [
                'name' => 'PRO',
                'max_accounts_per_product' => 100,
                'max_products' => 5,
                'priority' => 2,
                'fee_rate' => 0.05,
                'cost' => 0,
                'publish_fee_per_account' => 0,
                'enabled' => true,
                'can_upgrade' => true,
                'icon' => 'bi-gem',
                'gradient' => 'linear-gradient(135deg, #6366f1 0%, #4f46e5 100%)',
                'description' => 'PRO会员'
            ],
            'Infinite' => [
                'name' => 'Infinite',
                'max_accounts_per_product' => 65535,
                'max_products' => 999999,
                'priority' => 3,
                'fee_rate' => 0,
                'cost' => 10,
                'publish_fee_per_account' => 0.1,
                'enabled' => true,
                'can_upgrade' => true,
                'icon' => 'bi-infinity',
                'gradient' => 'linear-gradient(135deg, #10b981 0%, #059669 100%)',
                'description' => '无限会员'
            ]
        ];
    }

    public function getMembershipLevels() {
        return $this->data['membership_levels'];
    }

    public function updateMembershipLevels($levels) {
        if (!is_array($levels)) {
            return false;
        }
        $normalized = [];
        foreach ($levels as $level) {
            $item = $this->normalizeMembershipLevel($level);
            if ($item) {
                $normalized[$item['name']] = $item;
            }
        }
        if (empty($normalized)) {
            return false;
        }
        uasort($normalized, fn($a, $b) => ($a['priority'] <=> $b['priority']) ?: strcmp($a['name'], $b['name']));
        $this->data['membership_levels'] = $normalized;
        return $this->saveMembershipLevels();
    }

    public function upsertMembershipLevel($level) {
        $item = $this->normalizeMembershipLevel($level);
        if (!$item) {
            return false;
        }
        $this->data['membership_levels'][$item['name']] = $item;
        uasort($this->data['membership_levels'], fn($a, $b) => ($a['priority'] <=> $b['priority']) ?: strcmp($a['name'], $b['name']));
        return $this->saveMembershipLevel($item);
    }

    public function deleteMembershipLevel($name) {
        $name = trim((string)$name);
        if ($name === '' || $name === 'Free') {
            return false;
        }
        foreach ($this->data['users'] ?? [] as $user) {
            if (($user['membership_level'] ?? '') === $name) {
                return false;
            }
        }
        unset($this->data['membership_levels'][$name]);
        $stmt = $this->pdo->prepare('DELETE FROM kn_membership_levels WHERE name = ?');
        return $stmt->execute([$name]);
    }

    private function getDefaultSystemConfig() {
        return [
            'site_name' => 'KeyNest',
            'site_description' => '虚拟商品交易平台',
            'enable_recharge' => true,
            'enable_withdraw' => true,
            'withdraw_fee_rate' => 0.01,
            'min_withdraw_amount' => 10,
            'admin_wechat_qrcode' => '',
            'admin_alipay_qrcode' => '',
            'oauth_qq_enabled' => false,
            'oauth_wechat_enabled' => false,
            'oauth_caihong_enabled' => false,
            'register_email_verify_enabled' => false,
            'email_provider' => 'resend',
            'resend_api_key' => '',
            'resend_from_email' => '',
            'resend_from_name' => 'KeyNest',
            'email_code_ttl' => 10,
            'smtp_host' => '',
            'smtp_port' => 465,
            'smtp_username' => '',
            'smtp_password' => '',
            'smtp_secure' => 'ssl',
            'captcha_enabled' => false,
            'captcha_provider' => 'turnstile',
            'captcha_site_key' => '',
            'captcha_secret_key' => '',
            'captcha_extra_config' => '',
            'captcha_login_enabled' => false,
            'captcha_register_enabled' => false,
            'announcement_enabled' => false,
            'announcement_popup_enabled' => false,
            'announcement_title' => '',
            'announcement_content' => '',
            'announcement_position' => 'home',
            'announcement_items' => [],
            'user_agreement_title' => 'KeyNest 用户服务协议',
            'user_agreement_content' => "# KeyNest 用户服务协议\n\n欢迎使用 KeyNest 虚拟商品交易平台。注册或使用本平台，即表示你已阅读并同意本协议。\n\n## 1. 账号与安全\n\n- 你应使用真实、有效的信息注册账号，并妥善保管账号和密码。\n- 因账号保管不善造成的损失，由账号使用者自行承担。\n- 不得冒用他人身份、恶意注册、批量注册或从事影响平台正常运行的行为。\n\n## 2. 交易规则\n\n- 你应在交易前仔细确认商品标题、描述、库存说明、发货方式和售后规则。\n- 虚拟商品具有即时交付、易复制等特性，购买后请及时核验交付内容。\n- 如出现商品不符、无法使用等问题，应优先通过平台投诉或沟通流程处理。\n\n## 3. 禁止行为\n\n- 禁止发布或购买违法违规、侵权、诈骗、赌博、色情、木马病毒、黑灰产等内容。\n- 禁止利用平台洗钱、套现、刷单、恶意投诉、恶意退款或扰乱交易秩序。\n- 平台有权对违规账号、商品、订单采取限制、下架、冻结、封禁等措施。\n\n## 4. 风险提示\n\n- 虚拟商品交易存在账号失效、服务变更、第三方平台限制等风险。\n- 平台将尽力维护交易秩序，但不对用户之间私下约定或脱离平台的交易承担责任。\n\n## 5. 协议变更\n\n平台可根据业务和法律要求更新本协议，更新后将通过页面展示或公告方式提示。继续使用平台即视为接受更新后的协议。",
            'merchant_agreement_title' => 'KeyNest 商家守则、免责声明与商家质保',
            'merchant_agreement_content' => "# KeyNest 商家守则、免责声明与商家质保\n\n当你申请开通商家、发布商品、添加库存或作为卖家收款时，即表示你已阅读并同意本守则。\n\n## 1. 商家守则\n\n- 商家应确保商品来源合法、描述真实、库存有效、交付内容可正常使用。\n- 商品标题、图片、描述、价格、库存和售后说明不得夸大、误导或隐瞒重要信息。\n- 禁止发布违法违规、侵权盗版、诈骗钓鱼、黑灰产、恶意软件、个人隐私数据等商品。\n- 商家应及时处理订单、发货、售后和用户咨询，不得恶意拖延、诱导站外交易或逃避平台规则。\n\n## 2. 免责声明\n\n- 商家确认已充分了解虚拟商品交易风险，并自行承担因商品来源、授权、交付、售后等产生的责任。\n- 因商家商品描述不清、违规发布、无法交付、售后拒绝处理等造成的纠纷、退款、赔付或法律责任，由商家自行承担。\n- 平台可根据投诉、风控或监管要求对商品、订单、资金和商家资格采取限制、冻结、下架或关闭等必要措施。\n\n## 3. 商家质保\n\n- 商家承诺对所售商品提供明确、有效的质量保障和售后说明，并按承诺处理补发、换货、退款或技术支持。\n- 如商品存在不可用、与描述不符、重复销售、失效等问题，商家应优先保障买家权益并积极配合平台处理。\n- 商家连续出现高投诉、拒不售后或严重违规时，平台有权取消商家资格，后续重新开通需人工审核。\n\n## 4. 开通确认\n\n本人确认已阅读并同意以上商家守则、免责声明与商家质保，自愿申请开通商家功能，并承诺遵守平台全部规则。",
            'admin_badge_icon' => 'bi-shield-fill-check',
            'admin_badge_gradient' => 'linear-gradient(135deg, #ef4444 0%, #b91c1c 100%)',
            'admin_badge_text' => '管理员',
        ];
    }

    public function getSystemConfig() {
        return array_merge($this->getDefaultSystemConfig(), $this->data['system_config'] ?? []);
    }

    public function updateSystemConfig($config) {
        $this->data['system_config'] = array_merge($this->data['system_config'], $config);
        return $this->saveSystemConfig();
    }

    public function getPaymentConfigs() {
        $configs = $this->data['payment_configs'];
        usort($configs, fn($a, $b) => ($a['sort_order'] ?? 0) <=> ($b['sort_order'] ?? 0));
        return array_map(fn($config) => $this->normalizePaymentConfig($config), $configs);
    }

    private function normalizePaymentConfig($config) {
        $config['type'] = $config['type'] ?? 'yipay';
        $config['fee_rate'] = isset($config['fee_rate']) ? floatval($config['fee_rate']) : 0;
        $config['enabled'] = !empty($config['enabled']);
        $config['pay_methods'] = isset($config['pay_methods']) && is_array($config['pay_methods']) && !empty($config['pay_methods'])
            ? array_values($config['pay_methods'])
            : ['alipay', 'wxpay'];
        $config['submit_mode'] = $config['submit_mode'] ?? 'url_redirect';
        $config['sort_order'] = intval($config['sort_order'] ?? 0);
        $config['remark'] = $config['remark'] ?? '';
        return $config;
    }

    public function getPaymentConfig($id) {
        foreach ($this->data['payment_configs'] as $config) {
            if ($config['id'] === $id) {
                return $this->normalizePaymentConfig($config);
            }
        }
        return null;
    }

    public function getActivePaymentConfigs() {
        return array_values(array_filter($this->getPaymentConfigs(), fn($config) => !empty($config['enabled'])));
    }

    public function addPaymentConfig($config) {
        $config['id'] = 'payment_' . time() . '_' . bin2hex(random_bytes(6));
        $config['created_at'] = time();
        $config = $this->normalizePaymentConfig($config);
        $this->data['payment_configs'][] = $config;
        $this->saveRecord('payment_configs', $config);
        return $config;
    }

    public function updatePaymentConfig($id, $update) {
        foreach ($this->data['payment_configs'] as &$config) {
            if ($config['id'] === $id) {
                $config = array_merge($config, $update);
                $config = $this->normalizePaymentConfig($config);
                return $this->saveRecord('payment_configs', $config);
            }
        }
        return false;
    }

    public function deletePaymentConfig($id) {
        $this->data['payment_configs'] = array_values(array_filter($this->data['payment_configs'], fn($c) => $c['id'] !== $id));
        return $this->deleteRecord('payment_configs', $id);
    }

    public function getPaymentOrders($userId = null) {
        if ($userId === null) {
            return $this->data['payment_orders'];
        }
        return array_values(array_filter($this->data['payment_orders'], fn($o) => $o['user_id'] === $userId));
    }

    public function getPaymentOrder($id) {
        foreach ($this->data['payment_orders'] as $order) {
            if ($order['id'] === $id) {
                return $order;
            }
        }
        return null;
    }

    public function getPaymentOrderByTradeNo($tradeNo) {
        foreach ($this->data['payment_orders'] as $order) {
            if ($order['trade_no'] === $tradeNo) {
                return $order;
            }
        }
        return null;
    }

    public function createPaymentOrder($orderData) {
        $order = [
            'id' => 'pay_order_' . time() . '_' . bin2hex(random_bytes(6)),
            'trade_no' => $orderData['trade_no'] ?? 'KN' . date('YmdHis') . rand(1000, 9999),
            'user_id' => $orderData['user_id'],
            'payment_config_id' => $orderData['payment_config_id'] ?? '',
            'pay_type' => $orderData['pay_type'] ?? '',
            'amount' => $orderData['amount'] ?? 0,
            'actual_amount' => $orderData['actual_amount'] ?? ($orderData['amount'] ?? 0),
            'fee' => $orderData['fee'] ?? 0,
            'status' => $orderData['status'] ?? 'pending',
            'type' => $orderData['type'] ?? 'recharge',
            'title' => $orderData['title'] ?? '',
            'description' => $orderData['description'] ?? '',
            'target_level' => $orderData['target_level'] ?? '',
            'product_id' => $orderData['product_id'] ?? '',
            'quantity' => $orderData['quantity'] ?? 0,
            'pickup_password_hash' => $orderData['pickup_password_hash'] ?? '',
            'guest_token' => $orderData['guest_token'] ?? '',
            'guest_order' => $orderData['guest_order'] ?? false,
            'buyer_name' => $orderData['buyer_name'] ?? '',
            'related_id' => $orderData['related_id'] ?? '',
            'created_at' => time(),
            'paid_at' => $orderData['paid_at'] ?? null
        ];
        $this->data['payment_orders'][] = $order;
        $this->saveRecord('payment_orders', $order);
        return $order;
    }

    public function updatePaymentOrder($id, $update) {
        foreach ($this->data['payment_orders'] as &$order) {
            if ($order['id'] === $id) {
                $order = array_merge($order, $update);
                return $this->saveRecord('payment_orders', $order);
            }
        }
        return false;
    }

    public function deletePaymentOrder($id) {
        $exists = false;
        $this->data['payment_orders'] = array_values(array_filter($this->data['payment_orders'], function($order) use ($id, &$exists) {
            if (($order['id'] ?? '') === $id) {
                $exists = true;
                return false;
            }
            return true;
        }));
        return $exists ? $this->deleteRecord('payment_orders', $id) : false;
    }

    public function deletePaymentOrdersByStatus($statuses) {
        $statuses = (array)$statuses;
        $deleted = [];
        $this->data['payment_orders'] = array_values(array_filter($this->data['payment_orders'], function($order) use ($statuses, &$deleted) {
            if (in_array($order['status'] ?? 'pending', $statuses, true)) {
                $deleted[] = $order['id'];
                return false;
            }
            return true;
        }));
        foreach ($deleted as $id) {
            $this->deleteRecord('payment_orders', $id);
        }
        return count($deleted);
    }

    public function deleteAllPaymentOrders() {
        $count = count($this->data['payment_orders']);
        $this->data['payment_orders'] = [];
        $this->saveTable('payment_orders');
        return $count;
    }

    public function getWithdrawRequests($userId = null, $status = null) {
        $requests = $this->data['withdraw_requests'];
        if ($userId !== null) {
            $requests = array_filter($requests, fn($r) => $r['user_id'] === $userId);
        }
        if ($status !== null) {
            $requests = array_filter($requests, fn($r) => $r['status'] === $status);
        }
        return array_values($requests);
    }

    public function getWithdrawRequest($id) {
        foreach ($this->data['withdraw_requests'] as $request) {
            if ($request['id'] === $id) {
                return $request;
            }
        }
        return null;
    }

    public function createWithdrawRequest($data) {
        if (!isset($data['user_id'], $data['username'], $data['amount'])) {
            return null;
        }

        $amount = floatval($data['amount']);
        if ($amount <= 0 || $amount > 1000000) {
            return null;
        }

        $validMethods = ['alipay', 'wechat', 'bank'];
        $paymentMethod = htmlspecialchars(trim($data['payment_method']), ENT_QUOTES, 'UTF-8');
        if (!in_array($paymentMethod, $validMethods)) {
            return null;
        }

        $paymentAccount = trim($data['payment_account']);
        if (strlen($paymentAccount) > 100 || strlen($paymentAccount) < 1) {
            return null;
        }

        $config = $this->getSystemConfig();
        $feeRate = $config['withdraw_fee_rate'] ?? 0.01;
        $fee = $amount * $feeRate;

        $request = [
            'id' => 'wd_' . time() . '_' . bin2hex(random_bytes(6)),
            'user_id' => $data['user_id'],
            'username' => htmlspecialchars($data['username'], ENT_QUOTES, 'UTF-8'),
            'amount' => $amount,
            'actual_amount' => $amount - $fee,
            'fee' => $fee,
            'payment_method' => $paymentMethod,
            'payment_account' => htmlspecialchars($paymentAccount, ENT_QUOTES, 'UTF-8'),
            'qrcode_url' => htmlspecialchars($data['qrcode_url'] ?? '', ENT_QUOTES, 'UTF-8'),
            'status' => 'pending',
            'admin_note' => '',
            'processed_by' => null,
            'processed_at' => null,
            'created_at' => time(),
            'deadline' => time() + (7 * 24 * 60 * 60)
        ];

        $this->data['withdraw_requests'][] = $request;
        $this->saveRecord('withdraw_requests', $request);
        return $request;
    }

    public function updateWithdrawRequest($id, $update) {
        foreach ($this->data['withdraw_requests'] as &$request) {
            if ($request['id'] === $id) {
                $request = array_merge($request, $update);
                return $this->saveRecord('withdraw_requests', $request);
            }
        }
        return false;
    }

    public function deleteWithdrawRequest($id) {
        $exists = false;
        $this->data['withdraw_requests'] = array_values(array_filter($this->data['withdraw_requests'], function($request) use ($id, &$exists) {
            if (($request['id'] ?? '') === $id) {
                $exists = true;
                return false;
            }
            return true;
        }));
        return $exists ? $this->deleteRecord('withdraw_requests', $id) : false;
    }

    public function deleteWithdrawRequests($ids) {
        $count = 0;
        foreach ((array)$ids as $id) {
            if ($this->deleteWithdrawRequest($id)) {
                $count++;
            }
        }
        return $count;
    }

    public function deleteDepositRequest($id) {
        $exists = false;
        $this->data['deposit_requests'] = array_values(array_filter($this->data['deposit_requests'], function($request) use ($id, &$exists) {
            if (($request['id'] ?? '') === $id) {
                $exists = true;
                return false;
            }
            return true;
        }));
        return $exists ? $this->deleteRecord('deposit_requests', $id) : false;
    }

    public function deleteDepositRequests($ids) {
        $count = 0;
        foreach ((array)$ids as $id) {
            if ($this->deleteDepositRequest($id)) {
                $count++;
            }
        }
        return $count;
    }

    public function getPendingWithdrawRequests() {
        return $this->getWithdrawRequests(null, 'pending');
    }

    private function initDefaultData() {
        $admin = [
            'id' => 'admin_' . time(),
            'username' => 'admin',
            'password' => password_hash('admin123', PASSWORD_DEFAULT),
            'email' => 'admin@keynest.local',
            'balance' => 0,
            'role' => 'admin',
            'membership_level' => 'Infinite',
            'created_at' => time(),
            'last_login' => time()
        ];
        $this->data['users'] = [$admin];
        $this->saveRecord('users', $admin);
    }

    public function saveTable($name) {
        $name = $this->normalizeTableName($name);
        if ($name === 'system_config') {
            return $this->saveSystemConfig();
        }
        if (!isset($this->data[$name]) || !is_array($this->data[$name])) {
            return false;
        }

        $this->pdo->beginTransaction();
        try {
            $stmt = $this->pdo->prepare('DELETE FROM kn_records WHERE table_name = ?');
            $stmt->execute([$name]);
            foreach ($this->data[$name] as $record) {
                $this->saveRecord($name, $record);
            }
            $this->pdo->commit();
            return true;
        } catch (Throwable $e) {
            $this->pdo->rollBack();
            return false;
        }
    }

    public function getTable($name) {
        return $this->data[$this->normalizeTableName($name)] ?? [];
    }

    public function setTable($name, $data) {
        $name = $this->normalizeTableName($name);
        $this->data[$name] = is_array($data) ? array_values($data) : [];
        return $this->saveTable($name);
    }

    public function saveAll() {
        foreach ($this->data as $name => $table) {
            if ($name !== 'membership_levels') {
                $this->saveTable($name);
            }
        }
        if (isset($this->data['membership_levels'])) {
            $this->saveMembershipLevels();
        }
    }

    public function getUserByUsername($username) {
        if (!is_string($username) || strlen($username) > 50) {
            return null;
        }
        foreach ($this->data['users'] as $user) {
            if (isset($user['username']) && $user['username'] === $username) {
                return $user;
            }
        }
        return null;
    }

    public function getUserById($id) {
        if (!is_string($id) || strlen($id) > 80) {
            return null;
        }
        foreach ($this->data['users'] as $user) {
            if (isset($user['id']) && $user['id'] === $id) {
                return $user;
            }
        }
        return null;
    }

    public function addUser($user) {
        if (!isset($user['username'], $user['password'])) {
            return false;
        }
        if (!isset($user['id'])) {
            $user['id'] = 'id_' . time() . '_' . bin2hex(random_bytes(6));
        }
        if (!isset($user['membership_level'])) {
            $user['membership_level'] = 'Free';
        }
        if (!isset($user['created_at'])) {
            $user['created_at'] = time();
        }
        $this->data['users'][] = $user;
        return $this->saveRecord('users', $user);
    }

    public function updateUser($userId, $updates = null) {
        if ($updates === null && is_array($userId)) {
            $updates = $userId;
            $userId = $updates['id'];
        }

        $allowedFields = ['username', 'password', 'balance', 'email', 'role', 'membership_level', 'last_login', 'frozen_balance', 'qq_openid', 'qq_nickname', 'qq_bound_at', 'payment_methods', 'avatar', 'merchant_rules_accepted', 'merchant_rules_accepted_at', 'merchant_status', 'merchant_opened_once', 'merchant_approved_at', 'merchant_reapply_at', 'custom_label_text', 'custom_label_icon', 'custom_label_gradient'];
        foreach ($updates as $key => $value) {
            if (!in_array($key, $allowedFields)) {
                unset($updates[$key]);
                continue;
            }
            if ($key === 'username') {
                $updates[$key] = trim((string)$value);
                if ($updates[$key] === '' || strlen($updates[$key]) > 50) unset($updates[$key]);
            }
            if ($key === 'role') {
                $updates[$key] = $value === 'admin' ? 'admin' : 'user';
            }
            if ($key === 'balance' || $key === 'frozen_balance') {
                $updates[$key] = max(0, floatval($value));
            }
            if ($key === 'email' && $value !== '' && !filter_var($value, FILTER_VALIDATE_EMAIL)) {
                unset($updates[$key]);
            }
            if (in_array($key, ['qq_openid', 'qq_nickname'], true)) {
                $updates[$key] = trim((string)$value);
            }
            if ($key === 'qq_bound_at') {
                $updates[$key] = intval($value);
            }
            if ($key === 'payment_methods') {
                $updates[$key] = is_array($value) ? $value : [];
            }
            if ($key === 'avatar') {
                $updates[$key] = trim((string)$value);
                if ($updates[$key] !== '' && !preg_match('/^\/uploads\/avatars\/[a-zA-Z0-9_.-]+\.(png|jpe?g|gif|webp)$/i', $updates[$key])) {
                    unset($updates[$key]);
                }
            }
            if (in_array($key, ['merchant_rules_accepted', 'merchant_opened_once'], true)) {
                $updates[$key] = !empty($value);
            }
            if (in_array($key, ['merchant_rules_accepted_at', 'merchant_approved_at', 'merchant_reapply_at'], true)) {
                $updates[$key] = max(0, intval($value));
            }
            if ($key === 'merchant_status') {
                $status = trim((string)$value);
                $updates[$key] = in_array($status, ['none', 'pending', 'approved', 'rejected'], true) ? $status : 'none';
            }
            if (in_array($key, ['custom_label_text', 'custom_label_icon', 'custom_label_gradient'], true)) {
                $updates[$key] = trim((string)$value);
            }
        }

        if (empty($updates)) {
            return false;
        }

        foreach ($this->data['users'] as $existing) {
            if (isset($updates['username']) && $existing['id'] !== $userId && strcasecmp($existing['username'] ?? '', $updates['username']) === 0) {
                return false;
            }
        }

        foreach ($this->data['users'] as &$u) {
            if ($u['id'] === $userId) {
                $u = array_merge($u, $updates);
                return $this->saveRecord('users', $u);
            }
        }
        return false;
    }

    public function deleteUser($userId) {
        foreach ($this->data['users'] as $user) {
            if (($user['id'] ?? '') === $userId && (($user['username'] ?? '') === 'admin' || ($user['role'] ?? '') === 'admin')) {
                return false;
            }
        }
        $before = count($this->data['users']);
        $this->data['users'] = array_values(array_filter($this->data['users'], fn($u) => ($u['id'] ?? '') !== $userId));
        if (count($this->data['users']) === $before) {
            return false;
        }
        return $this->deleteRecord('users', $userId);
    }

    public function getCardCode($code) {
        foreach ($this->data['card_codes'] as $card) {
            if ($card['code'] === $code) {
                return $card;
            }
        }
        return null;
    }

    public function addCardCode($card) {
        if (empty($card['id'])) {
            $card['id'] = 'card_' . time() . '_' . bin2hex(random_bytes(6));
        }
        $this->data['card_codes'][] = $card;
        return $this->saveRecord('card_codes', $card);
    }

    public function useCardCode($code, $userId) {
        foreach ($this->data['card_codes'] as &$card) {
            if ($card['code'] === $code && empty($card['used'])) {
                $card['used'] = true;
                $card['used_by'] = $userId;
                $card['used_at'] = time();
                return $this->saveRecord('card_codes', $card);
            }
        }
        return false;
    }

    public function deleteCardCode($id) {
        $this->data['card_codes'] = array_values(array_filter($this->data['card_codes'], fn($c) => $c['id'] !== $id));
        return $this->deleteRecord('card_codes', $id);
    }

    public function getCardCodes($onlyUnused = false) {
        $cards = $this->data['card_codes'];
        if ($onlyUnused) {
            $cards = array_filter($cards, fn($c) => empty($c['used']));
        }
        return array_values($cards);
    }

    public function getProducts($filters = []) {
        $products = $this->data['products'];
        if (isset($filters['stock_min']) && $filters['stock_min'] > 0) {
            $products = array_filter($products, fn($p) => ($p['stock'] ?? 0) > 0);
        }
        if (!empty($filters['category']) && $filters['category'] !== 'all') {
            $products = array_filter($products, fn($p) => ($p['category'] ?? '') === $filters['category']);
        }
        if (!empty($filters['search'])) {
            $search = strtolower($filters['search']);
            $products = array_filter($products, fn($p) =>
                strpos(strtolower($p['title'] ?? ''), $search) !== false ||
                strpos(strtolower($p['category'] ?? ''), $search) !== false
            );
        }
        if (!empty($filters['seller_id'])) {
            $products = array_filter($products, fn($p) => ($p['seller_id'] ?? '') === $filters['seller_id']);
        }

        $levels = $this->data['membership_levels'];
        usort($products, function($a, $b) use ($levels) {
            $sellerA = $this->getUserById($a['seller_id'] ?? '');
            $sellerB = $this->getUserById($b['seller_id'] ?? '');
            $levelA = $sellerA ? ($levels[$sellerA['membership_level']] ?? ['priority' => 0]) : ['priority' => 0];
            $levelB = $sellerB ? ($levels[$sellerB['membership_level']] ?? ['priority' => 0]) : ['priority' => 0];
            $priorityDiff = intval($levelB['priority'] ?? 0) <=> intval($levelA['priority'] ?? 0);
            if ($priorityDiff !== 0) return $priorityDiff;
            $timeDiff = intval($b['updated_at'] ?? $b['created_at'] ?? 0) <=> intval($a['updated_at'] ?? $a['created_at'] ?? 0);
            if ($timeDiff !== 0) return $timeDiff;
            return intval($b['sales'] ?? 0) <=> intval($a['sales'] ?? 0);
        });

        return array_values($products);
    }

    public function reloadTable($name) {
        $name = $this->normalizeTableName($name);
        if (!in_array($name, $this->tables, true)) {
            return [];
        }
        $this->data[$name] = $this->loadTable($name);
        return $this->data[$name];
    }

    public function getProductById($id) {
        foreach ($this->data['products'] as $product) {
            if ($product['id'] === $id) {
                return $product;
            }
        }
        return null;
    }

    public function addProduct($product) {
        $this->data['products'][] = $product;
        return $this->saveRecord('products', $product);
    }

    public function updateProduct($product) {
        foreach ($this->data['products'] as &$p) {
            if ($p['id'] === $product['id']) {
                $p = array_merge($p, $product);
                return $this->saveRecord('products', $p);
            }
        }
        return false;
    }

    public function deleteProduct($id) {
        $this->data['products'] = array_values(array_filter($this->data['products'], fn($p) => $p['id'] !== $id));
        return $this->deleteRecord('products', $id);
    }

    public function getOrders($userId = null, $type = 'buyer') {
        $orders = $this->data['orders'];
        if ($userId) {
            $field = $type === 'buyer' ? 'buyer_id' : 'seller_id';
            $orders = array_filter($orders, fn($o) => ($o[$field] ?? '') === $userId);
        }
        return array_values($orders);
    }

    public function addOrder($order) {
        $this->data['orders'][] = $order;
        return $this->saveRecord('orders', $order);
    }

    public function updateOrder($order) {
        foreach ($this->data['orders'] as &$existing) {
            if (($existing['id'] ?? '') === ($order['id'] ?? '')) {
                $existing = array_merge($existing, $order);
                return $this->saveRecord('orders', $existing);
            }
        }
        return false;
    }

    public function getOrderById($id) {
        foreach ($this->data['orders'] as $order) {
            if ($order['id'] === $id) {
                return $order;
            }
        }
        return null;
    }

    public function getComments($productId = null) {
        $comments = $this->data['comments'];
        if ($productId) {
            $comments = array_filter($comments, fn($c) => ($c['product_id'] ?? '') === $productId);
        }
        return array_values($comments);
    }

    public function addComment($comment) {
        $this->data['comments'][] = $comment;
        return $this->saveRecord('comments', $comment);
    }

    public function hasComment($userId, $productId, $orderId) {
        foreach ($this->data['comments'] as $c) {
            if ($c['user_id'] === $userId && $c['product_id'] === $productId && $c['order_id'] === $orderId) {
                return true;
            }
        }
        return false;
    }

    public function getMessages($user1, $user2 = null) {
        $msgs = $this->data['messages'];
        if ($user2) {
            $msgs = array_filter($msgs, fn($m) =>
                ($m['from'] === $user1 && $m['to'] === $user2) ||
                ($m['from'] === $user2 && $m['to'] === $user1)
            );
        } else {
            $msgs = array_filter($msgs, fn($m) => $m['from'] === $user1 || $m['to'] === $user1);
        }
        return array_values($msgs);
    }

    public function addMessage($message) {
        $this->data['messages'][] = $message;
        return $this->saveRecord('messages', $message);
    }

    public function markMessagesRead($reader, $sender) {
        foreach ($this->data['messages'] as &$m) {
            if ($m['to'] === $reader && $m['from'] === $sender && empty($m['read'])) {
                $m['read'] = true;
                $this->saveRecord('messages', $m);
            }
        }
    }

    public function getUnreadCount($username) {
        return count(array_filter($this->data['messages'], fn($m) => $m['to'] === $username && empty($m['read'])));
    }

    public function getContacts($username) {
        $msgs = $this->getMessages($username);
        $contacts = [];
        foreach ($msgs as $m) {
            $contact = $m['from'] === $username ? $m['to'] : $m['from'];
            if (!in_array($contact, $contacts)) {
                $contacts[] = $contact;
            }
        }
        return $contacts;
    }

    public function generateCSRFToken($userId) {
        $token = bin2hex(random_bytes(32));
        $_SESSION['csrf_tokens'][$userId] = $token;
        return $token;
    }

    public function validateCSRFToken($userId, $token) {
        if (!isset($_SESSION['csrf_tokens'][$userId])) {
            return false;
        }
        if ($_SESSION['csrf_tokens'][$userId] !== $token) {
            return false;
        }
        unset($_SESSION['csrf_tokens'][$userId]);
        return true;
    }

    public function getDepositRequests($userId = null) {
        $requests = $this->data['deposit_requests'];
        if ($userId) {
            $requests = array_filter($requests, fn($r) => $r['user_id'] === $userId);
        }
        return array_values($requests);
    }

    public function addDepositRequest($request) {
        $this->data['deposit_requests'][] = $request;
        return $this->saveRecord('deposit_requests', $request);
    }

    public function updateDepositRequest($request) {
        foreach ($this->data['deposit_requests'] as &$r) {
            if ($r['id'] === $request['id']) {
                $r = array_merge($r, $request);
                return $this->saveRecord('deposit_requests', $r);
            }
        }
        return false;
    }
}

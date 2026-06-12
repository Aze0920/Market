<?php

class AdminQuery {
    private $pdo;
    private $store;

    public function __construct(PDO $pdo, RelationalStore $store) {
        $this->pdo = $pdo;
        $this->store = $store;
    }

    public static function pageParams($page, $pageSize) {
        $page = max(1, intval($page ?: 1));
        $pageSize = max(10, min(200, intval($pageSize ?: 20)));
        return [$page, $pageSize, ($page - 1) * $pageSize];
    }

    private function likeKeyword($keyword) {
        $keyword = trim((string)$keyword);
        if ($keyword === '') {
            return null;
        }
        return '%' . str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $keyword) . '%';
    }

    private function complaintWhereSql() {
        return "complaint_json IS NOT NULL AND complaint_json != '' AND complaint_json NOT IN ('[]', '{}')";
    }

    public function dashboardStats() {
        $todayStart = strtotime('today');
        $stats = [
            'user_count' => 0,
            'product_count' => 0,
            'pay_order_count' => 0,
            'open_complaints' => 0,
            'pending_requests' => 0,
            'pending_subdomains' => 0,
            'today_receipt' => 0.0,
            'today_profit' => 0.0,
        ];
        $stats['user_count'] = intval($this->pdo->query('SELECT COUNT(*) FROM kn_users')->fetchColumn());
        $stats['product_count'] = intval($this->pdo->query('SELECT COUNT(*) FROM kn_products')->fetchColumn());
        $stats['pay_order_count'] = intval($this->pdo->query('SELECT COUNT(*) FROM kn_payment_orders')->fetchColumn());
        $complaintWhere = $this->complaintWhereSql();
        $stats['open_complaints'] = intval($this->pdo->query("SELECT COUNT(*) FROM kn_orders WHERE {$complaintWhere} AND complaint_json LIKE '%\"status\":\"open\"%'")->fetchColumn());
        $stats['pending_requests'] = intval($this->pdo->query("SELECT (SELECT COUNT(*) FROM kn_deposit_requests WHERE status = 'pending') + (SELECT COUNT(*) FROM kn_withdraw_requests WHERE status = 'pending')")->fetchColumn());
        $stats['pending_subdomains'] = intval($this->pdo->query("SELECT COUNT(*) FROM kn_seller_subdomains WHERE status = 'pending'")->fetchColumn());

        $stmt = $this->pdo->prepare(
            "SELECT order_type, amount, actual_amount, fee
             FROM kn_payment_orders
             WHERE status = 'paid' AND COALESCE(paid_at, created_at) >= ?"
        );
        $stmt->execute([$todayStart]);
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $type = (string)($row['order_type'] ?? 'recharge');
            if (in_array($type, ['recharge', 'membership_upgrade', 'product_online_purchase'], true)) {
                $stats['today_receipt'] += floatval($row['actual_amount'] ?? $row['amount'] ?? 0);
            }
            if ($type === 'membership_upgrade') {
                $stats['today_profit'] += floatval($row['amount'] ?? 0);
            } elseif (in_array($type, ['product_online_purchase', 'recharge'], true)) {
                $stats['today_profit'] += floatval($row['fee'] ?? 0);
            } elseif ($type === 'publish_fee') {
                $stats['today_profit'] += abs(floatval($row['amount'] ?? 0));
            }
        }
        $stats['today_receipt'] = round($stats['today_receipt'], 2);
        $stats['today_profit'] = round($stats['today_profit'], 2);
        return $stats;
    }

    public function recentUsers($limit = 6) {
        $stmt = $this->pdo->prepare('SELECT * FROM kn_users ORDER BY created_at DESC LIMIT ?');
        $stmt->bindValue(1, max(1, intval($limit)), PDO::PARAM_INT);
        $stmt->execute();
        $rows = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $rows[] = $this->store->hydrateUserRow($row);
        }
        return $rows;
    }

    public function usersPage($page, $pageSize, $keyword = '', $merchantStatus = '') {
        [$page, $pageSize, $offset] = self::pageParams($page, $pageSize);
        $where = ['1=1'];
        $params = [];
        $like = $this->likeKeyword($keyword);
        if ($like !== null) {
            $where[] = '(username LIKE ? OR email LIKE ? OR id LIKE ?)';
            $params[] = $like;
            $params[] = $like;
            $params[] = $like;
        }
        if ($merchantStatus !== '') {
            $where[] = 'merchant_status = ?';
            $params[] = $merchantStatus;
        }
        $whereSql = implode(' AND ', $where);
        $countStmt = $this->pdo->prepare("SELECT COUNT(*) FROM kn_users WHERE {$whereSql}");
        $countStmt->execute($params);
        $total = intval($countStmt->fetchColumn());
        $stmt = $this->pdo->prepare("SELECT * FROM kn_users WHERE {$whereSql} ORDER BY created_at DESC LIMIT ? OFFSET ?");
        foreach ($params as $index => $value) {
            $stmt->bindValue($index + 1, $value);
        }
        $stmt->bindValue(count($params) + 1, $pageSize, PDO::PARAM_INT);
        $stmt->bindValue(count($params) + 2, $offset, PDO::PARAM_INT);
        $stmt->execute();
        $users = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $users[] = $this->store->hydrateUserRow($row);
        }
        return compact('users', 'total', 'page', 'pageSize');
    }

    public function productsPage($page, $pageSize, $keyword = '') {
        [$page, $pageSize, $offset] = self::pageParams($page, $pageSize);
        $where = ['1=1'];
        $params = [];
        $like = $this->likeKeyword($keyword);
        if ($like !== null) {
            $where[] = '(title LIKE ? OR seller_name LIKE ? OR id LIKE ? OR category LIKE ?)';
            $params = array_merge($params, [$like, $like, $like, $like]);
        }
        $whereSql = implode(' AND ', $where);
        $countStmt = $this->pdo->prepare("SELECT COUNT(*) FROM kn_products WHERE {$whereSql}");
        $countStmt->execute($params);
        $total = intval($countStmt->fetchColumn());
        $stmt = $this->pdo->prepare("SELECT id, seller_id, seller_name, title, category, price, stock, sales, description, image, pickup_password_enabled, created_at, updated_at FROM kn_products WHERE {$whereSql} ORDER BY created_at DESC LIMIT ? OFFSET ?");
        foreach ($params as $index => $value) {
            $stmt->bindValue($index + 1, $value);
        }
        $stmt->bindValue(count($params) + 1, $pageSize, PDO::PARAM_INT);
        $stmt->bindValue(count($params) + 2, $offset, PDO::PARAM_INT);
        $stmt->execute();
        $products = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $products[] = $this->store->hydrateProductSummaryRow($row);
        }
        return compact('products', 'total', 'page', 'pageSize');
    }

    public function paymentOrdersPage($page, $pageSize, $keyword = '') {
        [$page, $pageSize, $offset] = self::pageParams($page, $pageSize);
        $where = ['1=1'];
        $params = [];
        $like = $this->likeKeyword($keyword);
        if ($like !== null) {
            $where[] = '(trade_no LIKE ? OR id LIKE ? OR user_id LIKE ? OR guest_email LIKE ? OR title LIKE ? OR description LIKE ? OR order_type LIKE ? OR pay_type LIKE ? OR status LIKE ?)';
            $params = array_merge($params, array_fill(0, 9, $like));
        }
        $whereSql = implode(' AND ', $where);
        $countStmt = $this->pdo->prepare("SELECT COUNT(*) FROM kn_payment_orders WHERE {$whereSql}");
        $countStmt->execute($params);
        $total = intval($countStmt->fetchColumn());
        $stmt = $this->pdo->prepare("SELECT * FROM kn_payment_orders WHERE {$whereSql} ORDER BY created_at DESC LIMIT ? OFFSET ?");
        foreach ($params as $index => $value) {
            $stmt->bindValue($index + 1, $value);
        }
        $stmt->bindValue(count($params) + 1, $pageSize, PDO::PARAM_INT);
        $stmt->bindValue(count($params) + 2, $offset, PDO::PARAM_INT);
        $stmt->execute();
        $orders = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $orders[] = $this->store->hydratePaymentOrderRow($row);
        }
        return compact('orders', 'total', 'page', 'pageSize');
    }

    public function complaintOrdersPage($page, $pageSize, $keyword = '', $status = 'all') {
        [$page, $pageSize, $offset] = self::pageParams($page, $pageSize);
        $baseWhere = $this->complaintWhereSql();
        $where = [$baseWhere];
        $params = [];
        if ($status !== '' && $status !== 'all') {
            $where[] = 'complaint_json LIKE ?';
            $params[] = '%"status":"' . str_replace(['\\', '"'], ['\\\\', '\\"'], $status) . '"%';
        }
        $like = $this->likeKeyword($keyword);
        if ($like !== null) {
            $where[] = '(id LIKE ? OR payment_trade_no LIKE ? OR product_title LIKE ? OR buyer_name LIKE ? OR seller_name LIKE ? OR complaint_json LIKE ?)';
            $params = array_merge($params, array_fill(0, 6, $like));
        }
        $whereSql = implode(' AND ', $where);
        $countStmt = $this->pdo->prepare("SELECT COUNT(*) FROM kn_orders WHERE {$whereSql}");
        $countStmt->execute($params);
        $total = intval($countStmt->fetchColumn());
        $allCount = intval($this->pdo->query("SELECT COUNT(*) FROM kn_orders WHERE {$baseWhere}")->fetchColumn());
        $openCount = intval($this->pdo->query("SELECT COUNT(*) FROM kn_orders WHERE {$baseWhere} AND complaint_json LIKE '%\"status\":\"open\"%'")->fetchColumn());
        $stmt = $this->pdo->prepare(
            "SELECT o.*, bu.email AS buyer_email, su.email AS seller_email
             FROM kn_orders o
             LEFT JOIN kn_users bu ON bu.id = o.buyer_id
             LEFT JOIN kn_users su ON su.id = o.seller_id
             WHERE {$whereSql}
             ORDER BY o.purchase_date DESC
             LIMIT ? OFFSET ?"
        );
        foreach ($params as $index => $value) {
            $stmt->bindValue($index + 1, $value);
        }
        $stmt->bindValue(count($params) + 1, $pageSize, PDO::PARAM_INT);
        $stmt->bindValue(count($params) + 2, $offset, PDO::PARAM_INT);
        $stmt->execute();
        $orders = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $order = $this->store->hydrateOrderRow($row);
            if (!empty($row['buyer_email'])) {
                $order['buyer_id_email'] = $row['buyer_email'];
            }
            if (!empty($row['seller_email'])) {
                $order['seller_id_email'] = $row['seller_email'];
            }
            $orders[] = $order;
        }
        return [
            'orders' => $orders,
            'total' => $total,
            'page' => $page,
            'pageSize' => $pageSize,
            'summary' => ['all' => $allCount, 'open' => $openCount],
        ];
    }

    public function commentsPage($page, $pageSize, $keyword = '') {
        [$page, $pageSize, $offset] = self::pageParams($page, $pageSize);
        $where = ['1=1'];
        $params = [];
        $like = $this->likeKeyword($keyword);
        if ($like !== null) {
            $where[] = '(c.username LIKE ? OR c.content LIKE ? OR c.product_id LIKE ? OR c.order_id LIKE ? OR p.title LIKE ?)';
            $params = array_merge($params, array_fill(0, 5, $like));
        }
        $whereSql = implode(' AND ', $where);
        $countSql = "SELECT COUNT(*) FROM kn_comments c LEFT JOIN kn_products p ON p.id = c.product_id WHERE {$whereSql}";
        $countStmt = $this->pdo->prepare($countSql);
        $countStmt->execute($params);
        $total = intval($countStmt->fetchColumn());
        $sql = "SELECT c.*, p.title AS product_title, p.seller_id, p.seller_name, o.price AS order_price, o.quantity AS order_quantity
                FROM kn_comments c
                LEFT JOIN kn_products p ON p.id = c.product_id
                LEFT JOIN kn_orders o ON o.id = c.order_id
                WHERE {$whereSql}
                ORDER BY c.created_at DESC
                LIMIT ? OFFSET ?";
        $stmt = $this->pdo->prepare($sql);
        foreach ($params as $index => $value) {
            $stmt->bindValue($index + 1, $value);
        }
        $stmt->bindValue(count($params) + 1, $pageSize, PDO::PARAM_INT);
        $stmt->bindValue(count($params) + 2, $offset, PDO::PARAM_INT);
        $stmt->execute();
        $comments = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $comment = $this->store->hydrateCommentRow($row);
            $comment['product_title'] = $row['product_title'] ?? '';
            $comment['seller_id'] = $row['seller_id'] ?? '';
            $comment['seller_name'] = $row['seller_name'] ?? '';
            $comment['order_price'] = floatval($row['order_price'] ?? 0);
            $comment['order_quantity'] = intval($row['order_quantity'] ?? 1);
            $comments[] = $comment;
        }
        return compact('comments', 'total', 'page', 'pageSize');
    }

    public function deliveryFlagsForRelatedIds(array $relatedIds) {
        $relatedIds = array_values(array_filter(array_unique(array_map('strval', $relatedIds))));
        if (!$relatedIds) {
            return [];
        }
        $placeholders = implode(',', array_fill(0, count($relatedIds), '?'));
        $stmt = $this->pdo->prepare("SELECT id, delivery_info_json FROM kn_orders WHERE id IN ({$placeholders})");
        $stmt->execute($relatedIds);
        $map = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $deliveryInfo = json_decode((string)($row['delivery_info_json'] ?? '{}'), true);
            $items = is_array($deliveryInfo) ? ($deliveryInfo['items'] ?? []) : [];
            $map[$row['id']] = is_array($items) && count($items) > 0;
        }
        return $map;
    }

    public function expireStalePendingPaymentOrders() {
        $cutoff = time() - 600;
        $stmt = $this->pdo->prepare("UPDATE kn_payment_orders SET status = 'unpaid', paid_at = NULL, expired_at = ? WHERE status = 'pending' AND created_at > 0 AND created_at <= ?");
        $now = time();
        $stmt->execute([$now, $cutoff]);
        return $stmt->rowCount();
    }

    public function subdomainsPage($page, $pageSize, $keyword = '', $status = '') {
        [$page, $pageSize, $offset] = self::pageParams($page, $pageSize);
        $where = ['1=1'];
        $params = [];
        $like = $this->likeKeyword($keyword);
        if ($like !== null) {
            $where[] = '(s.prefix LIKE ? OR u.username LIKE ? OR u.email LIKE ? OR s.user_id LIKE ?)';
            $params[] = $like;
            $params[] = $like;
            $params[] = $like;
            $params[] = $like;
        }
        if ($status !== '') {
            $where[] = 's.status = ?';
            $params[] = $status;
        }
        $whereSql = implode(' AND ', $where);
        $fromSql = 'kn_seller_subdomains s LEFT JOIN kn_users u ON u.id = s.user_id';
        $countStmt = $this->pdo->prepare("SELECT COUNT(*) FROM {$fromSql} WHERE {$whereSql}");
        $countStmt->execute($params);
        $total = intval($countStmt->fetchColumn());
        $stmt = $this->pdo->prepare("SELECT s.*, u.username, u.email FROM {$fromSql} WHERE {$whereSql} ORDER BY s.updated_at DESC, s.created_at DESC LIMIT ? OFFSET ?");
        foreach ($params as $index => $value) {
            $stmt->bindValue($index + 1, $value);
        }
        $stmt->bindValue(count($params) + 1, $pageSize, PDO::PARAM_INT);
        $stmt->bindValue(count($params) + 2, $offset, PDO::PARAM_INT);
        $stmt->execute();
        $items = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $item = $this->store->hydrateSubdomainRow($row);
            $item['username'] = $row['username'] ?? '';
            $item['email'] = $row['email'] ?? '';
            $items[] = $item;
        }
        return compact('items', 'total', 'page', 'pageSize');
    }

    public function ensurePerformanceIndexes() {
        $indexes = [
            'kn_payment_orders' => [
                'idx_payment_orders_created' => 'created_at',
                'idx_payment_orders_paid_at' => 'paid_at',
                'idx_payment_orders_status' => 'status',
                'idx_payment_orders_related' => 'related_id',
            ],
            'kn_orders' => [
                'idx_orders_purchase_date' => 'purchase_date',
                'idx_orders_payment_trade_no' => 'payment_trade_no',
            ],
            'kn_users' => [
                'idx_users_created' => 'created_at',
                'idx_users_merchant_status' => 'merchant_status',
            ],
        ];
        foreach ($indexes as $table => $defs) {
            foreach ($defs as $name => $column) {
                $this->ensureIndex($table, $name, $column);
            }
        }
    }

    private function ensureIndex($table, $indexName, $column) {
        if (!preg_match('/^kn_[a-z0-9_]+$/', (string)$table) || !preg_match('/^idx_[a-z0-9_]+$/', (string)$indexName) || !preg_match('/^[a-z0-9_]+$/', (string)$column)) {
            return;
        }
        $stmt = $this->pdo->prepare('SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND INDEX_NAME = ?');
        $stmt->execute([$table, $indexName]);
        if (intval($stmt->fetchColumn()) > 0) {
            return;
        }
        $this->pdo->exec("ALTER TABLE `{$table}` ADD INDEX `{$indexName}` (`{$column}`)");
    }
}

<?php

require_once __DIR__ . '/SubdomainHelper.php';

class SubdomainPurchase {
    public static function apply(Database $db, array $user, $prefix, $months, $pricePaid, $source = 'balance') {
        $prefix = strtolower(trim((string)$prefix));
        $months = max(1, intval($months));
        $existing = $db->getSellerSubdomainByUserId($user['id']);
        $other = $db->getSellerSubdomainByPrefix($prefix);
        if ($other && ($other['user_id'] ?? '') !== $user['id']) {
            return ['success' => false, 'message' => '该二级域名前缀已被占用'];
        }
        if ($existing && ($existing['prefix'] ?? '') !== $prefix) {
            if (($existing['status'] ?? '') === 'pending') {
                return ['success' => false, 'message' => '您已有待审核的二级域名申请，请等待审核完成后再更换前缀'];
            }
            if (($existing['status'] ?? '') === 'approved') {
                return ['success' => false, 'message' => '已开通的二级域名不可自行更换前缀，请联系管理员处理'];
            }
        }

        $record = $existing ?: [
            'id' => '',
            'user_id' => $user['id'],
            'prefix' => $prefix,
            'expires_at' => 0,
            'approved_at' => 0,
            'created_at' => time(),
        ];
        $record['prefix'] = $prefix;
        $record['status'] = 'pending';
        $record['pending_months'] = intval($record['pending_months'] ?? 0) + $months;
        $record['last_price_paid'] = floatval($pricePaid);
        $record['disabled'] = false;
        if (!$db->saveSellerSubdomain($record)) {
            return ['success' => false, 'message' => '保存二级域名申请失败'];
        }

        $saved = $db->getSellerSubdomainByUserId($user['id']);
        $db->createPaymentOrder([
            'trade_no' => 'SUB' . date('YmdHis') . rand(1000, 9999),
            'user_id' => $user['id'],
            'payment_config_id' => $source === 'card' ? 'card' : 'balance',
            'pay_type' => $source === 'card' ? 'card_code' : 'balance',
            'amount' => floatval($pricePaid),
            'actual_amount' => floatval($pricePaid),
            'fee' => 0,
            'status' => 'paid',
            'type' => 'subdomain_purchase',
            'title' => '二级域名' . ($source === 'card' ? '卡密兑换' : '购买'),
            'description' => '二级域名 ' . $prefix . ' 购买 ' . $months . ' 个月',
            'related_id' => $saved['id'] ?? '',
            'paid_at' => time()
        ]);

        return ['success' => true, 'subdomain' => $saved];
    }
}

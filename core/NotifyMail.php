<?php

require_once __DIR__ . '/Mailer.php';

class NotifyMail {
    private static function siteName(array $config) {
        return trim((string)($config['site_name'] ?? 'KeyNest')) ?: 'KeyNest';
    }

    private static function orderTitle(array $order) {
        return trim((string)($order['product_title'] ?? '')) ?: '订单';
    }

    private static function tradeNo(array $order) {
        return trim((string)($order['payment_trade_no'] ?? $order['trade_no'] ?? $order['id'] ?? ''));
    }

    private static function moneyText($amount) {
        return '¥' . number_format(floatval($amount), 2, '.', '');
    }

    private static function withdrawMethodLabel($method) {
        $map = [
            'alipay' => '支付宝',
            'wechat' => '微信',
            'bank' => '银行卡',
        ];
        $method = strtolower(trim((string)$method));
        return $map[$method] ?? ($method !== '' ? $method : '收款方式');
    }

    private static function h($value) {
        return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
    }

    private static function renderRow($label, $value) {
        return '<div style="display:flex;justify-content:space-between;gap:16px;padding:14px 0;border-bottom:1px solid #eef2f7;font-size:14px;line-height:1.6">'
            . '<span style="color:#64748b;flex-shrink:0">' . self::h($label) . '</span>'
            . '<span style="color:#0f172a;font-weight:600;text-align:right;word-break:break-all">' . self::h($value) . '</span>'
            . '</div>';
    }

    private static function renderCard(array $config, array $options) {
        $siteName = self::siteName($config);
        $badge = self::h($options['badge'] ?? '系统通知');
        $title = self::h($options['title'] ?? '通知');
        $message = self::h($options['message'] ?? '');
        $gradient = $options['gradient'] ?? 'linear-gradient(135deg, #6366f1 0%, #8b5cf6 100%)';
        $footer = self::h($options['footer'] ?? '如非本人操作，请忽略本邮件。');
        $time = self::h($options['time'] ?? date('Y-m-d H:i:s'));

        $rowsHtml = '';
        foreach (($options['rows'] ?? []) as $row) {
            if (!is_array($row) || !isset($row['label'])) {
                continue;
            }
            $rowsHtml .= self::renderRow($row['label'], $row['value'] ?? '-');
        }

        $highlightHtml = '';
        if (!empty($options['highlight'])) {
            $highlight = $options['highlight'];
            $highlightLabel = self::h($highlight['label'] ?? '');
            $highlightValue = self::h($highlight['value'] ?? '');
            $highlightColor = self::h($highlight['color'] ?? '#16a34a');
            $highlightBg = self::h($highlight['bg'] ?? '#f0fdf4');
            $highlightBorder = self::h($highlight['border'] ?? '#86efac');
            $highlightHtml = '<div style="margin:22px 0;padding:20px;border-radius:18px;background:' . $highlightBg . ';border:1px dashed ' . $highlightBorder . ';text-align:center">'
                . '<div style="font-size:13px;color:' . $highlightColor . ';margin-bottom:8px">' . $highlightLabel . '</div>'
                . '<div style="font-size:34px;font-weight:900;color:' . $highlightColor . ';letter-spacing:1px">' . $highlightValue . '</div>'
                . '</div>';
        }

        $codeHtml = '';
        if (!empty($options['code'])) {
            $codeHtml = '<div style="margin:22px 0;padding:20px;border-radius:18px;background:#f8fafc;border:1px dashed #c7d2fe;text-align:center">'
                . '<div style="font-size:13px;color:#64748b;margin-bottom:8px">' . self::h($options['code_label'] ?? '验证码') . '</div>'
                . '<div style="font-size:34px;letter-spacing:8px;font-weight:900;color:#4f46e5">' . self::h($options['code']) . '</div>'
                . '</div>';
        }

        return '<div style="margin:0;padding:28px;background:#f3f6fb;font-family:-apple-system,BlinkMacSystemFont,Segoe UI,Arial,sans-serif;color:#1f2937">'
            . '<div style="max-width:560px;margin:0 auto;background:#ffffff;border-radius:22px;overflow:hidden;box-shadow:0 18px 45px rgba(15,23,42,.12);border:1px solid #e5e7eb">'
            . '<div style="padding:26px 30px;background:' . $gradient . ';color:#fff">'
            . '<div style="display:inline-block;padding:4px 10px;border-radius:999px;background:rgba(255,255,255,.18);font-size:12px;font-weight:700;margin-bottom:10px">' . $badge . '</div>'
            . '<div style="font-size:14px;opacity:.92">' . self::h($siteName) . '</div>'
            . '<div style="font-size:24px;font-weight:800;margin-top:6px;line-height:1.35">' . $title . '</div>'
            . '</div>'
            . '<div style="padding:30px">'
            . ($message !== '' ? '<p style="margin:0 0 14px;font-size:15px;line-height:1.8;color:#4b5563">' . $message . '</p>' : '')
            . ($rowsHtml !== '' ? '<div style="margin-top:8px">' . $rowsHtml . '</div>' : '')
            . $highlightHtml
            . $codeHtml
            . '<p style="margin:18px 0 0;font-size:13px;line-height:1.8;color:#94a3b8">' . $footer . '</p>'
            . '<p style="margin:8px 0 0;font-size:12px;line-height:1.6;color:#cbd5e1">发送时间：' . $time . '</p>'
            . '</div></div></div>';
    }

    private static function sendCard($email, $subject, array $config, array $cardOptions) {
        $email = trim((string)$email);
        if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return ['success' => false, 'message' => '收件邮箱无效'];
        }
        $html = self::renderCard($config, $cardOptions);
        return KeyNestMailer::sendAndLog($email, $subject, $html, $config);
    }

    private static function userEmail($db, $userId) {
        if ($userId === '') {
            return '';
        }
        $user = $db->getUserById($userId);
        return trim((string)($user['email'] ?? ''));
    }

    private static function usernameById($userId, $fallback = '-') {
        global $db;
        if ($userId === '') {
            return $fallback;
        }
        $user = $db->getUserById($userId);
        return trim((string)($user['username'] ?? $fallback)) ?: $fallback;
    }

    public static function withdrawApproved(array $user, array $request, array $config) {
        $email = trim((string)($user['email'] ?? ''));
        $actualAmount = self::moneyText($request['actual_amount'] ?? 0);
        return self::sendCard($email, self::siteName($config) . ' 提现成功', $config, [
            'badge' => '提现通知',
            'title' => '提现已完成',
            'gradient' => 'linear-gradient(135deg, #22c55e 0%, #16a34a 100%)',
            'message' => '管理员已确认您的提现申请并完成打款，请注意查收您的收款账户。',
            'rows' => [
                ['label' => '申请金额', 'value' => self::moneyText($request['amount'] ?? 0)],
                ['label' => '实到金额', 'value' => $actualAmount],
                ['label' => '收款方式', 'value' => self::withdrawMethodLabel($request['payment_method'] ?? '')],
                ['label' => '收款账号', 'value' => trim((string)($request['payment_account'] ?? '-'))],
            ],
            'highlight' => [
                'label' => '实到金额',
                'value' => $actualAmount,
                'color' => '#16a34a',
                'bg' => '#f0fdf4',
                'border' => '#86efac',
            ],
            'footer' => '如长时间未到账，请核对收款码信息并联系平台客服。',
        ]);
    }

    public static function withdrawRejected(array $user, array $request, array $config) {
        $email = trim((string)($user['email'] ?? ''));
        $note = trim((string)($request['admin_note'] ?? ''));
        return self::sendCard($email, self::siteName($config) . ' 提现申请未通过', $config, [
            'badge' => '提现通知',
            'title' => '提现未通过',
            'gradient' => 'linear-gradient(135deg, #f59e0b 0%, #d97706 100%)',
            'message' => '您的提现申请未通过审核，申请金额已退回账户余额。',
            'rows' => [
                ['label' => '申请金额', 'value' => self::moneyText($request['amount'] ?? 0)],
                ['label' => '收款方式', 'value' => self::withdrawMethodLabel($request['payment_method'] ?? '')],
                ['label' => '收款账号', 'value' => trim((string)($request['payment_account'] ?? '-'))],
                ['label' => '拒绝原因', 'value' => $note !== '' ? $note : '请联系平台客服了解详情'],
            ],
            'footer' => '余额已退回，可登录账户查看当前余额。',
        ]);
    }

    public static function depositApproved(array $user, array $request, array $config) {
        $email = trim((string)($user['email'] ?? ''));
        $amount = self::moneyText($request['amount'] ?? 0);
        return self::sendCard($email, self::siteName($config) . ' 充值到账通知', $config, [
            'badge' => '充值通知',
            'title' => '充值已到账',
            'gradient' => 'linear-gradient(135deg, #3b82f6 0%, #2563eb 100%)',
            'message' => '您的充值申请已通过审核，资金已成功到账。',
            'rows' => [
                ['label' => '到账金额', 'value' => $amount],
            ],
            'highlight' => [
                'label' => '到账金额',
                'value' => $amount,
                'color' => '#2563eb',
                'bg' => '#eff6ff',
                'border' => '#93c5fd',
            ],
            'footer' => '您可以在账户余额中查看最新金额。',
        ]);
    }

    public static function buyerComplaintEmail(array $order, $password, array $config) {
        $email = trim((string)($order['complaint']['email'] ?? ''));
        return self::sendCard($email, self::siteName($config) . ' 订单投诉撤诉密码', $config, [
            'badge' => '买家提醒',
            'title' => '投诉已提交',
            'gradient' => 'linear-gradient(135deg, #8b5cf6 0%, #6d5dfc 100%)',
            'message' => '您已提交订单投诉，请妥善保存撤诉密码，撤诉时需要输入该密码。',
            'rows' => [
                ['label' => '商品', 'value' => self::orderTitle($order)],
                ['label' => '订单号', 'value' => self::tradeNo($order)],
            ],
            'code' => (string)$password,
            'code_label' => '撤诉密码',
            'footer' => '撤诉密码 8 位数字，请勿泄露给他人。',
        ]);
    }

    public static function sellerComplaintReceived(array $order, array $buyer, array $config) {
        global $db;
        $email = self::userEmail($db, $order['seller_id'] ?? '');
        $complaint = is_array($order['complaint'] ?? null) ? $order['complaint'] : [];
        return self::sendCard($email, self::siteName($config) . ' 收到新的订单投诉', $config, [
            'badge' => '卖家提醒',
            'title' => '买家发起了投诉',
            'gradient' => 'linear-gradient(135deg, #ef4444 0%, #dc2626 100%)',
            'message' => '有买家对您的售出订单发起了投诉，对应收入已暂时冻结，请尽快登录处理。',
            'rows' => [
                ['label' => '商品', 'value' => self::orderTitle($order)],
                ['label' => '订单号', 'value' => self::tradeNo($order)],
                ['label' => '买家', 'value' => $buyer['username'] ?? '买家'],
                ['label' => '冻结金额', 'value' => self::moneyText($complaint['funds_amount'] ?? $order['frozen_amount'] ?? 0)],
                ['label' => '投诉原因', 'value' => $complaint['reason'] ?? '-'],
            ],
            'footer' => '您可以在「投诉管理 / 售出记录」中查看详情、回复买家或同意退款。',
        ]);
    }

    public static function sellerComplaintWithdrawn(array $order, array $config) {
        global $db;
        $email = self::userEmail($db, $order['seller_id'] ?? '');
        return self::sendCard($email, self::siteName($config) . ' 买家已撤诉', $config, [
            'badge' => '卖家提醒',
            'title' => '买家已撤诉',
            'gradient' => 'linear-gradient(135deg, #64748b 0%, #475569 100%)',
            'message' => '买家已撤诉，订单对应冻结金额已解冻。',
            'rows' => [
                ['label' => '商品', 'value' => self::orderTitle($order)],
                ['label' => '订单号', 'value' => self::tradeNo($order)],
            ],
            'footer' => '您可以在「投诉管理」中查看历史记录。',
        ]);
    }

    public static function buyerComplaintWithdrawn(array $order, array $config) {
        $email = trim((string)($order['complaint']['email'] ?? ''));
        if ($email === '') {
            global $db;
            $email = self::userEmail($db, $order['buyer_id'] ?? '');
        }
        return self::sendCard($email, self::siteName($config) . ' 撤诉成功', $config, [
            'badge' => '买家提醒',
            'title' => '撤诉成功',
            'gradient' => 'linear-gradient(135deg, #8b5cf6 0%, #6d5dfc 100%)',
            'message' => '您已成功撤诉，订单投诉已关闭。',
            'rows' => [
                ['label' => '商品', 'value' => self::orderTitle($order)],
                ['label' => '订单号', 'value' => self::tradeNo($order)],
            ],
            'footer' => '如仍有疑问，可联系平台客服。',
        ]);
    }

    public static function buyerSellerReply(array $order, $reply, array $config) {
        $email = trim((string)($order['complaint']['email'] ?? ''));
        if ($email === '') {
            global $db;
            $email = self::userEmail($db, $order['buyer_id'] ?? '');
        }
        return self::sendCard($email, self::siteName($config) . ' 卖家回复了投诉', $config, [
            'badge' => '投诉动态',
            'title' => '卖家已回复投诉',
            'gradient' => 'linear-gradient(135deg, #6366f1 0%, #3b82f6 100%)',
            'message' => '卖家对您的投诉作出了新的回复，请登录查看详情并继续沟通。',
            'rows' => [
                ['label' => '商品', 'value' => self::orderTitle($order)],
                ['label' => '订单号', 'value' => self::tradeNo($order)],
                ['label' => '卖家', 'value' => self::usernameById($order['seller_id'] ?? '', '卖家')],
                ['label' => '回复内容', 'value' => $reply],
            ],
            'footer' => '如问题仍未解决，您可以继续回复或保留投诉等待平台处理。',
        ]);
    }

    public static function sellerBuyerReply(array $order, $reply, array $config) {
        global $db;
        $email = self::userEmail($db, $order['seller_id'] ?? '');
        return self::sendCard($email, self::siteName($config) . ' 买家回复了投诉', $config, [
            'badge' => '投诉动态',
            'title' => '买家已回复投诉',
            'gradient' => 'linear-gradient(135deg, #f97316 0%, #ea580c 100%)',
            'message' => '买家对投诉作出了新的回复，请登录查看详情并及时处理。',
            'rows' => [
                ['label' => '商品', 'value' => self::orderTitle($order)],
                ['label' => '交易号', 'value' => self::tradeNo($order)],
                ['label' => '买家', 'value' => self::usernameById($order['buyer_id'] ?? '', ($order['complaint']['buyer_name'] ?? '买家'))],
                ['label' => '回复内容', 'value' => $reply],
            ],
            'footer' => '您可以在「投诉管理」中继续沟通，或在无法发货时选择同意退款。',
        ]);
    }

    public static function buyerSellerRefund(array $order, $amount, $note, array $config) {
        $email = trim((string)($order['complaint']['email'] ?? ''));
        if ($email === '') {
            global $db;
            $email = self::userEmail($db, $order['buyer_id'] ?? '');
        }
        $refundAmount = self::moneyText($amount);
        return self::sendCard($email, self::siteName($config) . ' 投诉退款已完成', $config, [
            'badge' => '买家提醒',
            'title' => '退款已完成',
            'gradient' => 'linear-gradient(135deg, #22c55e 0%, #16a34a 100%)',
            'message' => '卖家已同意退款，款项已退还到您的账户余额。',
            'rows' => [
                ['label' => '商品', 'value' => self::orderTitle($order)],
                ['label' => '订单号', 'value' => self::tradeNo($order)],
                ['label' => '退款金额', 'value' => $refundAmount],
                ['label' => '说明', 'value' => $note !== '' ? $note : '-'],
            ],
            'highlight' => [
                'label' => '退款金额',
                'value' => $refundAmount,
                'color' => '#16a34a',
                'bg' => '#f0fdf4',
                'border' => '#86efac',
            ],
            'footer' => '您可以在账户余额中查看退款结果。',
        ]);
    }

    public static function sellerComplaintRefundDone(array $order, $amount, $note, array $config) {
        global $db;
        $email = self::userEmail($db, $order['seller_id'] ?? '');
        $refundAmount = self::moneyText($amount);
        return self::sendCard($email, self::siteName($config) . ' 投诉退款已处理', $config, [
            'badge' => '卖家提醒',
            'title' => '退款已处理',
            'gradient' => 'linear-gradient(135deg, #64748b 0%, #475569 100%)',
            'message' => '您已同意退款，投诉订单退款处理完成。',
            'rows' => [
                ['label' => '商品', 'value' => self::orderTitle($order)],
                ['label' => '订单号', 'value' => self::tradeNo($order)],
                ['label' => '退款金额', 'value' => $refundAmount],
                ['label' => '说明', 'value' => $note !== '' ? $note : '-'],
            ],
            'footer' => '您可以在「投诉管理」中查看处理记录。',
        ]);
    }
}

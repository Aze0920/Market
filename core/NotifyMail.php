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

    private static function sendTo($email, $subject, $title, $message, array $config, array $extra = []) {
        $email = trim((string)$email);
        if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return ['success' => false, 'message' => '收件邮箱无效'];
        }
        $html = KeyNestMailer::renderTemplate($config, array_merge([
            'site_name' => self::siteName($config),
            'title' => $title,
            'message' => $message,
            'code' => '',
            'ttl' => '',
            'footer' => '如非本人操作，请忽略本邮件。',
            'time' => date('Y-m-d H:i:s'),
        ], $extra));
        return KeyNestMailer::sendAndLog($email, $subject, $html, $config);
    }

    private static function userEmail($db, $userId) {
        if ($userId === '') {
            return '';
        }
        $user = $db->getUserById($userId);
        return trim((string)($user['email'] ?? ''));
    }

    public static function withdrawApproved(array $user, array $request, array $config) {
        $email = trim((string)($user['email'] ?? ''));
        $message = '您的提现申请已处理完成，款项已按您提交的收款信息转出。'
            . "\n申请金额：" . self::moneyText($request['amount'] ?? 0)
            . "\n实到金额：" . self::moneyText($request['actual_amount'] ?? 0)
            . "\n手续费：" . self::moneyText($request['fee'] ?? 0)
            . "\n收款方式：" . self::withdrawMethodLabel($request['payment_method'] ?? '')
            . "\n收款账号：" . trim((string)($request['payment_account'] ?? '-'))
            . "\n处理时间：" . date('Y-m-d H:i:s', intval($request['processed_at'] ?? time()));
        if (!empty($request['admin_note'])) {
            $message .= "\n备注：" . trim((string)$request['admin_note']);
        }
        return self::sendTo(
            $email,
            self::siteName($config) . ' 提现成功通知',
            '提现成功',
            $message,
            $config,
            ['footer' => '如未收到款项，请尽快联系平台客服核对。']
        );
    }

    public static function withdrawRejected(array $user, array $request, array $config) {
        $email = trim((string)($user['email'] ?? ''));
        $message = '您的提现申请未通过审核，申请金额 ' . self::moneyText($request['amount'] ?? 0) . ' 已退回账户余额。'
            . "\n收款方式：" . self::withdrawMethodLabel($request['payment_method'] ?? '')
            . "\n收款账号：" . trim((string)($request['payment_account'] ?? '-'))
            . "\n处理时间：" . date('Y-m-d H:i:s', intval($request['processed_at'] ?? time()));
        $note = trim((string)($request['admin_note'] ?? ''));
        if ($note !== '') {
            $message .= "\n拒绝原因：" . $note;
        } else {
            $message .= "\n如有疑问，请联系平台客服。";
        }
        return self::sendTo(
            $email,
            self::siteName($config) . ' 提现申请未通过',
            '提现未通过',
            $message,
            $config,
            ['footer' => '余额已退回，可登录账户查看当前余额。']
        );
    }

    public static function depositApproved(array $user, array $request, array $config) {
        $email = trim((string)($user['email'] ?? ''));
        $message = '您的充值申请已通过审核，' . self::moneyText($request['amount'] ?? 0) . ' 已到账。'
            . "\n当前可在账户余额中查看最新金额。"
            . "\n处理时间：" . date('Y-m-d H:i:s');
        return self::sendTo(
            $email,
            self::siteName($config) . ' 充值到账通知',
            '充值成功',
            $message,
            $config
        );
    }

    public static function buyerComplaintEmail(array $order, $password, array $config) {
        $email = trim((string)($order['complaint']['email'] ?? ''));
        $title = self::orderTitle($order);
        $tradeNo = self::tradeNo($order);
        $message = '您已提交订单投诉：' . $title . '（交易号 ' . $tradeNo . '）。'
            . '请妥善保存撤诉密码，撤诉时需要输入该密码。';
        $result = self::sendTo($email, self::siteName($config) . ' 订单投诉撤诉密码', '订单投诉已提交', $message, $config, [
            'code' => (string)$password,
            'footer' => '撤诉密码 8 位数字，请勿泄露给他人。',
        ]);
        if (empty($result['success'])) {
            $fallback = KeyNestMailer::sendAndLog(
                $email,
                self::siteName($config) . ' 订单投诉撤诉密码',
                '<p>您正在投诉订单：<strong>' . htmlspecialchars($title, ENT_QUOTES, 'UTF-8') . '</strong></p>'
                . '<p>撤诉密码为：</p><h2 style="letter-spacing:4px;">' . htmlspecialchars((string)$password, ENT_QUOTES, 'UTF-8') . '</h2>'
                . '<p>请妥善保存，撤诉时需要输入该密码。</p>',
                $config
            );
            return $fallback;
        }
        return $result;
    }

    public static function sellerComplaintReceived(array $order, array $buyer, array $config) {
        global $db;
        $email = self::userEmail($db, $order['seller_id'] ?? '');
        $title = self::orderTitle($order);
        $message = '买家「' . ($buyer['username'] ?? '买家') . '」对您的订单「' . $title . '」发起了投诉，相关金额已冻结，请尽快登录处理。';
        return self::sendTo($email, self::siteName($config) . ' 收到新的订单投诉', '收到订单投诉', $message, $config);
    }

    public static function sellerComplaintWithdrawn(array $order, array $config) {
        global $db;
        $email = self::userEmail($db, $order['seller_id'] ?? '');
        $message = '买家已撤诉，订单「' . self::orderTitle($order) . '」的冻结金额已解冻。';
        return self::sendTo($email, self::siteName($config) . ' 买家已撤诉', '投诉已撤诉', $message, $config);
    }

    public static function buyerComplaintWithdrawn(array $order, array $config) {
        $email = trim((string)($order['complaint']['email'] ?? ''));
        if ($email === '') {
            global $db;
            $email = self::userEmail($db, $order['buyer_id'] ?? '');
        }
        $message = '您已成功撤诉，订单「' . self::orderTitle($order) . '」的投诉已关闭。';
        return self::sendTo($email, self::siteName($config) . ' 撤诉成功', '撤诉成功', $message, $config);
    }

    public static function buyerSellerReply(array $order, $reply, array $config) {
        $email = trim((string)($order['complaint']['email'] ?? ''));
        if ($email === '') {
            global $db;
            $email = self::userEmail($db, $order['buyer_id'] ?? '');
        }
        $message = '卖家回复了您的投诉（订单「' . self::orderTitle($order) . '」）：' . $reply;
        return self::sendTo($email, self::siteName($config) . ' 卖家回复了投诉', '卖家回复', $message, $config);
    }

    public static function sellerBuyerReply(array $order, $reply, array $config) {
        global $db;
        $email = self::userEmail($db, $order['seller_id'] ?? '');
        $message = '买家补充了投诉说明（订单「' . self::orderTitle($order) . '」）：' . $reply;
        return self::sendTo($email, self::siteName($config) . ' 买家回复了投诉', '买家回复', $message, $config);
    }

    public static function buyerSellerRefund(array $order, $amount, $note, array $config) {
        $email = trim((string)($order['complaint']['email'] ?? ''));
        if ($email === '') {
            global $db;
            $email = self::userEmail($db, $order['buyer_id'] ?? '');
        }
        $message = '卖家已同意退款，订单「' . self::orderTitle($order) . '」已退还 ¥'
            . number_format(floatval($amount), 2, '.', '') . ' 到您的余额。说明：' . $note;
        return self::sendTo($email, self::siteName($config) . ' 投诉退款已完成', '退款已完成', $message, $config);
    }

    public static function sellerComplaintRefundDone(array $order, $amount, $note, array $config) {
        global $db;
        $email = self::userEmail($db, $order['seller_id'] ?? '');
        $message = '您已同意退款，订单「' . self::orderTitle($order) . '」退款 ¥'
            . number_format(floatval($amount), 2, '.', '') . ' 已完成。说明：' . $note;
        return self::sendTo($email, self::siteName($config) . ' 投诉退款已处理', '退款已处理', $message, $config);
    }
}

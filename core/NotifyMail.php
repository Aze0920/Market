<?php

class NotifyMail {
    private static function siteName($config) {
        $name = trim((string)($config['site_name'] ?? 'KeyNest'));
        return $name !== '' ? $name : 'KeyNest';
    }

    private static function userEmail($user) {
        if (!is_array($user)) return '';
        $email = trim((string)($user['email'] ?? ''));
        return filter_var($email, FILTER_VALIDATE_EMAIL) ? $email : '';
    }

    private static function paymentMethodLabel($method) {
        $map = ['wechat' => '微信', 'alipay' => '支付宝', 'qq' => 'QQ钱包'];
        $key = strtolower(trim((string)$method));
        return $map[$key] ?? ((string)$method !== '' ? (string)$method : '-');
    }

    private static function moneyText($amount) {
        return '¥' . number_format((float)$amount, 2, '.', '');
    }
    private static function orderDetails($order, $extra = []) {
        $rows = [
            ['label' => '商品', 'value' => (string)($order['product_title'] ?? '-')],
            ['label' => '交易号', 'value' => (string)($order['payment_trade_no'] ?? $order['id'] ?? '-')],
        ];
        foreach ($extra as $row) {
            if (!is_array($row)) continue;
            $rows[] = $row;
        }
        return $rows;
    }

    public static function buyerComplaintEmail($order, $password, $config) {
        $siteName = self::siteName($config);
        return KeyNestMailer::sendNotification(
            (string)($order['complaint']['email'] ?? ''),
            $siteName . ' 投诉撤诉密码',
            $config,
            [
                'title' => '投诉已提交',
                'badge' => '投诉通知',
                'accent' => 'warning',
                'message' => '您已成功提交订单投诉，请妥善保存下面的撤诉密码。撤诉时需要输入该密码。',
                'details' => self::orderDetails($order, [
                    ['label' => '冻结金额', 'value' => self::moneyText($order['frozen_amount'] ?? 0)],
                    ['label' => '投诉原因', 'value' => (string)($order['complaint']['reason'] ?? '-')],
                ]),
                'highlight_label' => '撤诉密码',
                'highlight_value' => (string)$password,
                'footer' => '请勿将撤诉密码泄露给他人。如需继续处理，请登录账号查看投诉进度。',
            ]
        );
    }

    public static function sellerComplaintReceived($order, $buyerUser, $config) {
        global $db;
        $seller = $db->getUserById($order['seller_id'] ?? '');
        $email = self::userEmail($seller);
        if ($email === '') {
            return ['success' => false, 'message' => '卖家未绑定邮箱'];
        }
        $siteName = self::siteName($config);
        return KeyNestMailer::sendNotification(
            $email,
            $siteName . ' 您收到一条新投诉',
            $config,
            [
                'title' => '买家发起了投诉',
                'badge' => '卖家提醒',
                'accent' => 'danger',
                'message' => '有买家对您的售出订单发起了投诉，对应收入已暂时冻结，请尽快登录处理。',
                'details' => self::orderDetails($order, [
                    ['label' => '买家', 'value' => (string)($order['buyer_name'] ?? ($buyerUser['username'] ?? '买家'))],
                    ['label' => '冻结金额', 'value' => self::moneyText($order['frozen_amount'] ?? 0)],
                    ['label' => '投诉原因', 'value' => (string)($order['complaint']['reason'] ?? '-')],
                ]),
                'footer' => '您可以在「投诉管理 / 售出记录」中查看详情、回复买家或同意退款。',
            ]
        );
    }

    public static function buyerSellerReply($order, $reply, $config) {
        $email = trim((string)($order['complaint']['email'] ?? ''));
        if ($email === '') {
            global $db;
            $buyer = $db->getUserById($order['buyer_id'] ?? '');
            $email = self::userEmail($buyer);
        }
        if ($email === '') {
            return ['success' => false, 'message' => '买家邮箱无效'];
        }
        $siteName = self::siteName($config);
        return KeyNestMailer::sendNotification(
            $email,
            $siteName . ' 卖家回复了您的投诉',
            $config,
            [
                'title' => '卖家已回复投诉',
                'badge' => '投诉动态',
                'accent' => 'info',
                'message' => '卖家对您的投诉作出了新的回复，请登录查看详情并继续沟通。',
                'details' => self::orderDetails($order, [
                    ['label' => '卖家', 'value' => (string)($order['seller_name'] ?? '卖家')],
                    ['label' => '回复内容', 'value' => (string)$reply],
                ]),
                'footer' => '如问题仍未解决，您可以继续回复或保留投诉等待平台处理。',
            ]
        );
    }

    public static function sellerBuyerReply($order, $reply, $config) {
        global $db;
        $seller = $db->getUserById($order['seller_id'] ?? '');
        $email = self::userEmail($seller);
        if ($email === '') {
            return ['success' => false, 'message' => '卖家未绑定邮箱'];
        }
        $siteName = self::siteName($config);
        return KeyNestMailer::sendNotification(
            $email,
            $siteName . ' 买家回复了投诉',
            $config,
            [
                'title' => '买家已回复投诉',
                'badge' => '投诉动态',
                'accent' => 'warning',
                'message' => '买家对投诉作出了新的回复，请登录查看详情并及时处理。',
                'details' => self::orderDetails($order, [
                    ['label' => '买家', 'value' => (string)($order['buyer_name'] ?? '买家')],
                    ['label' => '回复内容', 'value' => (string)$reply],
                ]),
                'footer' => '您可以在「投诉管理」中继续沟通，或在无法发货时选择同意退款。',
            ]
        );
    }

    public static function sellerComplaintWithdrawn($order, $config) {
        global $db;
        $seller = $db->getUserById($order['seller_id'] ?? '');
        $email = self::userEmail($seller);
        if ($email === '') {
            return ['success' => false, 'message' => '卖家未绑定邮箱'];
        }
        $siteName = self::siteName($config);
        return KeyNestMailer::sendNotification(
            $email,
            $siteName . ' 买家已撤诉',
            $config,
            [
                'title' => '投诉已撤诉',
                'badge' => '投诉结束',
                'accent' => 'success',
                'message' => '买家已撤销该订单投诉，冻结金额已解冻并回到您的可用余额。',
                'details' => self::orderDetails($order, [
                    ['label' => '买家', 'value' => (string)($order['buyer_name'] ?? '买家')],
                    ['label' => '解冻金额', 'value' => self::moneyText($order['frozen_amount'] ?? 0)],
                ]),
                'footer' => '投诉记录仍会保留，您可在后台查看历史沟通内容。',
            ]
        );
    }

    public static function buyerComplaintWithdrawn($order, $config) {
        $email = trim((string)($order['complaint']['email'] ?? ''));
        if ($email === '') {
            global $db;
            $buyer = $db->getUserById($order['buyer_id'] ?? '');
            $email = self::userEmail($buyer);
        }
        if ($email === '') {
            return ['success' => false, 'message' => '买家邮箱无效'];
        }
        $siteName = self::siteName($config);
        return KeyNestMailer::sendNotification(
            $email,
            $siteName . ' 撤诉成功',
            $config,
            [
                'title' => '您已成功撤诉',
                'badge' => '投诉结束',
                'accent' => 'success',
                'message' => '该订单投诉已撤销，卖家冻结金额已解冻，本次投诉流程结束。',
                'details' => self::orderDetails($order, [
                    ['label' => '卖家', 'value' => (string)($order['seller_name'] ?? '卖家')],
                ]),
                'footer' => '如需再次投诉，请联系平台客服说明情况。',
            ]
        );
    }

    public static function buyerSellerRefund($order, $amount, $note, $config) {
        $email = trim((string)($order['complaint']['email'] ?? ''));
        if ($email === '') {
            global $db;
            $buyer = $db->getUserById($order['buyer_id'] ?? '');
            $email = self::userEmail($buyer);
        }
        if ($email === '') {
            return ['success' => false, 'message' => '买家邮箱无效'];
        }
        $siteName = self::siteName($config);
        return KeyNestMailer::sendNotification(
            $email,
            $siteName . ' 卖家已同意退款',
            $config,
            [
                'title' => '退款已到账',
                'badge' => '退款通知',
                'accent' => 'success',
                'message' => '卖家已同意退款，冻结金额已退回到您的账户余额，请登录查看。',
                'details' => self::orderDetails($order, [
                    ['label' => '卖家', 'value' => (string)($order['seller_name'] ?? '卖家')],
                    ['label' => '退款金额', 'value' => self::moneyText($amount)],
                    ['label' => '说明', 'value' => (string)$note],
                ]),
                'highlight_label' => '退款金额',
                'highlight_value' => self::moneyText($amount),
                'footer' => '退款已进入余额，可在「余额管理」中查看流水记录。',
            ]
        );
    }

    public static function sellerComplaintRefundDone($order, $amount, $note, $config) {
        global $db;
        $seller = $db->getUserById($order['seller_id'] ?? '');
        $email = self::userEmail($seller);
        if ($email === '') {
            return ['success' => false, 'message' => '卖家未绑定邮箱'];
        }
        $siteName = self::siteName($config);
        return KeyNestMailer::sendNotification(
            $email,
            $siteName . ' 退款处理完成',
            $config,
            [
                'title' => '您已完成退款',
                'badge' => '投诉结束',
                'accent' => 'info',
                'message' => '您已同意将该订单冻结金额退还给买家，本次投诉已结束。',
                'details' => self::orderDetails($order, [
                    ['label' => '买家', 'value' => (string)($order['buyer_name'] ?? '买家')],
                    ['label' => '退款金额', 'value' => self::moneyText($amount)],
                    ['label' => '说明', 'value' => (string)$note],
                ]),
                'footer' => '如需查看详情，请登录账号进入投诉管理。',
            ]
        );
    }

    public static function userWithdrawApproved($withdrawRequest, $user, $config, $adminNote = '') {
        $email = self::userEmail($user);
        if ($email === '') {
            return ['success' => false, 'message' => '用户未绑定邮箱'];
        }
        $siteName = self::siteName($config);
        $rows = [
            ['label' => '申请金额', 'value' => self::moneyText($withdrawRequest['amount'] ?? 0)],
            ['label' => '实到金额', 'value' => self::moneyText($withdrawRequest['actual_amount'] ?? $withdrawRequest['amount'] ?? 0)],
            ['label' => '收款方式', 'value' => self::paymentMethodLabel($withdrawRequest['payment_method'] ?? '')],
        ];
        if ($adminNote !== '') {
            $rows[] = ['label' => '管理员备注', 'value' => $adminNote];
        }
        return KeyNestMailer::sendNotification(
            $email,
            $siteName . ' 提现成功',
            $config,
            [
                'title' => '提现已完成',
                'badge' => '提现通知',
                'accent' => 'success',
                'message' => '管理员已确认您的提现申请并完成打款，请注意查收您的收款账户。',
                'details' => $rows,
                'highlight_label' => '实到金额',
                'highlight_value' => self::moneyText($withdrawRequest['actual_amount'] ?? $withdrawRequest['amount'] ?? 0),
                'footer' => '如长时间未到账，请核对收款码信息并联系平台客服。',
            ]
        );
    }
}

<?php

class OrderTradeNo {
    private static function typePriority($type) {
        $map = [
            'product_online_purchase' => 100,
            'product_purchase' => 90,
            'product_sale_income' => 10,
        ];
        return $map[$type] ?? 0;
    }

    public static function resolveForPurchaseOrder(array $order, array $paymentOrders) {
        $stored = trim((string)($order['payment_trade_no'] ?? ''));
        if ($stored !== '') {
            return $stored;
        }

        $orderId = (string)($order['id'] ?? '');
        $buyerId = (string)($order['buyer_id'] ?? '');
        $productId = (string)($order['product_id'] ?? '');
        $purchaseDate = intval($order['purchase_date'] ?? 0);
        $price = floatval($order['price'] ?? 0);
        $guestEmail = strtolower(trim((string)($order['guest_email'] ?? '')));

        $best = ['trade_no' => '', 'priority' => -1];
        foreach ($paymentOrders as $paymentOrder) {
            if (($paymentOrder['status'] ?? '') !== 'paid') {
                continue;
            }
            $tradeNo = trim((string)($paymentOrder['trade_no'] ?? ''));
            if ($tradeNo === '') {
                continue;
            }

            $type = (string)($paymentOrder['type'] ?? $paymentOrder['order_type'] ?? '');
            $priority = self::typePriority($type);
            $relatedId = trim((string)($paymentOrder['related_id'] ?? ''));

            if ($relatedId === $orderId) {
                if ($priority >= $best['priority']) {
                    $best = ['trade_no' => $tradeNo, 'priority' => $priority];
                }
                continue;
            }

            if (!in_array($type, ['product_online_purchase', 'product_purchase'], true)) {
                continue;
            }

            $poProductId = (string)($paymentOrder['product_id'] ?? '');
            $poUserId = (string)($paymentOrder['user_id'] ?? '');
            $poPaidAt = intval($paymentOrder['paid_at'] ?? $paymentOrder['created_at'] ?? 0);
            $poAmount = abs(floatval($paymentOrder['amount'] ?? 0));
            $timeMatch = $purchaseDate > 0 && abs($poPaidAt - $purchaseDate) <= 180;
            $productMatch = $poProductId !== '' && $poProductId === $productId;
            $buyerMatch = $buyerId !== '' && $poUserId === $buyerId;
            $guestMatch = $guestEmail !== '' && strtolower(trim((string)($paymentOrder['guest_email'] ?? ''))) === $guestEmail;
            $amountMatch = $price > 0 && abs($poAmount - $price) < 0.02;

            if ($productMatch && $timeMatch && ($buyerMatch || $guestMatch) && $amountMatch && $priority >= $best['priority']) {
                $best = ['trade_no' => $tradeNo, 'priority' => $priority];
            }
        }

        if ($best['trade_no'] !== '') {
            return $best['trade_no'];
        }

        foreach ($paymentOrders as $paymentOrder) {
            $relatedId = trim((string)($paymentOrder['related_id'] ?? ''));
            if ($relatedId !== $orderId) {
                continue;
            }
            $tradeNo = trim((string)($paymentOrder['trade_no'] ?? ''));
            if ($tradeNo !== '') {
                return $tradeNo;
            }
        }

        return '';
    }

    public static function attachToOrders(array &$orders, $db, $persist = false) {
        $paymentOrders = $db->getPaymentOrders();
        foreach ($orders as &$order) {
            $hadStored = trim((string)($order['payment_trade_no'] ?? '')) !== '';
            $resolved = self::resolveForPurchaseOrder($order, $paymentOrders);
            $order['payment_trade_no'] = $resolved;
            if ($persist && $resolved !== '' && !$hadStored) {
                $db->updateOrder(['id' => $order['id'] ?? '', 'payment_trade_no' => $resolved]);
            }
        }
        unset($order);
        return $orders;
    }

    public static function attachToOrder($order, $db, $persist = false) {
        $wrapped = [$order];
        self::attachToOrders($wrapped, $db, $persist);
        return $wrapped[0];
    }
}

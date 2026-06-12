<?php

class OrderTradeNo {
    public static function attachToOrder(array $order, $db) {
        $items = self::attachToOrders([$order], $db);
        return $items[0] ?? $order;
    }

    public static function attachToOrders(array $orders, $db) {
        if (empty($orders)) {
            return $orders;
        }
        $orderIds = [];
        foreach ($orders as $order) {
            $id = trim((string)($order['id'] ?? ''));
            if ($id !== '') {
                $orderIds[$id] = true;
            }
        }
        if (empty($orderIds)) {
            return $orders;
        }
        $tradeNoMap = [];
        foreach ($db->getPaymentOrders() as $paymentOrder) {
            $relatedId = trim((string)($paymentOrder['related_id'] ?? ''));
            if ($relatedId === '' || !isset($orderIds[$relatedId]) || !empty($tradeNoMap[$relatedId])) {
                continue;
            }
            $tradeNoMap[$relatedId] = (string)($paymentOrder['trade_no'] ?? '');
        }
        foreach ($orders as &$order) {
            $orderId = trim((string)($order['id'] ?? ''));
            $order['payment_trade_no'] = $tradeNoMap[$orderId] ?? ($order['payment_trade_no'] ?? '');
        }
        unset($order);
        return $orders;
    }
}

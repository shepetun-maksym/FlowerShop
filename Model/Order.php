<?php
// отримати усі замовлення користувача з деталями
function getUserOrders(PDO $pdo, int $userId): array {
    $sql = "SELECT o.id, o.created_at, o.status, o.phone, o.paid, o.payment_method,
                   o.delivery_type, o.delivery_address, o.store_id,
                   s.name AS store_name, s.address AS store_address,
                   oi.quantity, oi.price_at_purchase, oi.product_id,
                   p.flower_name, p.category, p.description, d.percentage AS discount_pct
            FROM orders o
            JOIN order_items oi ON oi.order_id = o.id
            JOIN products p ON oi.product_id = p.id
            LEFT JOIN discounts d ON p.discount_id = d.id
            LEFT JOIN stores s ON o.store_id = s.id
            WHERE o.user_id = :user_id
            ORDER BY o.created_at DESC, o.id DESC";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([':user_id' => $userId]);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    // групуємо по заказу
    $orders = [];
    foreach ($rows as $row) {
        $oid = $row['id'];
        if (!isset($orders[$oid])) {
            $orders[$oid] = [
                'id' => $oid,
                'created_at' => $row['created_at'],
                'status' => $row['status'],
                'paid' => $row['paid'],
                'payment_method' => $row['payment_method'],
                'phone' => $row['phone'],
                'delivery_type' => $row['delivery_type'],
                'delivery_address' => $row['delivery_address'],
                'store_id' => $row['store_id'],
                'store_name' => $row['store_name'],
                'store_address' => $row['store_address'],
                'items' => []
            ];
        }
        $orders[$oid]['items'][] = [
            'product_id' => $row['product_id'],
            'flower_name' => $row['flower_name'],
            'category' => $row['category'],
            'description' => $row['description'],
            'discount_pct' => $row['discount_pct'],
            'quantity' => $row['quantity'],
            'price_at_purchase' => $row['price_at_purchase']
        ];
    }
    // повертаємо як масив замовлень
    return array_values($orders);
}

// скасування замовлення користувачем
function cancelUserOrder(PDO $pdo, int $orderId, int $userId): bool {
    // змінюємо статус лише в тому випадку, якщо замовлення належить користувачеві та не скасовано
    $sql = "UPDATE orders SET status = 'CANCELLED' WHERE id = :id AND user_id = :user_id AND status != 'CANCELLED'";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([':id' => $orderId, ':user_id' => $userId]);
    return $stmt->rowCount() > 0;
}

// переглянути всі магазини, де можна забрати товар самостійно
function getStores(PDO $pdo): array {
    $sql = "SELECT id, name, address, phone FROM stores WHERE is_active = true ORDER BY name ASC";
    $stmt = $pdo->prepare($sql);
    $stmt->execute();
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// отримати інформацію про конкретний магазин
function getStore(PDO $pdo, int $storeId): ?array {
    $sql = "SELECT id, name, address, phone FROM stores WHERE id = :id AND is_active = true";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([':id' => $storeId]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    return $result ?: null;
}

// отримати доступні магазини для конкретного товару (де є залишок)
function getStoresWithProduct(PDO $pdo, int $productId): array {
    $sql = "SELECT DISTINCT
                s.id,
                s.name,
                s.address,
                s.phone,
                i.quantity AS stock
            FROM inventory i
            JOIN stores s ON s.id = i.location_id
            WHERE i.product_id = :product_id
              AND i.location_type = 'STORE'
              AND i.quantity > 0
              AND s.is_active = true
            ORDER BY s.name ASC";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([':product_id' => $productId]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

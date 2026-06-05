<?php

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../Model/Product.php';

// перевірка запасів і управління кошиком
// перевіряє доступну кількість товара на складі (STORE)
function checkProductStock(PDO $pdo, int $productId, int $requestedQty): array {
    $sql = "SELECT COALESCE(SUM(quantity), 0) AS total_stock 
            FROM inventory 
            WHERE product_id = :id AND location_type = 'STORE'";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([':id' => $productId]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$result) {
        return [
            'available' => 0,
            'success' => false,
            'message' => 'Товар не знайдено'
        ];
    }

    $availableStock = (int)$result['total_stock'];
    if ($availableStock <= 0) {
        return [
            'available' => 0,
            'success' => false,
            'message' => 'Товар недоступний (немає на складі)'
        ];
    }

    if ($requestedQty > $availableStock) {
        return [
            'available' => $availableStock,
            'success' => false,
            'message' => "На складі лише {$availableStock} шт. (ви хочете {$requestedQty})"
        ];
    }

    return [
        'available' => $availableStock,
        'success' => true,
        'message' => 'OK'
    ];
}

// зменшує запаси товара в inventory при оформленні замовлення
function decrementProductStock(PDO $pdo, int $productId, int $quantity): bool {
    $sql = "SELECT id, quantity 
            FROM inventory 
            WHERE product_id = :id AND location_type = 'STORE' AND quantity > 0 
            ORDER BY id LIMIT 1";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([':id' => $productId]);
    $inventoryRecord = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$inventoryRecord) {
        return false;
    }

    $updateSql = "UPDATE inventory 
                  SET quantity = quantity - :qty 
                  WHERE id = :inv_id AND quantity >= :qty";
    $updateStmt = $pdo->prepare($updateSql);
    $updateStmt->execute([
        ':qty' => $quantity,
        ':inv_id' => $inventoryRecord['id']
    ]);

    return $updateStmt->rowCount() > 0;
}

// контролер кошика
function cartController(): array {
    if (session_status() !== PHP_SESSION_ACTIVE) {
        session_start();
    }

    $result = [
        'errors' => [],
        'success' => false,
        'cart' => [],
        'user' => null,
        'order_id' => null,
        'stores' => []
    ];

    // завантажуємо інформацію про користувача, якщо він увійшов
    if (!empty($_SESSION['user_id'])) {
        $pdo = getPDO();
        require_once __DIR__ . '/../Model/User.php';
        $result['user'] = findUserById($pdo, (int)$_SESSION['user_id']);
    }

    // завантажуємо кошик з сеансу
    if (!isset($_SESSION['cart'])) {
        $_SESSION['cart'] = [];
    }
    $cart = &$_SESSION['cart'];

    // ініціалізуємо доставку в сеансу
    if (!isset($_SESSION['delivery'])) {
        $_SESSION['delivery'] = [
            'type' => 'PICKUP',
            'store_id' => null,
            'address' => ''
        ];
    }
    $delivery = &$_SESSION['delivery'];

    // обробляємо POST запити
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $action = $_POST['action'] ?? '';
        $pdo = getPDO();

        if ($action === 'add') {
            handleAddToCart($pdo, $cart, $result);
        } elseif ($action === 'update') {
            handleUpdateCart($pdo, $cart, $result);
        } elseif ($action === 'remove') {
            handleRemoveFromCart($cart, $result);
        } elseif ($action === 'clear') {
            handleClearCart($cart, $result);
        } elseif ($action === 'set_delivery') {
            handleSetDelivery($pdo, $delivery, $result);
        } elseif ($action === 'order') {
            handleCreateOrder($pdo, $result, $cart, $delivery);
        }
    }

    // завантажуємо товари з актуальною інформацією
    $pdo = getPDO();
    $products = [];
    foreach ($cart as $pid => $qty) {
        $prod = getProductById($pdo, $pid);
        if ($prod) {
            $prod['quantity'] = $qty;
            $prod['available_stock'] = (int)$prod['stock'];
            $products[] = $prod;
        }
    }

    // завантажуємо список клієнтів для менеджера
    if (!empty($result['user']) && $result['user']['role'] === 'MANAGER') {
        require_once __DIR__ . '/../Model/User.php';
        $result['clients'] = getAllClients($pdo);
    }

    // завантажуємо магазини для доставки
    require_once __DIR__ . '/../Model/Order.php';
    $result['stores'] = getStores($pdo);
    $result['delivery'] = $delivery;
    $result['cart'] = $products;

    return $result;
}

// обробка дій кошика
// додавання товара до кошика
function handleAddToCart(PDO $pdo, array &$cart, array &$result): void {
    $productId = (int)($_POST['product_id'] ?? 0);
    $quantity = max(1, (int)($_POST['quantity'] ?? 1));

    $stock = checkProductStock($pdo, $productId, $quantity);
    if (!$stock['success']) {
        $result['errors'][] = $stock['message'];
        return;
    }

    $product = getProductById($pdo, $productId);
    if (!$product) {
        $result['errors'][] = 'Товар не знайдено';
        return;
    }

    if (isset($cart[$productId])) {
        $newQty = $cart[$productId] + $quantity;
        $stockCheck = checkProductStock($pdo, $productId, $newQty);
        if (!$stockCheck['success']) {
            $result['errors'][] = $stockCheck['message'];
        } else {
            $cart[$productId] = $newQty;
            $result['success'] = "Товар оновлено ({$newQty} шт. у кошику)";
        }
    } else {
        $cart[$productId] = $quantity;
        $result['success'] = "Товар додано до кошика ({$quantity} шт.)";
    }
}

// оновлення кількості товара
function handleUpdateCart(PDO $pdo, array &$cart, array &$result): void {
    $productId = (int)($_POST['product_id'] ?? 0);
    $quantity = max(1, (int)($_POST['quantity'] ?? 1));

    if (!isset($cart[$productId])) {
        return;
    }

    $stock = checkProductStock($pdo, $productId, $quantity);
    if (!$stock['success']) {
        $result['errors'][] = $stock['message'];
    } else {
        $cart[$productId] = $quantity;
        $result['success'] = 'Кількість оновлено';
    }
}

// видалення товара з кошика
function handleRemoveFromCart(array &$cart, array &$result): void {
    $productId = (int)($_POST['product_id'] ?? 0);
    if (isset($cart[$productId])) {
        unset($cart[$productId]);
        $result['success'] = 'Товар видалено з кошика';
    }
}

// очищення кошика
function handleClearCart(array &$cart, array &$result): void {
    $cart = [];
    $result['success'] = 'Кошик очищено';
}

// встановлення типу доставки
function handleSetDelivery(PDO $pdo, array &$delivery, array &$result): void {
    $deliveryType = $_POST['delivery_type'] ?? 'PICKUP';

    if ($deliveryType === 'PICKUP') {
        $storeId = (int)($_POST['store_id'] ?? 0);
        require_once __DIR__ . '/../Model/Order.php';
        $store = getStore($pdo, $storeId);

        if (!$store) {
            $result['errors'][] = 'Оберіть коректний магазин для самовивозу';
            return;
        }

        $delivery['type'] = 'PICKUP';
        $delivery['store_id'] = $storeId;
        $delivery['address'] = '';
        $result['success'] = 'Тип доставки встановлено: самовивіз';

    } elseif ($deliveryType === 'DELIVERY') {
        $address = trim($_POST['delivery_address'] ?? '');

        if (empty($address)) {
            $result['errors'][] = 'Вкажіть адресу доставки';
            return;
        }

        $delivery['type'] = 'DELIVERY';
        $delivery['store_id'] = null;
        $delivery['address'] = $address;
        $result['success'] = 'Тип доставки встановлено: доставка за адресою';

    } else {
        $result['errors'][] = 'Невідомий тип доставки';
    }
}

// створення замовлення
function handleCreateOrder(PDO $pdo, array &$result, array &$cart, array &$delivery): void {
    // визначаємо користувача та номер телефону
    $userId = null;
    $phone = '';

    if (!empty($result['user']) && $result['user']['role'] === 'MANAGER' && !empty($_POST['client_id'])) {
        $userId = (int)$_POST['client_id'];
        require_once __DIR__ . '/../Model/User.php';
        $client = findUserById($pdo, $userId);
        if (!$client || $client['role'] !== 'CLIENT') {
            $result['errors'][] = 'Оберіть коректного клієнта';
            return;
        }
        $phone = $client['phone'] ?? '';

    } elseif (!empty($result['user']) && $result['user']['role'] === 'CLIENT') {
        $userId = $result['user']['id'];
        $phone = $result['user']['phone'] ?? '';

    } else {
        $result['errors'][] = 'Тільки авторизовані клієнти або менеджери можуть оформити замовлення';
        return;
    }

    // базові перевірки
    if (empty($cart)) {
        $result['errors'][] = 'Кошик порожній';
        return;
    }

    // перевіряємо доставку
    if ($delivery['type'] === 'PICKUP') {
        if (empty($delivery['store_id'])) {
            $result['errors'][] = 'Оберіть магазин для самовивозу';
            return;
        }
    } elseif ($delivery['type'] === 'DELIVERY') {
        if (empty($delivery['address'])) {
            $result['errors'][] = 'Вкажіть адресу доставки';
            return;
        }
    } else {
        $result['errors'][] = 'Встановіть тип доставки';
        return;
    }

    // перевіряємо залишки на складі
    foreach ($cart as $pid => $qty) {
        $stock = checkProductStock($pdo, $pid, $qty);
        if (!$stock['success']) {
            $result['errors'][] = 'Помилка перевірки запасів: ' . $stock['message'];
            return;
        }
    }

    // створюємо замовлення зі статусом NEW
    $paymentMethod = $_POST['payment_method'] ?? 'online';

    try {
        $pdo->beginTransaction();

        // вставляємо замовлення
        $sql = "INSERT INTO orders 
                (phone, user_id, delivery_type, store_id, delivery_address, status, payment_method) 
                VALUES (:phone, :user_id, :delivery_type, :store_id, :delivery_address, 'NEW', :payment_method) 
                RETURNING id";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            ':phone' => $phone,
            ':user_id' => $userId,
            ':delivery_type' => $delivery['type'],
            ':store_id' => $delivery['type'] === 'PICKUP' ? $delivery['store_id'] : null,
            ':delivery_address' => $delivery['type'] === 'DELIVERY' ? $delivery['address'] : null,
            ':payment_method' => $paymentMethod,
        ]);
        $orderId = $stmt->fetchColumn();

        // вставляємо товари замовлення
        foreach ($cart as $pid => $qty) {
            $product = getProductById($pdo, $pid);
            if (!$product) {
                continue;
            }

            $sql2 = "INSERT INTO order_items 
                     (order_id, product_id, quantity, price_at_purchase) 
                     VALUES (:order_id, :product_id, :quantity, :price)";
            $stmt2 = $pdo->prepare($sql2);
            $stmt2->execute([
                ':order_id' => $orderId,
                ':product_id' => $pid,
                ':quantity' => $qty,
                ':price' => $product['price_current'],
            ]);
        }

        $pdo->commit();

        // перенаправляємо залежно від способу оплати
        if ($paymentMethod === 'cash') {
            header('Location: payment_success.php?order_id=' . $orderId . '&payment_method=cash');
            exit;
        } else {
            header('Location: checkout.php?order_id=' . $orderId);
            exit;
        }

    } catch (Exception $e) {
        $pdo->rollBack();
        $result['errors'][] = 'Помилка при створенні замовлення: ' . $e->getMessage();
    }
}
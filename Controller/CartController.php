<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../Model/Product.php';

//Перевірити доступну кількість товара на складі (STORE) з таблиці inventory
function checkProductStock(PDO $pdo, int $productId, int $requestedQty): array {
    $stmt = $pdo->prepare("SELECT COALESCE(SUM(quantity), 0) AS total_stock FROM inventory WHERE product_id = :id AND location_type = 'STORE'");
    $stmt->execute([':id' => $productId]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$result) {
        return ['available' => 0, 'success' => false, 'message' => 'Товар не знайдено'];
    }
    
    $availableStock = (int)$result['total_stock'];
    if ($availableStock <= 0) {
        return ['available' => 0, 'success' => false, 'message' => 'Товар недоступний (немає на складі)'];
    }
    
    if ($requestedQty > $availableStock) {
        return [
            'available' => $availableStock,
            'success' => false,
            'message' => "На складі лише {$availableStock} шт. (ви хочете {$requestedQty})"
        ];
    }
    
    return ['available' => $availableStock, 'success' => true, 'message' => 'OK'];
}

//Зменшити quantity товара в таблиці inventory при оформленні замовлення (від STORE локації)
function decrementProductStock(PDO $pdo, int $productId, int $quantity): bool {
    $stmt = $pdo->prepare("SELECT id, quantity FROM inventory WHERE product_id = :id AND location_type = 'STORE' AND quantity > 0 ORDER BY id LIMIT 1");
    $stmt->execute([':id' => $productId]);
    $inventoryRecord = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$inventoryRecord) {
        return false;
    }
    
    $updateStmt = $pdo->prepare("UPDATE inventory SET quantity = quantity - :qty WHERE id = :inv_id AND quantity >= :qty");
    $updateStmt->execute([':qty' => $quantity, ':inv_id' => $inventoryRecord['id']]);
    
    return $updateStmt->rowCount() > 0;
}

function cartController(): array {
    if (session_status() !== PHP_SESSION_ACTIVE) session_start();
    $result = ['errors' => [], 'success' => false, 'cart' => [], 'user' => null, 'order_id' => null, 'stores' => []];

    // Завантажити інформацію про користувача, якщо він увійшов у систему
    if (!empty($_SESSION['user_id'])) {
        $pdo = getPDO();
        require_once __DIR__ . '/../Model/User.php';
        $result['user'] = findUserById($pdo, (int)$_SESSION['user_id']);
    }

    // Завантажити кошик у сеансі
    if (!isset($_SESSION['cart'])) $_SESSION['cart'] = [];
    $cart = &$_SESSION['cart'];

    // Ініціалізувати інформацію про доставку в сеансі
    if (!isset($_SESSION['delivery'])) {
        $_SESSION['delivery'] = [
            'type' => 'PICKUP',
            'store_id' => null,
            'address' => ''
        ];
    }
    $delivery = &$_SESSION['delivery'];

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $action = $_POST['action'] ?? '';
        $pdo = getPDO();
        
        if ($action === 'add') {
            $productId = (int)($_POST['product_id'] ?? 0);
            $quantity = max(1, (int)($_POST['quantity'] ?? 1));
            
            $stock = checkProductStock($pdo, $productId, $quantity);
            if (!$stock['success']) {
                $result['errors'][] = $stock['message'];
            } else {
                $product = getProductById($pdo, $productId);
                if ($product) {
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
                } else {
                    $result['errors'][] = 'Товар не знайдено';
                }
            }
        } elseif ($action === 'update') {
            $productId = (int)($_POST['product_id'] ?? 0);
            $quantity = max(1, (int)($_POST['quantity'] ?? 1));
            
            if (isset($cart[$productId])) {
                $stock = checkProductStock($pdo, $productId, $quantity);
                if (!$stock['success']) {
                    $result['errors'][] = $stock['message'];
                } else {
                    $cart[$productId] = $quantity;
                    $result['success'] = 'Кількість оновлено';
                }
            }
        } elseif ($action === 'remove') {
            $productId = (int)($_POST['product_id'] ?? 0);
            if (isset($cart[$productId])) {
                unset($cart[$productId]);
                $result['success'] = 'Товар видалено з кошика';
            }
        } elseif ($action === 'clear') {
            $cart = [];
            $result['success'] = 'Кошик очищено';
        } elseif ($action === 'set_delivery') {
            $deliveryType = $_POST['delivery_type'] ?? 'PICKUP';
            
            if ($deliveryType === 'PICKUP') {
                $storeId = (int)($_POST['store_id'] ?? 0);
                require_once __DIR__ . '/../Model/Order.php';
                $store = getStore($pdo, $storeId);
                
                if (!$store) {
                    $result['errors'][] = 'Оберіть коректний магазин для самовивозу.';
                } else {
                    $delivery['type'] = 'PICKUP';
                    $delivery['store_id'] = $storeId;
                    $delivery['address'] = '';
                    $result['success'] = 'Тип доставки встановлено: Самовивіз.';
                }
            } elseif ($deliveryType === 'DELIVERY') {
                $address = trim($_POST['delivery_address'] ?? '');
                
                if (empty($address)) {
                    $result['errors'][] = 'Вкажіть адресу доставки.';
                } else {
                    $delivery['type'] = 'DELIVERY';
                    $delivery['store_id'] = null;
                    $delivery['address'] = $address;
                    $result['success'] = 'Тип доставки встановлено: Доставка за адресою.';
                }
            } else {
                $result['errors'][] = 'Невідомий тип доставки.';
            }

        } elseif ($action === 'order') {
            //визначити userId та phone
            $userId = null;
            $phone  = '';

            if (!empty($result['user']) && $result['user']['role'] === 'MANAGER' && !empty($_POST['client_id'])) {
                $userId = (int)$_POST['client_id'];
                require_once __DIR__ . '/../Model/User.php';
                $client = findUserById($pdo, $userId);
                if (!$client || $client['role'] !== 'CLIENT') {
                    $result['errors'][] = 'Оберіть коректного клієнта.';
                } else {
                    $phone = $client['phone'] ?? '';
                }
            } elseif (!empty($result['user']) && $result['user']['role'] === 'CLIENT') {
                $userId = $result['user']['id'];
                $phone  = $result['user']['phone'] ?? '';
            } else {
                $result['errors'][] = 'Тільки авторизовані клієнти або менеджери можуть оформити замовлення.';
            }

            //базові перевірки
            if (!$result['errors'] && empty($cart)) {
                $result['errors'][] = 'Кошик порожній.';
            }

            if (!$result['errors']) {
                if ($delivery['type'] === 'PICKUP') {
                    if (empty($delivery['store_id'])) {
                        $result['errors'][] = 'Оберіть магазин для самовивозу.';
                    }
                } elseif ($delivery['type'] === 'DELIVERY') {
                    if (empty($delivery['address'])) {
                        $result['errors'][] = 'Вкажіть адресу доставки.';
                    }
                } else {
                    $result['errors'][] = 'Встановіть тип доставки.';
                }
            }

            // перевірка залишків на складі
            if (!$result['errors']) {
                foreach ($cart as $pid => $qty) {
                    $stock = checkProductStock($pdo, $pid, $qty);
                    if (!$stock['success']) {
                        $result['errors'][] = 'Помилка перевірки запасів: ' . $stock['message'];
                        break;
                    }
                }
            }

            //створити замовлення зі статусом NEWта перенаправити на сторінку оплати  
            if (!$result['errors']) {
                $paymentMethod = $_POST['payment_method'] ?? 'online';
                $pdo->beginTransaction();
                try {
                    //вставити замовлення, статус NEW (оплата ще не пройшла)
                    $stmt = $pdo->prepare("
                        INSERT INTO orders
                            (phone, user_id, delivery_type, store_id, delivery_address, status, payment_method)
                        VALUES
                            (:phone, :user_id, :delivery_type, :store_id, :delivery_address, 'NEW', :payment_method)
                        RETURNING id
                    ");
                    $stmt->execute([
                        ':phone'            => $phone,
                        ':user_id'          => $userId,
                        ':delivery_type'    => $delivery['type'],
                        ':store_id'         => $delivery['type'] === 'PICKUP'   ? $delivery['store_id'] : null,
                        ':delivery_address' => $delivery['type'] === 'DELIVERY' ? $delivery['address']  : null,
                        ':payment_method'   => $paymentMethod,
                    ]);
                    $orderId = $stmt->fetchColumn();

                    //вставити товари замовлення
                    foreach ($cart as $pid => $qty) {
                        $product = getProductById($pdo, $pid);
                        if ($product) {
                            $stmt2 = $pdo->prepare("
                                INSERT INTO order_items
                                    (order_id, product_id, quantity, price_at_purchase)
                                VALUES
                                    (:order_id, :product_id, :quantity, :price)
                            ");
                            $stmt2->execute([
                                ':order_id'   => $orderId,
                                ':product_id' => $pid,
                                ':quantity'   => $qty,
                                ':price'      => $product['price_current'],
                            ]);
                        }
                    }

                    $pdo->commit();

                    //перевірити способ оплати
                    $paymentMethod = $_POST['payment_method'] ?? 'online';
                    
                    if ($paymentMethod === 'cash') {
                        //оплата при отриманні то просто перенаправити на success сторінку
                        header('Location: payment_success.php?order_id=' . $orderId . '&payment_method=cash');
                        exit;
                    } else {
                        //оплата онлайн то перенаправити на сторінку оплати Stripe
                        header('Location: checkout.php?order_id=' . $orderId);
                        exit;
                    }

                } catch (Exception $e) {
                    $pdo->rollBack();
                    $result['errors'][] = 'Помилка при створенні замовлення: ' . $e->getMessage();
                }
            }
        }
    }

    //завантажити товари з кошика з актуальною інформацією про наявність на складі
    $pdo = getPDO();
    $products = [];
    foreach ($cart as $pid => $qty) {
        $prod = getProductById($pdo, $pid);
        if ($prod) {
            $prod['quantity']        = $qty;
            $prod['available_stock'] = (int)$prod['stock'];
            $products[] = $prod;
        }
    }
    //Для менеджер список клієнтів
    if (!empty($result['user']) && $result['user']['role'] === 'MANAGER') {
        require_once __DIR__ . '/../Model/User.php';
        $result['clients'] = getAllClients($pdo);
    }
    //завантажити доступні магазини
    require_once __DIR__ . '/../Model/Order.php';
    $result['stores']   = getStores($pdo);
    $result['delivery'] = $delivery;
    
    $result['cart'] = $products;
    return $result;
}
?>
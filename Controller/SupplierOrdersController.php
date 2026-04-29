<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../Model/User.php';

const GREENHOUSE_LOCATION_ID = 1;

function supplierOrdersController(): array {
    if (session_status() !== PHP_SESSION_ACTIVE) session_start();
    $result = ['errors' => [], 'success' => false, 'orders' => [], 'greenhouse' => null, 'greenhouses' => [], 'stats' => null];

    if (empty($_SESSION['user_id'])) {
        header('Location: login.php'); exit;
    }

    try {
        $pdo = getPDO();
        $userId = (int)$_SESSION['user_id'];
        $user = findUserById($pdo, $userId);
        
        if (!$user || $user['role'] !== 'SUPPLIER') {
            $result['errors'][] = 'Доступ заборонено.';
            return $result;
        }

        // отримати всі теплиці постачальника
        $ghStmt = $pdo->prepare("SELECT id, name FROM greenhouses WHERE supplier_id = :supplier_id ORDER BY name");
        $ghStmt->execute([':supplier_id' => $userId]);
        $greenhouses = $ghStmt->fetchAll(PDO::FETCH_ASSOC);
        
        $result['greenhouses'] = $greenhouses;

        // Отримати обрану теплицю або першу за замовчуванням
        $selectedGreenhouseId = null;
        if (isset($_GET['greenhouse_id'])) {
            $selectedGreenhouseId = (int)$_GET['greenhouse_id'];
            // Перевіримо що теплиця належить цьому постачальнику
            $checkStmt = $pdo->prepare("SELECT id FROM greenhouses WHERE id = :id AND supplier_id = :supplier_id");
            $checkStmt->execute([':id' => $selectedGreenhouseId, ':supplier_id' => $userId]);
            if (!$checkStmt->fetch()) {
                $selectedGreenhouseId = null;
            }
        }
        
        if (!$selectedGreenhouseId && !empty($greenhouses)) {
            $selectedGreenhouseId = (int)$greenhouses[0]['id'];
        }

        if (!$selectedGreenhouseId) {
            $result['errors'][] = 'Теплиця не знайдена для цього постачальника.';
            return $result;
        }

        // отримати обрану теплицю
        $ghStmt = $pdo->prepare("SELECT id, name FROM greenhouses WHERE id = :id AND supplier_id = :supplier_id");
        $ghStmt->execute([':id' => $selectedGreenhouseId, ':supplier_id' => $userId]);
        $greenhouse = $ghStmt->fetch(PDO::FETCH_ASSOC);

        $result['greenhouse'] = $greenhouse;

        // отримати замовлення теплиці
        $result['orders'] = getGreenhouseOrders($pdo, $selectedGreenhouseId);
        
        // отримати статистику продажів
        $result['stats'] = getSupplierSalesStats($pdo, $selectedGreenhouseId);

        // обробка post запитів
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $action = $_POST['action'] ?? '';
            
            if ($action === 'update_status') {
                $orderId = (int)($_POST['order_id'] ?? 0);
                $newStatus = $_POST['status'] ?? '';
                
                $validStatuses = ['PENDING', 'CONFIRMED', 'DELIVERED', 'CANCELLED'];
                if (!in_array($newStatus, $validStatuses)) {
                    $result['errors'][] = 'Невірний статус.';
                } else {
                    $statusResult = updateGreenhouseOrderStatus($pdo, $orderId, $newStatus, $selectedGreenhouseId);
                    if ($statusResult === true) {
                        $result['success'] = 'Статус оновлено.';
                        $result['orders'] = getGreenhouseOrders($pdo, $selectedGreenhouseId);
                    } elseif ($statusResult === 'delivered') {
                        $result['errors'][] = 'Неможливо змінити статус. Замовлення вже доставлено.';
                    } else {
                        $result['errors'][] = 'Помилка при оновленні статусу.';
                    }
                }
            } elseif ($action === 'confirm_order') {
                $orderId = (int)($_POST['order_id'] ?? 0);
                $confirmResult = confirmGreenhouseOrder($pdo, $orderId, $selectedGreenhouseId);
                if ($confirmResult === true) {
                    $result['success'] = 'Замовлення підтверджено.';
                    $result['orders'] = getGreenhouseOrders($pdo, $selectedGreenhouseId);
                } elseif ($confirmResult === 'delivered') {
                    $result['errors'][] = 'Неможливо змінити статус. Замовлення вже доставлено.';
                } else {
                    $result['errors'][] = 'Помилка при підтвердженні.';
                }
            } elseif ($action === 'deliver_order') {
                $orderId = (int)($_POST['order_id'] ?? 0);
                error_log("deliver_order called: orderId={$orderId}, greenhouse={$selectedGreenhouseId}");
                
                if (deliverGreenhouseOrder($pdo, $orderId, $selectedGreenhouseId)) {
                    $result['success'] = 'Замовлення доставлено. Товари перенесені в магазин.';
                    $result['orders'] = getGreenhouseOrders($pdo, $selectedGreenhouseId);
                    error_log("deliverGreenhouseOrder succeeded for order {$orderId}");
                } else {
                    $result['errors'][] = 'Помилка при доставці.';
                    error_log("deliverGreenhouseOrder FAILED for order {$orderId}");
                }
            } elseif ($action === 'cancel_order') {
                $orderId = (int)($_POST['order_id'] ?? 0);
                $cancelResult = cancelGreenhouseOrder($pdo, $orderId, $selectedGreenhouseId);
                if ($cancelResult === true) {
                    $result['success'] = 'Замовлення скасовано.';
                    $result['orders'] = getGreenhouseOrders($pdo, $selectedGreenhouseId);
                } elseif ($cancelResult === 'delivered') {
                    $result['errors'][] = 'Неможливо скасувати. Замовлення вже доставлено.';
                } else {
                    $result['errors'][] = 'Помилка при скасуванні.';
                }
            }
        }
    } catch (Exception $e) {
        $result['errors'][] = 'Помилка: ' . $e->getMessage();
    }

    return $result;
}

//отримати замовлення для теплиці
function getGreenhouseOrders(PDO $pdo, int $greenhouseId): array {
    $sql = "SELECT go.id, go.status, go.created_at, go.confirmed_at, go.delivered_at,
                   u.name as admin_name, u.lastname as admin_lastname, u.phone as admin_phone,
                   g.address as greenhouse_address, g.phone as greenhouse_phone,
                   s.id as store_id, s.name as store_name, s.address as store_address
            FROM greenhouse_orders go
            LEFT JOIN users u ON go.admin_id = u.id
            LEFT JOIN greenhouses g ON go.greenhouse_id = g.id
            LEFT JOIN stores s ON go.store_id = s.id
            WHERE go.greenhouse_id = :gh_id
            ORDER BY go.created_at DESC";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute([':gh_id' => $greenhouseId]);
    $orders = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Отримати items для кожного замовлення
    foreach ($orders as &$order) {
        $itemStmt = $pdo->prepare("SELECT goi.id, goi.product_id, goi.quantity, goi.quantity_delivered, goi.price_per_unit, p.flower_name
                                   FROM greenhouse_order_items goi
                                   JOIN products p ON goi.product_id = p.id
                                   WHERE goi.greenhouse_order_id = :order_id");
        $itemStmt->execute([':order_id' => $order['id']]);
        $order['items'] = $itemStmt->fetchAll(PDO::FETCH_ASSOC);
    }

    return $orders;
}

//оновити статус замовлення з автоматичним переносом товарів при delivered
//повертає true успішно, false помилка, delivered замовлення вже доставлено
function updateGreenhouseOrderStatus(PDO $pdo, int $orderId, string $newStatus, int $greenhouseId) {
    //перевірити що замовлення належить цій теплиці
    $checkStmt = $pdo->prepare("SELECT id, status FROM greenhouse_orders WHERE id = :id AND greenhouse_id = :gh_id LIMIT 1");
    $checkStmt->execute([':id' => $orderId, ':gh_id' => $greenhouseId]);
    $order = $checkStmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$order) {
        return false;
    }

    // якщо замовлення вже доставлено то не дозволяємо змінювати
    if ($order['status'] === 'DELIVERED') {
        return 'delivered';
    }

    // якщо статус DELIVERED то перемістити товари
    if ($newStatus === 'DELIVERED' && $order['status'] !== 'DELIVERED') {
        if (!transferGreenehouseOrderToStore($pdo, $orderId, $greenhouseId)) {
            return false;
        }
    }

    // оновити статус
    $updateFields = [];
    $params = [':id' => $orderId, ':status' => $newStatus];

    $updateFields[] = 'status = :status';
    
    if ($newStatus === 'CONFIRMED' && $order['status'] !== 'CONFIRMED') {
        $updateFields[] = 'confirmed_at = CURRENT_TIMESTAMP';
    }
    
    if ($newStatus === 'DELIVERED' && $order['status'] !== 'DELIVERED') {
        $updateFields[] = 'delivered_at = CURRENT_TIMESTAMP';
    }

    $sql = "UPDATE greenhouse_orders SET " . implode(', ', $updateFields) . " WHERE id = :id";
    $stmt = $pdo->prepare($sql);
    return $stmt->execute($params);
}

 //допоміжна функція для перенесення товарів з теплиці в магазин
function transferGreenehouseOrderToStore(PDO $pdo, int $orderId, int $greenhouseId): bool {
    // отримати items замовлення
    $itemsStmt = $pdo->prepare("SELECT product_id, quantity FROM greenhouse_order_items WHERE greenhouse_order_id = :order_id");
    $itemsStmt->execute([':order_id' => $orderId]);
    $items = $itemsStmt->fetchAll(PDO::FETCH_ASSOC);

    $pdo->beginTransaction();
    try {
        foreach ($items as $item) {
            $productId = (int)$item['product_id'];
            $qty = (int)$item['quantity'];

            // зменшити в GREENHOUSE
            $decreaseStmt = $pdo->prepare("UPDATE inventory SET quantity = quantity - :qty 
                                          WHERE product_id = :product_id AND location_type = 'GREENHOUSE' AND location_id = :gh_id");
            $decreaseStmt->execute([':qty' => $qty, ':product_id' => $productId, ':gh_id' => $greenhouseId]);

            // збільшити в STORE
            $storeStmt = $pdo->prepare("SELECT id FROM inventory WHERE product_id = :product_id AND location_type = 'STORE' AND location_id = 1 LIMIT 1");
            $storeStmt->execute([':product_id' => $productId]);
            $storeRecord = $storeStmt->fetch(PDO::FETCH_ASSOC);

            if ($storeRecord) {
                $updateStoreStmt = $pdo->prepare("UPDATE inventory SET quantity = quantity + :qty WHERE id = :inv_id");
                $updateStoreStmt->execute([':qty' => $qty, ':inv_id' => $storeRecord['id']]);
            } else {
                $insertStoreStmt = $pdo->prepare("INSERT INTO inventory (product_id, location_type, location_id, quantity) VALUES (:product_id, 'STORE', 1, :qty)");
                $insertStoreStmt->execute([':product_id' => $productId, ':qty' => $qty]);
            }
        }

        $pdo->commit();
        return true;
    } catch (Exception $e) {
        $pdo->rollBack();
        return false;
    }
}

function confirmGreenhouseOrder(PDO $pdo, int $orderId, int $greenhouseId) {
    // перевірити що замовлення належить цій теплиці
    $checkStmt = $pdo->prepare("SELECT id, status FROM greenhouse_orders WHERE id = :id AND greenhouse_id = :gh_id LIMIT 1");
    $checkStmt->execute([':id' => $orderId, ':gh_id' => $greenhouseId]);
    $order = $checkStmt->fetch(PDO::FETCH_ASSOC);
    if (!$order) {
        return false;
    }

    // якщо замовлення вже доставлено не дозволяємо міняти
    if ($order['status'] === 'DELIVERED') {
        return 'delivered';
    }

    $stmt = $pdo->prepare("UPDATE greenhouse_orders SET status = 'CONFIRMED', confirmed_at = CURRENT_TIMESTAMP WHERE id = :id");
    return $stmt->execute([':id' => $orderId]);
}


function deliverGreenhouseOrder(PDO $pdo, int $orderId, int $greenhouseId): bool {
    // перевірити що замовлення належить цій теплиці і отримати store_id
    $checkStmt = $pdo->prepare("SELECT id, status, store_id FROM greenhouse_orders WHERE id = :id AND greenhouse_id = :gh_id LIMIT 1");
    $checkStmt->execute([':id' => $orderId, ':gh_id' => $greenhouseId]);
    $order = $checkStmt->fetch(PDO::FETCH_ASSOC);
    if (!$order) {
        error_log("✗ Замовлення №{$orderId} не знайдено для теплиці {$greenhouseId}");
        return false;
    }
    $storeId = (int)$order['store_id'];

    // якщо замовлення вже доставлене то перевіримо чи quantity_delivered потрібна обновка
    if ($order['status'] === 'DELIVERED') {
        // перевірити чи всі items у цьому замовленні вже мають quantity_delivered
        $checkDeliveredStmt = $pdo->prepare("SELECT COUNT(*) as cnt FROM greenhouse_order_items 
                                            WHERE greenhouse_order_id = :order_id 
                                            AND (quantity_delivered = 0 OR quantity_delivered IS NULL)");
        $checkDeliveredStmt->execute([':order_id' => $orderId]);
        $result = $checkDeliveredStmt->fetch(PDO::FETCH_ASSOC);
        
        if ($result['cnt'] == 0) {
            // всі товари вже мають правильну quantity_delivered
            error_log("Замовлення №{$orderId} вже доставлено з правильною кількістю.");
            return true;
        }
        // якщо є items з quantity_delivered = 0, обновимо їх
    }

    // отримати items замовлення
    $itemsStmt = $pdo->prepare("SELECT id, product_id, quantity FROM greenhouse_order_items WHERE greenhouse_order_id = :order_id");
    $itemsStmt->execute([':order_id' => $orderId]);
    $items = $itemsStmt->fetchAll(PDO::FETCH_ASSOC);

    $pdo->beginTransaction();
    try {
        
        $isFirstDelivery = ($order['status'] !== 'DELIVERED');
        
        foreach ($items as $item) {
            $itemId = (int)$item['id'];
            $productId = (int)$item['product_id'];
            $qty = (int)$item['quantity'];

            if ($isFirstDelivery) {
                // зменшити в GREENHOUSE (location_id = greenhouse_id)
                $decreaseStmt = $pdo->prepare("UPDATE inventory SET quantity = quantity - :qty 
                                              WHERE product_id = :product_id AND location_type = 'GREENHOUSE' AND location_id = :gh_id");
                $decreaseStmt->execute([':qty' => $qty, ':product_id' => $productId, ':gh_id' => $greenhouseId]);

                // збільшити в STORE (location_id = store_id з замовлення)
                $storeStmt = $pdo->prepare("SELECT id FROM inventory WHERE product_id = :product_id AND location_type = 'STORE' AND location_id = :store_id LIMIT 1");
                $storeStmt->execute([':product_id' => $productId, ':store_id' => $storeId]);
                $storeRecord = $storeStmt->fetch(PDO::FETCH_ASSOC);

                if ($storeRecord) {
                    // UPDATE існуючий запис
                    $updateStoreStmt = $pdo->prepare("UPDATE inventory SET quantity = quantity + :qty WHERE id = :inv_id");
                    $updateStoreStmt->execute([':qty' => $qty, ':inv_id' => $storeRecord['id']]);
                } else {
                    // INSERT новий запис
                    $insertStoreStmt = $pdo->prepare("INSERT INTO inventory (product_id, location_type, location_id, quantity) VALUES (:product_id, 'STORE', :store_id, :qty)");
                    $insertStoreStmt->execute([':product_id' => $productId, ':store_id' => $storeId, ':qty' => $qty]);
                }
            }

            // обновити quantity_delivered для цього item замовлення
            $updateItemStmt = $pdo->prepare("UPDATE greenhouse_order_items SET quantity_delivered = :qty WHERE id = :item_id");
            $result = $updateItemStmt->execute([':qty' => $qty, ':item_id' => $itemId]);
            error_log("UPDATE quantity_delivered: item_id={$itemId}, qty={$qty}, result=" . var_export($result, true));
        }

        // Оновити статус замовлення на DELIVERED (якщо це перша доставка)
        if ($isFirstDelivery) {
            $orderStmt = $pdo->prepare("UPDATE greenhouse_orders SET status = 'DELIVERED', delivered_at = CURRENT_TIMESTAMP WHERE id = :id");
            $orderStmt->execute([':id' => $orderId]);
        }

        $pdo->commit();
        error_log("✓ Замовлення №{$orderId} оновлено. quantity_delivered встановлено.");
        return true;
    } catch (Exception $e) {
        $pdo->rollBack();
        error_log("✗ ПОМИЛКА в deliverGreenhouseOrder для замовлення №{$orderId}: " . $e->getMessage());
        return false;
    }
}

//скасувати замовлення
function cancelGreenhouseOrder(PDO $pdo, int $orderId, int $greenhouseId) {
    // перевірити що замовлення належить цій теплиці
    $checkStmt = $pdo->prepare("SELECT id, status FROM greenhouse_orders WHERE id = :id AND greenhouse_id = :gh_id LIMIT 1");
    $checkStmt->execute([':id' => $orderId, ':gh_id' => $greenhouseId]);
    $order = $checkStmt->fetch(PDO::FETCH_ASSOC);
    if (!$order) {
        return false;
    }

    // пкщо замовлення вже доставлено то не дозволяємо скасовувати
    if ($order['status'] === 'DELIVERED') {
        return 'delivered';
    }

    $stmt = $pdo->prepare("UPDATE greenhouse_orders SET status = 'CANCELLED' WHERE id = :id");
    return $stmt->execute([':id' => $orderId]);
}

function getSupplierSalesStats(PDO $pdo, int $greenhouseId): array {
    $stats = [];
    
    // загальна кількість замовлень та їх сума
    $totalStmt = $pdo->prepare("
        SELECT 
            COUNT(*) as total_orders,
            SUM(COALESCE(goi.quantity * goi.price_per_unit, 0)) as total_sum,
            go.status
        FROM greenhouse_orders go
        LEFT JOIN greenhouse_order_items goi ON go.id = goi.greenhouse_order_id
        WHERE go.greenhouse_id = :gh_id
        GROUP BY go.status
    ");
    $totalStmt->execute([':gh_id' => $greenhouseId]);
    $statusStats = $totalStmt->fetchAll(PDO::FETCH_ASSOC);
    $stats['by_status'] = $statusStats;
    
    // загальна сума всех замовлень
    $sumStmt = $pdo->prepare("
        SELECT SUM(COALESCE(goi.quantity * goi.price_per_unit, 0)) as grand_total
        FROM greenhouse_orders go
        LEFT JOIN greenhouse_order_items goi ON go.id = goi.greenhouse_order_id
        WHERE go.greenhouse_id = :gh_id
    ");
    $sumStmt->execute([':gh_id' => $greenhouseId]);
    $sumRow = $sumStmt->fetch(PDO::FETCH_ASSOC);
    $stats['grand_total'] = (float)($sumRow['grand_total'] ?? 0);
    
    // найпопулярніші товари
    $productsStmt = $pdo->prepare("
        SELECT 
            p.flower_name,
            p.category,
            SUM(goi.quantity) as total_qty,
            COUNT(DISTINCT go.id) as orders_count,
            SUM(COALESCE(goi.quantity * goi.price_per_unit, 0)) as product_sum
        FROM greenhouse_order_items goi
        JOIN greenhouse_orders go ON goi.greenhouse_order_id = go.id
        JOIN products p ON goi.product_id = p.id
        WHERE go.greenhouse_id = :gh_id AND go.status IN ('CONFIRMED', 'DELIVERED')
        GROUP BY p.id, p.flower_name, p.category
        ORDER BY total_qty DESC
        LIMIT 10
    ");
    $productsStmt->execute([':gh_id' => $greenhouseId]);
    $stats['top_products'] = $productsStmt->fetchAll(PDO::FETCH_ASSOC);
    
    // загальна кількість всех замовлень
    $countStmt = $pdo->prepare("SELECT COUNT(*) as count FROM greenhouse_orders WHERE greenhouse_id = :gh_id");
    $countStmt->execute([':gh_id' => $greenhouseId]);
    $countRow = $countStmt->fetch(PDO::FETCH_ASSOC);
    $stats['total_orders'] = (int)($countRow['count'] ?? 0);
    
    return $stats;
}
?>

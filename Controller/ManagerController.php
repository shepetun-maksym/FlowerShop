<?php
require_once __DIR__ . '/../config/db.php';

function managerController(): array {
    if (session_status() !== PHP_SESSION_ACTIVE) session_start();
    $result = ['errors' => [], 'success' => false, 'orders' => []];
    if (empty($_SESSION['user_id'])) {
        header('Location: login.php'); exit;
    }
    try {
        $pdo = getPDO();
        require_once __DIR__ . '/../Model/User.php';
        require_once __DIR__ . '/../Model/Product.php';
        
        $user = findUserById($pdo, (int)$_SESSION['user_id']);
        if (!$user || $user['role'] !== 'MANAGER') {
            $result['errors'][] = 'Доступ заборонено. Тільки менеджер може переглядати цю сторінку.';
            return $result;
        }
        
        // Обработка смены статуса заказа
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['order_id'], $_POST['status'])) {
            $orderId = (int)$_POST['order_id'];
            $newStatus = $_POST['status'];
            $allowed = ['NEW','PROCESSING','COMPLETED','CANCELLED'];
            
            if (in_array($newStatus, $allowed, true)) {
                // Переглянути поточний статус замовлення
                $stmtCurrent = $pdo->prepare("SELECT status FROM orders WHERE id = :id");
                $stmtCurrent->execute([':id' => $orderId]);
                $currentOrder = $stmtCurrent->fetch(PDO::FETCH_ASSOC);
                $currentStatus = $currentOrder['status'] ?? 'NEW';
                
                // Якщо статус змінюється з NEW на PROCESSING або COMPLETED то зменшити запаси
                if ($currentStatus === 'NEW' && ($newStatus === 'PROCESSING' || $newStatus === 'COMPLETED')) {

                    $stmtItems = $pdo->prepare("SELECT product_id, quantity FROM order_items WHERE order_id = :order_id");
                    $stmtItems->execute([':order_id' => $orderId]);
                    $orderItems = $stmtItems->fetchAll(PDO::FETCH_ASSOC);
                    
                    // Зменшити запаси для кожного товару
                    require_once __DIR__ . '/../Controller/CartController.php';
                    foreach ($orderItems as $item) {
                        if (!decrementProductStock($pdo, $item['product_id'], $item['quantity'])) {
                            throw new Exception("Помилка при зменшенні запасів для товара ID {$item['product_id']}");
                        }
                    }
                }
                
                // Оновити статус замовленняі
                if ($newStatus === 'COMPLETED') {
                    // Якщо статус змінюється на COMPLETED встановити paid TRUE
                    $stmt = $pdo->prepare("UPDATE orders SET status = :status, paid = TRUE WHERE id = :id");
                } else {
                    $stmt = $pdo->prepare("UPDATE orders SET status = :status WHERE id = :id");
                }
                $stmt->execute([':status' => $newStatus, ':id' => $orderId]);
                $result['success'] = 'Статус замовлення оновлено.';
            } else {
                $result['errors'][] = 'Неправильний статус.';
            }
        }
        
        $sql = "SELECT o.*, u.name, u.lastname, u.email, s.name AS store_name, s.address AS store_address 
                FROM orders o 
                LEFT JOIN users u ON o.user_id = u.id 
                LEFT JOIN stores s ON o.store_id = s.id 
                ORDER BY o.created_at DESC, o.id DESC";
        $orders = $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);
        
        $orderIds = array_column($orders, 'id');
        $items = [];
        if ($orderIds) {
            $in = implode(',', array_map('intval', $orderIds));
            $sql2 = "SELECT oi.*, p.flower_name, p.category, p.description FROM order_items oi JOIN products p ON oi.product_id = p.id WHERE oi.order_id IN ($in)";
            $itemsRaw = $pdo->query($sql2)->fetchAll(PDO::FETCH_ASSOC);
            foreach ($itemsRaw as $it) {
                $items[$it['order_id']][] = $it;
            }
        }
        
        foreach ($orders as &$o) {
            $o['items'] = $items[$o['id']] ?? [];
        }
        $result['orders'] = $orders;
    } catch (Exception $e) {
        $result['errors'][] = 'Системна помилка: ' . $e->getMessage();
    }
    return $result;
}
?>

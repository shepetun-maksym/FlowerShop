<?php
require_once __DIR__ . '/../Controller/AdminController.php';
if (session_status() !== PHP_SESSION_ACTIVE) session_start();
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../Model/User.php';

if (empty($_SESSION['user_id'])) {
    header('Location: login.php'); exit;
}

$pdo = getPDO();
$userId = (int)$_SESSION['user_id'];
$user = findUserById($pdo, $userId);

if (!$user || $user['role'] !== 'ADMIN') {
    echo 'Доступ заборонено';
    exit;
}

$errors = [];
$success = false;

// Отримати замовлення адміна
$orders = getAdminGreenhouseOrders($pdo, $userId);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'cancel_order') {
        $orderId = (int)($_POST['order_id'] ?? 0);
        
        // Перевірка що замовлення вже не доставлено
        $checkStmt = $pdo->prepare("SELECT status FROM greenhouse_orders WHERE id = :id AND admin_id = :admin_id LIMIT 1");
        $checkStmt->execute([':id' => $orderId, ':admin_id' => $userId]);
        $order = $checkStmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$order) {
            $errors[] = 'Замовлення не знайдено.';
        } elseif ($order['status'] === 'DELIVERED') {
            $errors[] = 'Неможливо скасувати доставлене замовлення.';
        } else {
            // Скасувати замовлення
            $cancelStmt = $pdo->prepare("UPDATE greenhouse_orders SET status = 'CANCELLED' WHERE id = :id");
            if ($cancelStmt->execute([':id' => $orderId])) {
                $success = 'Замовлення скасовано.';
                $orders = getAdminGreenhouseOrders($pdo, $userId);
            } else {
                $errors[] = 'Помилка при скасуванні замовлення.';
            }
        }
    }
}

function translateStatus($status) {
    $translations = [
        'PENDING' => 'Очікує підтвердження',
        'CONFIRMED' => 'Підтверджено',
        'DELIVERED' => 'Доставлено',
        'CANCELLED' => 'Скасовано'
    ];
    return $translations[$status] ?? $status;
}

function getStatusClass($status) {
    $classes = [
        'PENDING' => 'status-pending',
        'CONFIRMED' => 'status-confirmed',
        'DELIVERED' => 'status-delivered',
        'CANCELLED' => 'status-cancelled'
    ];
    return $classes[$status] ?? '';
}
?>
<!doctype html>
<html lang="uk">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Історія замовлень у теплиці</title>
  <link rel="stylesheet" href="../assets/css/header.css">
  <link rel="stylesheet" href="../assets/css/menu.css">
  <link rel="stylesheet" href="../assets/css/footer.css">
  <link rel="stylesheet" href="../assets/css/profile.css">
  <link rel="stylesheet" href="../assets/css/admin.css">
  <link rel="stylesheet" href="../assets/css/greenhouseorder_history.css">
</head>
<body>
<?php include __DIR__ . '/header.php'; ?>
<main class="container" style="padding:40px 18px">
  <h1>Історія замовлень у теплиці</h1>
  
  <a href="admin.php" class="back-link">← Повернутися на адмін панель</a>

  <?php if (!empty($errors)): ?>
    <div class="errors">
      <ul>
        <?php foreach ($errors as $e): ?>
          <li><?php echo htmlspecialchars($e); ?></li>
        <?php endforeach; ?>
      </ul>
    </div>
  <?php endif; ?>

  <?php if ($success): ?>
    <div class="success"><?php echo htmlspecialchars($success); ?></div>
  <?php endif; ?>

  <?php if (empty($orders)): ?>
    <div class="no-orders">
      <p>На даний момент немає замовлень у теплиці.</p>
      <a href="greenhouse_orders.php" class="btn" style="background-color: #007bff; color: white; padding: 10px 20px; border-radius: 4px; text-decoration: none; display: inline-block; margin-top: 20px;">Створити нове замовлення</a>
    </div>
  <?php else: ?>
    <div style="margin-bottom: 20px;">
      <p style="color: #666;">Всього замовлень: <strong><?php echo count($orders); ?></strong></p>
    </div>

    <?php foreach ($orders as $order): ?>
      <div class="order-card">
        <div class="order-header">
          <div>
            <div class="order-id">Замовлення №<?php echo htmlspecialchars($order['id']); ?></div>
            <div style="font-size: 12px; color: #666; margin-top: 4px;">
              Дата: <?php echo date('d.m.Y H:i', strtotime($order['created_at'])); ?>
            </div>
          </div>
          <span class="status-badge <?php echo getStatusClass($order['status']); ?>">
            <?php echo translateStatus($order['status']); ?>
          </span>
        </div>

        <div class="order-info">
          <div class="info-item">
            <span class="info-label">Теплиця</span>
            <span class="info-value"><?php echo htmlspecialchars($order['greenhouse_name'] ?? 'Не вказано'); ?></span>
          </div>
          <div class="info-item">
            <span class="info-label">Адреса теплиці</span>
            <span class="info-value"><?php echo htmlspecialchars($order['greenhouse_address'] ?? 'Не вказано'); ?></span>
          </div>
          <div class="info-item">
            <span class="info-label">Магазин призначення</span>
            <span class="info-value"><?php echo htmlspecialchars($order['store_name'] ?? 'Не вказано'); ?></span>
          </div>
          <div class="info-item">
            <span class="info-label">Адреса магазину</span>
            <span class="info-value"><?php echo htmlspecialchars($order['store_address'] ?? 'Не вказано'); ?></span>
          </div>
          <div class="info-item">
            <span class="info-label">Телефон теплиці</span>
            <span class="info-value"><?php echo htmlspecialchars($order['greenhouse_phone'] ?? 'Не вказано'); ?></span>
          </div>
          <div class="info-item">
            <span class="info-label">Постачальник</span>
            <span class="info-value"><?php echo htmlspecialchars(($order['supplier_name'] ?? '') . ' ' . ($order['supplier_lastname'] ?? '')); ?></span>
          </div>
          <div class="info-item">
            <span class="info-label">Телефон постачальника</span>
            <span class="info-value"><?php echo htmlspecialchars($order['supplier_phone'] ?? 'Не вказано'); ?></span>
          </div>
          <?php if ($order['confirmed_at']): ?>
          <div class="info-item">
            <span class="info-label">Дата підтвердження</span>
            <span class="info-value"><?php echo date('d.m.Y H:i', strtotime($order['confirmed_at'])); ?></span>
          </div>
          <?php endif; ?>
          <?php if ($order['delivered_at']): ?>
          <div class="info-item">
            <span class="info-label">Дата доставки</span>
            <span class="info-value"><?php echo date('d.m.Y H:i', strtotime($order['delivered_at'])); ?></span>
          </div>
          <?php endif; ?>
        </div>

        <div class="order-items">
          <h4>Товари в замовленні:</h4>
          <table class="items-table">
            <thead>
              <tr>
                <th>Товар</th>
                <th>Замовлено</th>
                <th>Ціна за одиницю</th>
                <th>Сума</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($order['items'] as $item): ?>
              <tr>
                <td><?php echo htmlspecialchars($item['flower_name']); ?></td>
                <td><?php echo $item['quantity']; ?> шт.</td>
                <td><?php echo number_format((float)$item['price_per_unit'], 2); ?> грн</td>
                <td><?php echo number_format((float)$item['price_per_unit'] * $item['quantity'], 2); ?> грн</td>
              </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>

        <div class="order-actions">
          <?php if ($order['status'] !== 'DELIVERED' && $order['status'] !== 'CANCELLED'): ?>
            <form method="post" style="display: inline;" onsubmit="return confirm('Ви впевнені що хочете скасувати це замовлення?');">
              <input type="hidden" name="action" value="cancel_order">
              <input type="hidden" name="order_id" value="<?php echo $order['id']; ?>">
              <button type="submit" class="btn btn-danger">Скасувати замовлення</button>
            </form>
          <?php endif; ?>
          <a href="greenhouse_orders.php" class="btn btn-secondary">До замовлень</a>
        </div>
      </div>
    <?php endforeach; ?>
  <?php endif; ?>
</main>

<?php include __DIR__ . '/footer.php'; ?>
</body>
</html>

<?php
require_once __DIR__ . '/../Controller/SupplierOrdersController.php';
require_once __DIR__ . '/../Model/Product.php';
if (session_status() !== PHP_SESSION_ACTIVE) session_start();
$ctx = supplierOrdersController();
?>
<!doctype html>
<html lang="uk">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Замовлення</title>
  <link rel="stylesheet" href="../assets/css/header.css">
  <link rel="stylesheet" href="../assets/css/menu.css">
  <link rel="stylesheet" href="../assets/css/footer.css">
  <link rel="stylesheet" href="../assets/css/profile.css">
  <link rel="stylesheet" href="../assets/css/admin.css">
  <link rel="stylesheet" href="../assets/css/supplier_orders.css">
  <script>
    function showOrders() {
      document.querySelector('.orders-container').classList.remove('hidden');
      document.querySelector('.stats-container').classList.remove('active');
      document.getElementById('btn-orders').classList.add('active');
      document.getElementById('btn-stats').classList.remove('active');
    }
    function showStats() {
      document.querySelector('.orders-container').classList.add('hidden');
      document.querySelector('.stats-container').classList.add('active');
      document.getElementById('btn-stats').classList.add('active');
      document.getElementById('btn-orders').classList.remove('active');
    }
  </script>
</head>
<body>
<?php include __DIR__ . '/header.php'; ?>
<main class="container" style="padding:40px 18px">
  <h1>Замовлення від магазину</h1>
  
  <?php if (!empty($ctx['greenhouses']) && count($ctx['greenhouses']) > 1): ?>
    <div style="margin-bottom: 20px; padding: 15px; background: #f0f8f0; border-radius: 8px;">
      <label for="greenhouse-selector" style="font-weight: bold; margin-right: 10px;">Виберіть теплицю:</label>
      <select id="greenhouse-selector" style="padding: 8px; border: 1px solid #ccc; border-radius: 4px; font-size: 14px;" onchange="window.location.href = '?greenhouse_id=' + this.value">
        <?php foreach ($ctx['greenhouses'] as $gh): ?>
          <option value="<?php echo $gh['id']; ?>" <?php echo ($ctx['greenhouse']['id'] == $gh['id']) ? 'selected' : ''; ?>>
            <?php echo htmlspecialchars($gh['name']); ?>
          </option>
        <?php endforeach; ?>
      </select>
    </div>
  <?php endif; ?>
  
  <?php if (!empty($ctx['errors'])): ?>
    <div class="errors"><ul><?php foreach ($ctx['errors'] as $e) echo '<li>'.htmlspecialchars($e).'</li>'; ?></ul></div>
  <?php endif; ?>
  <?php if (!empty($ctx['success'])): ?>
    <div class="success"><?php echo htmlspecialchars($ctx['success']); ?></div>
  <?php endif; ?>

  <div class="toggle-buttons">
    <button class="toggle-btn active" id="btn-orders" onclick="showOrders()">📦 Замовлення</button>
    <button class="toggle-btn" id="btn-stats" onclick="showStats()">📊 Статистика</button>
  </div>

  <div class="orders-container">
  <?php if ($ctx['greenhouse']): ?>
    <div style="background:#e8f4f8;padding:15px;border-radius:8px;margin-bottom:30px;">
      <strong>Теплиця:</strong> <?php echo htmlspecialchars($ctx['greenhouse']['name']); ?>
    </div>

    <?php if (empty($ctx['orders'])): ?>
      <p>Немає замовлень.</p>
    <?php else: ?>
      <?php foreach ($ctx['orders'] as $order): ?>
        <div class="order-card">
          <div class="order-header">
            <div>
              <h3>Замовлення #<?php echo $order['id']; ?></h3>
              <p style="margin:5px 0;color:#666;">
                Дата: <?php echo date('d.m.Y H:i', strtotime($order['created_at'])); ?>
              </p>
            </div>
            <span class="order-status status-<?php echo strtolower($order['status']); ?>">
              <?php 
                $statusLabels = [
                  'PENDING' => 'Очікує підтвердження',
                  'CONFIRMED' => 'Підтверджено',
                  'DELIVERED' => 'Доставлено',
                  'CANCELLED' => 'Скасовано'
                ];
                echo $statusLabels[$order['status']] ?? $order['status'];
              ?>
            </span>
          </div>

          <div style="background:#f0f8f0;padding:12px;border-radius:6px;margin:15px 0;border-left:4px solid #2f8f4a;">
            <h4 style="margin:0 0 10px 0;color:#6b4c3b;">Інформація замовлення:</h4>
            <p style="margin:5px 0;"><strong>Адміністратор:</strong> <?php echo htmlspecialchars($order['admin_name'] . ' ' . $order['admin_lastname']); ?><?php if ($order['admin_phone']) echo ' | ' . htmlspecialchars($order['admin_phone']); ?></p>
            <p style="margin:5px 0;"><strong>Магазин призначення:</strong> <?php echo htmlspecialchars($order['store_name'] ?? 'Не вказано'); ?></p>
            <p style="margin:5px 0;"><strong>Адреса магазину:</strong> <?php echo htmlspecialchars($order['store_address'] ?? 'Не вказано'); ?></p>
            <?php if ($order['greenhouse_address']): ?>
              <p style="margin:5px 0;"><strong>Адреса теплиці:</strong> <?php echo htmlspecialchars($order['greenhouse_address']); ?></p>
            <?php endif; ?>
          </div>

          <h4>Товари в замовленні:</h4>
          <table class="order-items-table">
            <thead>
              <tr>
                <th>Товар</th>
                <th>Кількість</th>
                <th>Ціна за одиницю</th>
                <th>Сума</th>
              </tr>
            </thead>
            <tbody>
              <?php $total = 0; foreach ($order['items'] as $item): 
                $sum = (float)$item['price_per_unit'] * (int)$item['quantity'];
                $total += $sum;
              ?>
                <tr>
                  <td><?php echo htmlspecialchars($item['flower_name']); ?></td>
                  <td><?php echo $item['quantity']; ?> шт.</td>
                  <td><?php echo number_format((float)$item['price_per_unit'], 2); ?> грн</td>
                  <td><?php echo number_format($sum, 2); ?> грн</td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
          <p style="text-align:right;font-weight:bold;margin-top:10px;">
            Всього: <?php echo number_format($total, 2); ?> грн
          </p>

          <h4>Змінити статус:</h4>
          <form method="post" style="display:flex;gap:15px;align-items:center;margin-bottom:20px;">
            <input type="hidden" name="action" value="update_status">
            <input type="hidden" name="order_id" value="<?php echo $order['id']; ?>">
            <select name="status" 
                    style="padding:8px;border:1px solid #ccc;border-radius:4px;" 
                    onchange="this.form.submit()"
                    <?php echo $order['status'] === 'DELIVERED' ? 'disabled' : ''; ?>>
              <option value="PENDING" <?php echo $order['status'] === 'PENDING' ? 'selected' : ''; ?>>Очікує підтвердження</option>
              <option value="CONFIRMED" <?php echo $order['status'] === 'CONFIRMED' ? 'selected' : ''; ?>>Підтверджено</option>
              <option value="DELIVERED" <?php echo $order['status'] === 'DELIVERED' ? 'selected' : ''; ?>>Доставлено</option>
              <option value="CANCELLED" <?php echo $order['status'] === 'CANCELLED' ? 'selected' : ''; ?>>Скасовано</option>
            </select>
          </form>

          <?php if ($order['status'] !== 'DELIVERED' && $order['status'] !== 'CANCELLED'): ?>
            <p style="color:#666;font-size:0.9em;">Виберіть новий статус у меню</p>
          <?php else: ?>
            <p style="color:#0f5132;font-weight:bold;font-size:0.9em;">✓ Замовлення завершено.</p>
          <?php endif; ?>
        </div>
      <?php endforeach; ?>
    <?php endif; ?>
  <?php endif; ?>
  </div>

  <div class="stats-container">
    <h2 style="color:#2f8f4a;margin-bottom:20px;">Статистика продажів</h2>
    
    <div class="stats-grid">
      <div class="stat-card">
        <h3>Всього замовлень</h3>
        <div class="stat-value"><?php echo $ctx['stats']['total_orders'] ?? 0; ?></div>
      </div>
      
      <div class="stat-card">
        <h3>Загальна сума продажів</h3>
        <div class="stat-value currency"><?php echo number_format($ctx['stats']['grand_total'] ?? 0, 2); ?></div>
      </div>
    </div>

    <div style="background:white;padding:20px;border-radius:8px;margin-bottom:30px;box-shadow:0 2px 8px rgba(0,0,0,0.06);">
      <h3 style="color:#2f8f4a;margin-top:0;">Замовлення за статусом</h3>
      <table class="products-table">
        <thead>
          <tr>
            <th>Статус</th>
            <th>Кількість</th>
            <th>Сума</th>
          </tr>
        </thead>
        <tbody>
          <?php 
            $statusLabels = [
              'PENDING' => 'Очікує підтвердження',
              'CONFIRMED' => 'Підтверджено',
              'DELIVERED' => 'Доставлено',
              'CANCELLED' => 'Скасовано'
            ];
            if (!empty($ctx['stats']['by_status'])): 
              foreach ($ctx['stats']['by_status'] as $stat): 
                $label = $statusLabels[$stat['status']] ?? $stat['status']; 
          ?>
            <tr>
              <td><strong><?php echo $label; ?></strong></td>
              <td><?php echo (int)$stat['total_orders']; ?></td>
              <td><?php echo number_format((float)($stat['total_sum'] ?? 0), 2); ?> грн</td>
            </tr>
          <?php 
              endforeach; 
            endif; 
          ?>
        </tbody>
      </table>
    </div>

    <div style="background:white;padding:20px;border-radius:8px;box-shadow:0 2px 8px rgba(0,0,0,0.06);">
      <h3 style="color:#2f8f4a;margin-top:0;">Top 10 найпопулярніших товарів</h3>
      <?php if (!empty($ctx['stats']['top_products'])): ?>
        <table class="products-table">
          <thead>
            <tr>
              <th>Товар</th>
              <th>Категорія</th>
              <th>Кількість</th>
              <th>Замовлень</th>
              <th>Сума</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($ctx['stats']['top_products'] as $product): ?>
              <tr>
                <td><?php echo htmlspecialchars($product['flower_name']); ?></td>
                <td><?php echo getCategoryLabel($product['category']); ?></td>
                <td><?php echo (int)$product['total_qty']; ?> шт.</td>
                <td><?php echo (int)$product['orders_count']; ?></td>
                <td><?php echo number_format((float)$product['product_sum'], 2); ?> грн</td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      <?php else: ?>
        <p style="color:#666;text-align:center;padding:20px;">Немає підтвджених замовлень для статистики</p>
      <?php endif; ?>
    </div>
  </div>
</main>

<?php include __DIR__ . '/footer.php'; ?>
</body>
</html>

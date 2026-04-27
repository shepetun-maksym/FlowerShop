<!doctype html>
<html lang="uk">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Менеджер: замовлення</title>
  <link rel="stylesheet" href="../assets/css/header.css">
  <link rel="stylesheet" href="../assets/css/menu.css">
  <link rel="stylesheet" href="../assets/css/footer.css">
  <link rel="stylesheet" href="../assets/css/profile.css">
  <link rel="stylesheet" href="../assets/css/manager.css">
</head>
<body>
<?php
require_once __DIR__ . '/../Controller/ManagerController.php';
require_once __DIR__ . '/../Model/Product.php';
$ctx = managerController();
if (session_status() !== PHP_SESSION_ACTIVE) session_start();
?>
<?php include __DIR__ . '/header.php'; ?>
<main class="container" style="padding:40px 18px">
  <?php if (!empty($ctx['errors'])): ?>
    <div class="errors"><ul><?php foreach ($ctx['errors'] as $e) echo '<li>'.htmlspecialchars($e).'</li>'; ?></ul></div>
  <?php endif; ?>
  <?php if (!empty($ctx['success'])): ?>
    <div class="success"><?php echo htmlspecialchars($ctx['success']); ?></div>
  <?php endif; ?>

  <section class="manager-section" style="max-width:1000px;margin:0 auto;">
    <h2 style="margin-bottom:18px;">Всі замовлення</h2>
    <?php foreach ($ctx['orders'] as $order): ?>
    <div class="order-card" style="border:1px solid #eee;border-radius:8px;padding:18px 18px 12px 18px;margin-bottom:18px;box-shadow:0 2px 8px #0001;">
      <div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:12px;">
        <div>
          <b>№ замовлення:</b> <?php echo $order['id']; ?><br>
          <small style="color:#666;">Клієнт: <?php echo htmlspecialchars(($order['name'] ?? '') . ' ' . ($order['lastname'] ?? '')); ?></small><br>
          <small style="color:#666;">Телефон: <?php echo htmlspecialchars($order['phone']); ?></small><br>
          <small style="color:#666;">Email: <?php echo htmlspecialchars($order['email']); ?></small>
        </div>
        <div style="text-align:right;">
          <span style="margin-right:18px;"><b>Статус:</b> 
            <form method="post" style="display:inline;">
              <input type="hidden" name="order_id" value="<?php echo $order['id']; ?>">
              <select name="status" class="status-select" style="padding:6px 10px;border-radius:4px;border:1px solid #ddd;">
                <option value="NEW" <?php if ($order['status']==='NEW') echo 'selected'; ?>>Нове</option>
                <option value="PROCESSING" <?php if ($order['status']==='PROCESSING') echo 'selected'; ?>>В обробці</option>
                <option value="COMPLETED" <?php if ($order['status']==='COMPLETED') echo 'selected'; ?>>Завершено</option>
                <option value="CANCELLED" <?php if ($order['status']==='CANCELLED') echo 'selected'; ?>>Скасовано</option>
              </select>
              <button type="submit" class="btn btn-primary" style="padding:6px 12px;margin-left:8px;">Оновити</button>
            </form>
          </span><br>
          <span style="margin-top:8px;display:inline-block;"><b>Оплачено:</b> <span style="color:<?php echo $order['paid'] ? '#2e8b57' : '#d9534f'; ?>;font-weight:500"><?php echo $order['paid'] ? 'Так' : 'Ні'; ?></span></span><br>
          <span style="margin-top:8px;display:inline-block;"><b>Спосіб оплати:</b> <span style="color:#1976D2;font-weight:500"><?php echo $order['payment_method'] === 'cash' ? 'Оплата при отриманні' : 'Оплата онлайн'; ?></span></span>
        </div>
        <div style="color:#888;font-size:13px;"><b>Дата:</b> <?php echo date('d.m.Y H:i', strtotime($order['created_at'])); ?></div>
      </div>
      
      <div style="margin-top:12px;padding:12px;background:#f0f4f8;border-radius:6px;border-left:4px solid #2196F3;">
        <b style="color:#1976D2;">Спосіб доставки:</b>
        <?php if ($order['delivery_type'] === 'PICKUP'): ?>
          <span style="color:#2e8b57;font-weight:500;">Самовивіз</span>
          <?php if (!empty($order['store_name'])): ?>
            <div style="margin-top:6px;color:#555;">
              <b>Магазин:</b> <?php echo htmlspecialchars($order['store_name']); ?><br>
              <b>Адреса магазину:</b> <?php echo htmlspecialchars($order['store_address']); ?>
            </div>
          <?php endif; ?>
        <?php elseif ($order['delivery_type'] === 'DELIVERY'): ?>
          <span style="color:#d9534f;font-weight:500;">Доставка за адресою</span>
          <?php if (!empty($order['delivery_address'])): ?>
            <div style="margin-top:6px;color:#555;">
              <b>Адреса доставки:</b> <?php echo htmlspecialchars($order['delivery_address']); ?>
            </div>
          <?php endif; ?>
        <?php endif; ?>
      </div>
      
      <div style="margin-top:10px;">
        <table style="width:100%;border-collapse:collapse;">
          <thead>
            <tr style="background:#f7f7f7;">
              <th style="padding:6px 8px;text-align:left;">Назва</th>
              <th style="padding:6px 8px;text-align:left;">Категорія</th>
              <th style="padding:6px 8px;text-align:left;">Опис</th>
              <th style="padding:6px 8px;text-align:left;">Кількість</th>
              <th style="padding:6px 8px;text-align:left;">Ціна</th>
              <th style="padding:6px 8px;text-align:left;">Сума</th>
            </tr>
          </thead>
          <tbody>
            <?php $sum = 0; foreach ($order['items'] as $item): $itemSum = $item['price_at_purchase'] * $item['quantity']; $sum += $itemSum; ?>
            <tr>
              <td style="padding:6px 8px;"> <?php echo htmlspecialchars($item['flower_name']); ?> </td>
              <td style="padding:6px 8px;"> <?php echo getCategoryLabel($item['category']); ?> </td>
              <td style="padding:6px 8px;"> <?php echo htmlspecialchars($item['description']); ?> </td>
              <td style="padding:6px 8px;"> <?php echo $item['quantity']; ?> </td>
              <td style="padding:6px 8px;"> <?php echo number_format($item['price_at_purchase'], 2); ?> грн </td>
              <td style="padding:6px 8px;"> <?php echo number_format($itemSum, 2); ?> грн </td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
        <div style="text-align:right;margin-top:8px;font-weight:600;">Всього: <?php echo number_format($sum, 2); ?> грн</div>
      </div>
    </div>
    <?php endforeach; ?>
  </section>
</main>
<?php include __DIR__ . '/footer.php'; ?>
</body>
</html>

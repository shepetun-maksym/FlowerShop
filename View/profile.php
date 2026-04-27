<!doctype html>
<html lang="uk">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Мій кабінет</title>
  <link rel="stylesheet" href="../assets/css/header.css">
  <link rel="stylesheet" href="../assets/css/menu.css">
  <link rel="stylesheet" href="../assets/css/footer.css">
  <link rel="stylesheet" href="../assets/css/profile.css">
</head>
<body>
<?php
require_once __DIR__ . '/../Controller/UserController.php';
require_once __DIR__ . '/../Model/Product.php';
$ctx = profileController();
if (session_status() !== PHP_SESSION_ACTIVE) session_start();
?>
<?php include __DIR__ . '/header.php'; ?>
<main class="container" style="padding:40px 18px">
  <h1>Мій кабінет</h1>
  <?php if (!empty($ctx['errors'])): ?>
    <div class="errors"><ul><?php foreach ($ctx['errors'] as $e) echo '<li>'.htmlspecialchars($e).'</li>'; ?></ul></div>
  <?php endif; ?>
  <?php if (!empty($ctx['success'])): ?>
    <div class="success">Дані збережено.</div>
  <?php endif; ?>

  <?php $u = $ctx['user'] ?? []; ?>
  <div class="profile-page">
    <aside class="profile-aside">
      <?php
        $fullName = trim(($u['name'] ?? '') . ' ' . ($u['lastname'] ?? ''));
        $initial = '';
        if ($fullName !== '') {
            if (function_exists('mb_substr')) $initial = mb_substr($fullName, 0, 1);
            else $initial = substr($fullName, 0, 1);
        }
      ?>
      <div class="avatar"><?php echo htmlspecialchars($initial); ?></div>
      <div class="user-name"><?php echo htmlspecialchars(($u['name'] ?? '') . ' ' . ($u['lastname'] ?? '')); ?></div>
      <div class="user-email"><?php echo htmlspecialchars($u['email'] ?? ''); ?></div>
    </aside>
    <section class="profile-main">
      <form method="post" action="profile.php" class="profile-form">
        <div class="row">
          <label>Ім'я
            <input name="name" value="<?php echo htmlspecialchars($_POST['name'] ?? $u['name'] ?? ''); ?>">
          </label>
          <label>Прізвище
            <input name="lastname" value="<?php echo htmlspecialchars($_POST['lastname'] ?? $u['lastname'] ?? ''); ?>">
          </label>
        </div>
        <div class="row">
          <label>Email
            <input name="email" value="<?php echo htmlspecialchars($_POST['email'] ?? $u['email'] ?? ''); ?>">
          </label>
          <label>Номер телефону
            <input name="phone" value="<?php echo htmlspecialchars($_POST['phone'] ?? $u['phone'] ?? ''); ?>">
          </label>
        </div>

        <h3>Змінити пароль</h3>
        <p class="hint">Щоб змінити пароль, введіть поточний пароль для підтвердження.</p>
        <div class="row">
          <label>Поточний пароль
            <input type="password" name="current_password" autocomplete="current-password">
          </label>
        </div>
        <div class="row">
          <label>Новий пароль
            <input type="password" name="password" autocomplete="new-password">
          </label>
          <label>Підтвердження пароля
            <input type="password" name="password_confirm" autocomplete="new-password">
          </label>
        </div>

        <div class="actions"><button class="btn btn-primary" type="submit">Зберегти зміни</button></div>
      </form>
    </section>
  </div>
</main>
<?php
$orders = $ctx['orders'] ?? [];
?>

<?php if (!empty($orders)): ?>
<div class="profile-orders" style="max-width:1000px;margin:32px auto 0 auto;background:#fff;border-radius:8px;padding:24px;">
  <h2 style="margin-bottom:18px;">Мої замовлення</h2>
  <?php foreach ($orders as $order): ?>
    <div class="order-card" style="border:1px solid #eee;border-radius:8px;padding:18px 18px 12px 18px;margin-bottom:18px;box-shadow:0 2px 8px #0001;">
      <div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;">
        <div>
          <b>Номер замовлення:</b> <?php echo $order['id']; ?>
          <span style="margin-left:18px;"><b>Статус:</b> <span style="color:#007bff;font-weight:500"><?php 
            $statusTexts = ['NEW' => 'Нове', 'PROCESSING' => 'В обробці', 'COMPLETED' => 'Завершено', 'CANCELLED' => 'Скасовано'];
            echo htmlspecialchars($statusTexts[$order['status']] ?? $order['status']);
          ?></span></span>
          <span style="margin-left:18px;"><b>Оплачено:</b> <span style="color:<?php echo $order['paid'] ? '#2e8b57' : '#d9534f'; ?>;font-weight:500"><?php echo $order['paid'] ? 'Так' : 'Ні'; ?></span></span>
          <span style="margin-left:18px;"><b>Спосіб оплати:</b> <span style="color:#1976D2;font-weight:500"><?php echo $order['payment_method'] === 'cash' ? 'Оплата при отриманні' : 'Оплата онлайн'; ?></span></span>
          <?php
            $canCancel = false;
            if ($order['status'] !== 'CANCELLED' && $order['status'] !== 'COMPLETED') {
              // Перевіряємо роль користувача
              if (!empty($u['role']) && $u['role'] === 'MANAGER') {
                $canCancel = true;
              } elseif ($order['status'] === 'NEW') {
                $canCancel = true;
              }
            }
          ?>
          <?php if ($canCancel): ?>
            <form method="post" style="display:inline;margin-left:18px">
              <input type="hidden" name="cancel_order_id" value="<?php echo $order['id']; ?>">
              <button type="submit" onclick="return confirm('Справді скасувати це замовлення?')" style="background:#f44336;color:#fff;border:none;padding:4px 14px;border-radius:5px;cursor:pointer;">Скасувати</button>
            </form>
          <?php endif; ?>
        </div>
        <div style="color:#888;font-size:13px;"><b>Дата:</b> <?php echo date('d.m.Y H:i', strtotime($order['created_at'])); ?></div>
      </div>
      
      <!-- Інформація про доставку -->
      <div style="margin-top:12px;padding:12px;background:#f0f4f8;border-radius:6px;border-left:4px solid #2196F3;">
        <b style="color:#1976D2;">Спосіб доставки:</b>
        <?php if ($order['delivery_type'] === 'PICKUP'): ?>
          <span style="color:#2e8b57;font-weight:500;">Самовивіз</span>
          <?php if (!empty($order['store_name'])): ?>
            <div style="margin-top:6px;color:#555;">
              <b>Магазин:</b> <?php echo htmlspecialchars($order['store_name']); ?><br>
              <b>Адреса:</b> <?php echo htmlspecialchars($order['store_address']); ?>
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
              <th style="padding:6px 8px;text-align:left;">Бонус</th>
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
              <td style="padding:6px 8px;"> <?php echo $item['discount_pct'] ? $item['discount_pct'] . '%' : '—'; ?> </td>
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
</div>
<?php endif; ?>

<?php include __DIR__ . '/footer.php'; ?>
</body>
</html>

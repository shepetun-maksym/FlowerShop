<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/stripe.php';
require_once __DIR__ . '/../vendor/autoload.php';
if (session_status() !== PHP_SESSION_ACTIVE) session_start();

\Stripe\Stripe::setApiKey(STRIPE_SECRET_KEY);
$orderId = (int)($_GET['order_id'] ?? 0);
$paymentIntentId = $_GET['payment_intent'] ?? '';
$paymentMethod = $_GET['payment_method'] ?? '';

$pdo = getPDO();
$isPaid = false;
$message = '';
$order = null;

if ($paymentMethod === 'cash') {
    // Оплата при отриманні - не оплачено онлайн
    $pdo->prepare("UPDATE orders SET paid = FALSE WHERE id = ?")
        ->execute([$orderId]);
    $isPaid = false;
    $message = 'Замовлення створено! Оплата при отриманні.';
} elseif ($paymentIntentId) {
    // Онлайн оплата через Stripe
    $intent = \Stripe\PaymentIntent::retrieve($paymentIntentId);
    if ($intent->status === 'succeeded') {
        $pdo->prepare("UPDATE orders SET paid = TRUE WHERE id = ?")
            ->execute([$orderId]);
        $isPaid = true;
        $message = 'Оплату підтверджено!';
    }
}

// Очистити кошик і доставку
$_SESSION['cart'] = [];
$_SESSION['delivery'] = ['type' => 'PICKUP', 'store_id' => null, 'address' => ''];
?>
<!doctype html>
<html lang="uk">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Дякуємо за замовлення!</title>
  <link rel="stylesheet" href="../assets/css/header.css">
  <link rel="stylesheet" href="../assets/css/menu.css">
  <link rel="stylesheet" href="../assets/css/footer.css">
  <link rel="stylesheet" href="../assets/css/payment_success.css">
</head>
<body>
<?php include __DIR__ . '/header.php'; ?>

<main class="container">
  <div class="success-container">
    <div class="success-card">
      <div class="success-icon">✓</div>
      
      <h1 class="success-title">Дякуємо за замовлення!</h1>
      <p class="success-subtitle"><?php echo htmlspecialchars($message); ?></p>

      <?php if ($order): ?>
      <div class="order-details">
        <div class="detail-row">
          <span class="detail-label">Номер замовлення:</span>
          <span class="detail-value order-id">#<?php echo $order['id']; ?></span>
        </div>
        <div class="detail-row">
          <span class="detail-label">Сума:</span>
          <span class="detail-value"><?php echo number_format($order['total'], 2); ?> грн</span>
        </div>
        <div class="detail-row">
          <span class="detail-label">Дата:</span>
          <span class="detail-value"><?php echo date('d.m.Y H:i', strtotime($order['created_at'])); ?></span>
        </div>
        <div class="detail-row">
          <span class="detail-label">Статус оплати:</span>
          <span class="detail-value" style="color: <?php echo $isPaid ? 'var(--green)' : 'var(--accent)'; ?>;">
            <?php echo $isPaid ? '✓ Оплачено' : '⏱ Очікується при отриманні'; ?>
          </span>
        </div>
      </div>
      <?php endif; ?>

      <p class="success-message">
        Ми отримали ваше замовлення. Деталі та статус можна перевірити у вашому профілі. 
        Дякуємо за покупку! 🌸
      </p>

      <div class="action-buttons">
        <a href="menu.php" class="btn btn-primary">← Повернутися до каталогу</a>
        <a href="profile.php" class="btn btn-secondary">Мої замовлення</a>
      </div>
    </div>
  </div>
</main>

<?php include __DIR__ . '/footer.php'; ?>
</body>
</html>
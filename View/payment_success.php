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
<!doctype html><html lang="uk"><head><meta charset="utf-8"><title>Дякуємо!</title>
<link rel="stylesheet" href="../assets/css/header.css"></head>
<body>
<?php include __DIR__ . '/header.php'; ?>
<div style="text-align:center;padding:80px 20px;">
  <h1 style="color:<?php echo $isPaid ? '#2e8b57' : '#ff9800'; ?>;">✓ <?php echo htmlspecialchars($message); ?></h1>
  <p>Замовлення #<?php echo $orderId; ?> успішно оформлено.</p>
  <a href="menu.php" class="btn btn-primary">Повернутися до каталогу</a>
</div>
</body></html>
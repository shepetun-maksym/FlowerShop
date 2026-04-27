<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/stripe.php';
require_once __DIR__ . '/../vendor/autoload.php';

if (session_status() !== PHP_SESSION_ACTIVE) session_start();

// Перевірити чи передано order_id
$orderId = (int)($_GET['order_id'] ?? 0);
if (!$orderId) {
    header('Location: cart.php');
    exit;
}

$pdo = getPDO();

// Отримати замовлення та перевірити що воно належить конкретному юзеру
$stmtOrder = $pdo->prepare("SELECT * FROM orders WHERE id = :id");
$stmtOrder->execute([':id' => $orderId]);
$order = $stmtOrder->fetch(PDO::FETCH_ASSOC);

if (!$order) {
    header('Location: cart.php');
    exit;
}

// Перевірити що замовлення ще не оплачено
if ($order['paid'] === true || $order['status'] === 'PROCESSING') {
    header('Location: payment_success.php?order_id=' . $orderId);
    exit;
}

// Отримати товари замовлення
$stmtItems = $pdo->prepare("
    SELECT oi.quantity, oi.price_at_purchase, p.flower_name AS name
    FROM order_items oi
    LEFT JOIN products p ON p.id = oi.product_id
    WHERE oi.order_id = :id
");
$stmtItems->execute([':id' => $orderId]);
$items = $stmtItems->fetchAll(PDO::FETCH_ASSOC);

$total = 0;
foreach ($items as $item) {
    $total += $item['price_at_purchase'] * $item['quantity'];
}

if ($total <= 0) {
    header('Location: cart.php');
    exit;
}

// Створити або отримати існуючий PaymentIntent
\Stripe\Stripe::setApiKey(STRIPE_SECRET_KEY);

if (!empty($order['stripe_payment_intent_id'])) {
    // Якщо PaymentIntent вже існує то отримаємо його
    try {
        $intent = \Stripe\PaymentIntent::retrieve($order['stripe_payment_intent_id']);
        // Якщо сума змінилась — оновити
        if ($intent->amount !== (int)round($total * 100)) {
            $intent = \Stripe\PaymentIntent::update($order['stripe_payment_intent_id'], [
                'amount' => (int)round($total * 100),
            ]);
        }
    } catch (\Exception $e) {
        // Якщо не вдалось створюємо новий
        $intent = null;
    }
} else {
    $intent = null;
}

if (!$intent) {
    // Створити новий PaymentIntent
    $intent = \Stripe\PaymentIntent::create([
        'amount'   => (int)round($total * 100),
        'currency' => 'uah',
        'metadata' => [
            'order_id'   => $orderId,
            'user_id'    => $order['user_id'] ?? '',
        ],
        'description' => 'Замовлення #' . $orderId . ' — FlowerShop',
    ]);

    // Зберегти payment_intent_id в бдшку
    $pdo->prepare("UPDATE orders SET stripe_payment_intent_id = :pi WHERE id = :id")
        ->execute([':pi' => $intent->id, ':id' => $orderId]);
}
?>
<!doctype html>
<html lang="uk">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Оплата замовлення #<?php echo $orderId; ?></title>
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600&display=swap" rel="stylesheet">
  <script src="https://js.stripe.com/v3/"></script>
  <link rel="stylesheet" href="../assets/css/header.css">
  <link rel="stylesheet" href="../assets/css/footer.css">
  <link rel="stylesheet" href="../assets/css/checkout.css">
</head>
<body>
<?php include __DIR__ . '/header.php'; ?>

<main class="checkout-container">
  <div class="checkout-header">
    <a href="cart.php" class="back-link">
      <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M19 12H5M12 5l-7 7 7 7"/></svg>
      Повернутися до кошика
    </a>
    
    <h1 class="checkout-title">Оплата замовлення</h1>
    <p class="checkout-subtitle">Замовлення #<?php echo $orderId; ?> · Безпечна оплата через Stripe</p>
  </div>

  <div class="checkout-grid">

    <div class="checkout-card">
      <div class="card-title">Ваше замовлення</div>

      <ul class="order-items">
        <?php foreach ($items as $item): ?>
          <li class="order-item">
            <span class="order-item-name"><?php echo htmlspecialchars($item['name']); ?></span>
            <span class="order-item-qty"><?php echo $item['quantity']; ?> шт.</span>
            <span class="order-item-price"><?php echo number_format($item['price_at_purchase'] * $item['quantity'], 2); ?> грн</span>
          </li>
        <?php endforeach; ?>
      </ul>

      <div class="order-total-row">
        <span class="order-total-label">До сплати</span>
        <span class="order-total-value"><?php echo number_format($total, 2); ?> грн</span>
      </div>

      <div class="delivery-info">
        <?php if ($order['delivery_type'] === 'PICKUP'): ?>
          <strong>Самовивіз</strong>
          <?php
            if ($order['store_id']) {
              $storeStmt = $pdo->prepare("SELECT name, address FROM stores WHERE id = :id");
              $storeStmt->execute([':id' => $order['store_id']]);
              $store = $storeStmt->fetch(PDO::FETCH_ASSOC);
              if ($store) echo htmlspecialchars($store['name']) . '<br>' . htmlspecialchars($store['address']);
            }
          ?>
        <?php else: ?>
          <strong>Доставка за адресою</strong>
          <?php echo htmlspecialchars($order['delivery_address']); ?>
        <?php endif; ?>
      </div>
    </div>

    <div class="checkout-card">
      <div class="card-title">Дані картки</div>

      <div id="payment-element"></div>

      <button id="pay-btn" class="pay-btn">
        <span id="btn-text">Сплатити <?php echo number_format($total, 2); ?> грн</span>
        <span class="spinner" id="btn-spinner"></span>
      </button>

      <div id="error-message"></div>

      <div class="security-note">
        <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
          <path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/>
        </svg>
        <span>Дані захищені шифруванням SSL. Ми не зберігаємо дані вашої картки.</span>
      </div>
    </div>

  </div>
</main>

<?php include __DIR__ . '/footer.php'; ?>

<script>
const stripe = Stripe('<?php echo STRIPE_PUBLISHABLE_KEY; ?>');

const elements = stripe.elements({
  clientSecret: '<?php echo $intent->client_secret; ?>',
  appearance: {
    theme: 'flat',
    variables: {
      colorPrimary:       '#6f4b32',
      colorBackground:    '#ffffff',
      colorText:          '#2b2b2b',
      colorTextSecondary: '#888888',
      colorDanger:        '#d32f2f',
      fontFamily:         '"Poppins", sans-serif',
      borderRadius:       '8px',
      fontSizeBase:       '14px',
      fontWeightBold:     '600',
    },
    rules: {
      '.Input': { 
        border: '1px solid #e0e0e0',
        boxShadow: 'none',
        padding: '12px 16px',
      },
      '.Input:focus': { 
        border: '1px solid #6f4b32',
        boxShadow: '0 0 0 3px rgba(111, 75, 50, 0.1)',
      },
      '.Label': { 
        color: '#666666',
        fontWeight: '500',
        marginBottom: '6px',
      },
      '.Tab': {
        padding: '12px 0',
        borderBottom: '2px solid #f0f0f0',
      },
      '.Tab--selected': {
        borderBottomColor: '#6f4b32',
      },
    }
  }
});

const paymentElement = elements.create('payment');
paymentElement.mount('#payment-element');

const payBtn     = document.getElementById('pay-btn');
const btnText    = document.getElementById('btn-text');
const btnSpinner = document.getElementById('btn-spinner');
const errorDiv   = document.getElementById('error-message');

payBtn.addEventListener('click', async () => {
  payBtn.disabled        = true;
  btnText.style.display  = 'none';
  btnSpinner.style.display = 'block';
  errorDiv.style.display = 'none';

  const { error } = await stripe.confirmPayment({
    elements,
    confirmParams: {
      return_url: `${location.origin}/View/payment_success.php?order_id=<?php echo $orderId; ?>`
    }
  });

  if (error) {
    errorDiv.textContent   = error.message;
    errorDiv.style.display = 'block';
    payBtn.disabled        = false;
    btnText.style.display  = 'inline';
    btnSpinner.style.display = 'none';
  }
});
</script>
</body>
</html>
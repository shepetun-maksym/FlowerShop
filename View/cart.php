<!doctype html>
<html lang="uk">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Кошик</title>
  <link rel="stylesheet" href="../assets/css/header.css">
  <link rel="stylesheet" href="../assets/css/menu.css">
  <link rel="stylesheet" href="../assets/css/footer.css">
  <link rel="stylesheet" href="../assets/css/profile.css">
  <link rel="stylesheet" href="../assets/css/cart.css">
</head>
<body>
<?php
require_once __DIR__ . '/../Controller/CartController.php';
require_once __DIR__ . '/../Model/Product.php';
$ctx = cartController();
if (session_status() !== PHP_SESSION_ACTIVE) session_start();
?>
<?php include __DIR__ . '/header.php'; ?>
<main class="container">
  <h1>Кошик</h1>
  
  <?php if (!empty($ctx['errors'])): ?>
    <div class="cart-error">
      <ul>
        <?php foreach ($ctx['errors'] as $e) echo '<li>'.htmlspecialchars($e).'</li>'; ?>
      </ul>
    </div>
  <?php endif; ?>
  
  <?php if (!empty($ctx['success'])): ?>
    <div class="cart-success"><?php echo htmlspecialchars($ctx['success']); ?></div>
  <?php endif; ?>

  <?php if ($ctx['user'] && $ctx['user']['role'] === 'CLIENT'): ?>
    <div class="cart-user-info">
      <h3>Інформація про клієнта</h3>
      <p><strong>Ім'я:</strong> <?php echo htmlspecialchars($ctx['user']['name']); ?></p>
      <p><strong>Прізвище:</strong> <?php echo htmlspecialchars($ctx['user']['lastname']); ?></p>
      <p><strong>Email:</strong> <?php echo htmlspecialchars($ctx['user']['email']); ?></p>
      <p><strong>Телефон:</strong> <?php echo htmlspecialchars($ctx['user']['phone']); ?></p>
    </div>
  <?php endif; ?>

  <?php if (empty($ctx['cart'])): ?>
    <div class="cart-empty">
      Кошик порожній, додайте товари щоб оформити замовлення.<br><br>
      <a href="menu.php">← Повернутися до товарів</a>
    </div>
  <?php else: ?>
    <div class="cart-header">
      <h2 style="margin:0;color:var(--brown);font-size:20px;">Товари в кошику (<?php echo count($ctx['cart']); ?>)</h2>
      <form method="post" style="margin:0;">
        <input type="hidden" name="action" value="clear">
        <button type="submit" class="btn btn-ghost">Очистити</button>
      </form>
    </div>

    <div class="cart-items-container">
      <?php $total = 0; foreach ($ctx['cart'] as $item): $sum = $item['price_current'] * $item['quantity']; $total += $sum; ?>
        <?php
          if (!empty($item['image'])) {
              $imgRaw = $item['image'];
              if (strpos($imgRaw, '://') !== false || strpos($imgRaw, '/') === 0) {
                  $img = $imgRaw;
              } elseif (strpos($imgRaw, 'Images/') === 0) {
                  $img = '../' . $imgRaw;
              } else {
                  $img = '../Images/' . $imgRaw;
              }
          } else {
              $img = '../Images/noflower.jpg';
          }
        ?>
        <div class="cart-item">
          <div class="cart-item-img-wrapper">
            <img class="cart-item-img" src="<?php echo htmlspecialchars($img); ?>" alt="<?php echo htmlspecialchars($item['name']); ?>">
          </div>

          <div class="cart-item-details">
            <h3 class="cart-item-name"><?php echo htmlspecialchars($item['name']); ?></h3>
            <span class="cart-item-category"><?php echo getCategoryLabel($item['category']); ?></span>
            <p class="cart-item-desc"><?php echo htmlspecialchars($item['description']); ?></p>
            
            <div class="cart-item-meta">
              <div class="cart-item-meta-item">
                <span class="cart-item-meta-label">Ціна за одиницю</span>
                <span class="cart-item-meta-value price"><?php echo htmlspecialchars($item['price_current']); ?> грн</span>
              </div>
              <div class="cart-item-meta-item">
                <span class="cart-item-meta-label">Знижка</span>
                <span class="cart-item-meta-value"><?php echo $item['discount'] ? $item['discount'].'%' : '—'; ?></span>
              </div>
              <div class="cart-item-meta-item">
                <span class="cart-item-meta-label">На складі</span>
                <span class="cart-item-meta-value <?php echo $item['available_stock'] > 0 ? 'stock-ok' : 'stock-low'; ?>">
                  <?php echo $item['available_stock']; ?> шт.
                  <?php if ($item['quantity'] > $item['available_stock']): ?>
                    <div style="color:#d32f2f;font-size:11px;margin-top:4px;font-weight:600;">Недостатньо</div>
                  <?php endif; ?>
                </span>
              </div>
            </div>
          </div>

          <div class="cart-item-actions">
            <form method="post" style="display:contents;">
              <input type="hidden" name="action" value="update">
              <input type="hidden" name="product_id" value="<?php echo $item['id']; ?>">
              <input type="number" name="quantity" class="cart-qty-input" value="<?php echo $item['quantity']; ?>" min="1" max="<?php echo $item['available_stock']; ?>" onchange="this.form.submit()">
            </form>
            
            <div class="cart-sum"><?php echo number_format($sum, 2); ?> грн</div>
            
            <form method="post" style="display:contents;">
              <input type="hidden" name="action" value="remove">
              <input type="hidden" name="product_id" value="<?php echo $item['id']; ?>">
              <button type="submit" class="btn btn-brown btn-remove">Видалити</button>
            </form>
          </div>
        </div>
      <?php endforeach; ?>
    </div>

    <div class="delivery-section" style="margin:32px 0;padding:24px;background:#f9f7f5;border-radius:12px;border:1px solid #e0d5cb;">
      <h3 style="margin:0 0 18px 0;color:var(--brown);">Спосіб доставки</h3>
      
      <form id="delivery-form">
        <input type="hidden" name="action" value="set_delivery">
        
        <div style="margin-bottom:18px;">
          <label style="display:flex;align-items:center;cursor:pointer;margin-bottom:12px;">
            <input type="radio" name="delivery_type" value="PICKUP" <?php echo ($ctx['delivery']['type'] === 'PICKUP' ? 'checked' : ''); ?> onchange="setDeliveryType('PICKUP')" style="margin-right:12px;cursor:pointer;">
            <span style="font-size:16px;color:var(--brown);">Самовивіз з магазину</span>
          </label>
          
          <div id="pickup-section" style="display:<?php echo ($ctx['delivery']['type'] === 'PICKUP' ? 'block' : 'none'); ?>;margin-left:28px;padding:16px;background:#fff;border-radius:8px;border:1px solid #ddd;">
            <label for="store_id" style="display:block;margin-bottom:8px;font-weight:500;color:var(--brown);">Виберіть магазин:</label>
            <select name="store_id" id="store_id" onchange="savePickupStore()" style="width:100%;padding:10px;border:1px solid #ddd;border-radius:6px;font-size:14px;">
              <option value="">-- Виберіть магазин --</option>
              <?php foreach ($ctx['stores'] as $store): ?>
                <option value="<?php echo $store['id']; ?>" <?php echo ($ctx['delivery']['store_id'] == $store['id'] ? 'selected' : ''); ?>>
                  <?php echo htmlspecialchars($store['name']); ?> (<?php echo htmlspecialchars($store['address']); ?>)
                </option>
              <?php endforeach; ?>
            </select>
          </div>
        </div>
        
        <div style="margin-bottom:12px;">
          <label style="display:flex;align-items:center;cursor:pointer;margin-bottom:12px;">
            <input type="radio" name="delivery_type" value="DELIVERY" <?php echo ($ctx['delivery']['type'] === 'DELIVERY' ? 'checked' : ''); ?> onchange="setDeliveryType('DELIVERY')" style="margin-right:12px;cursor:pointer;">
            <span style="font-size:16px;color:var(--brown);">Доставка за адресою</span>
          </label>
          
          <div id="delivery-section-form" style="display:<?php echo ($ctx['delivery']['type'] === 'DELIVERY' ? 'block' : 'none'); ?>;margin-left:28px;padding:16px;background:#fff;border-radius:8px;border:1px solid #ddd;">
            <label for="delivery_address" style="display:block;margin-bottom:8px;font-weight:500;color:var(--brown);">Введіть адресу доставки:</label>
            <input type="text" name="delivery_address" id="delivery_address" value="<?php echo htmlspecialchars($ctx['delivery']['address']); ?>" placeholder="Наприклад: вул. Шевченка, 12" onchange="saveDeliveryAddress()" style="width:100%;padding:10px;border:1px solid #ddd;border-radius:6px;font-size:14px;box-sizing:border-box;">
          </div>
        </div>
      </form>
    </div>
    
    <script>
      function setDeliveryType(type) {
        const pickupSection = document.getElementById('pickup-section');
        const deliverySection = document.getElementById('delivery-section-form');
        
        if (type === 'PICKUP') {
          pickupSection.style.display = 'block';
          deliverySection.style.display = 'none';
          saveDelivery('PICKUP', '', '');
        } else {
          pickupSection.style.display = 'none';
          deliverySection.style.display = 'block';
          saveDelivery('DELIVERY', '', '');
        }
      }
      
      function savePickupStore() {
        const storeId = document.getElementById('store_id').value;
        if (storeId) {
          saveDelivery('PICKUP', storeId, '');
        }
      }
      
      function saveDeliveryAddress() {
        const address = document.getElementById('delivery_address').value;
        if (address.trim()) {
          saveDelivery('DELIVERY', '', address);
        }
      }
      
      function saveDelivery(type, storeId, address) {
        const formData = new FormData();
        formData.append('action', 'set_delivery');
        formData.append('delivery_type', type);
        if (storeId) {
          formData.append('store_id', storeId);
        }
        if (address) {
          formData.append('delivery_address', address);
        }
        
        fetch(window.location.href, {
          method: 'POST',
          body: formData
        })
        .then(response => response.text())
        .catch(error => console.error('Помилка:', error));
      }
    </script>

    <div class="cart-summary">
      <div class="cart-summary-label">Загальна вартість замовлення</div>
      <div class="cart-summary-total"><?php echo number_format($total, 2); ?> грн</div>
    </div>

    <?php if (!empty($ctx['user']) && $ctx['user']['role'] === 'CLIENT'): ?>
      <form method="post" class="cart-actions">
        <input type="hidden" name="action" value="order">
        <div style="margin-bottom:20px;padding:16px;background:#f0f4f8;border-radius:8px;border:1px solid #e0e0e0;">
          <label style="display:block;margin-bottom:12px;font-weight:600;color:#333;">Спосіб оплати:</label>
          <div style="display:flex;gap:16px;flex-wrap:wrap;">
            <label style="display:flex;align-items:center;gap:8px;cursor:pointer;">
              <input type="radio" name="payment_method" value="online" checked style="cursor:pointer;width:18px;height:18px;">
              <span>Оплатити онлайн</span>
            </label>
            <label style="display:flex;align-items:center;gap:8px;cursor:pointer;">
              <input type="radio" name="payment_method" value="cash" style="cursor:pointer;width:18px;height:18px;">
              <span>Оплатити при отриманні</span>
            </label>
          </div>
        </div>
        <button type="submit" class="btn btn-primary btn-large">Оформити замовлення</button>
      </form>
    <?php elseif (!empty($ctx['user']) && $ctx['user']['role'] === 'MANAGER'): ?>
      <form method="post" class="cart-actions">
        <input type="hidden" name="action" value="order">
        <div class="cart-actions-row">
          <label class="cart-actions-label" for="client_id">Оберіть клієнта для замовлення:</label>
          <select name="client_id" id="client_id" class="cart-actions-select" required>
            <option value="">-- Оберіть клієнта --</option>
            <?php foreach (($ctx['clients'] ?? []) as $cl): ?>
              <option value="<?php echo $cl['id']; ?>">
                <?php echo htmlspecialchars($cl['lastname'] . ' ' . $cl['name'] . ' (' . $cl['email'] . ')'); ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>
        <div style="margin-bottom:20px;padding:16px;background:#f0f4f8;border-radius:8px;border:1px solid #e0e0e0;">
          <label style="display:block;margin-bottom:12px;font-weight:600;color:#333;">Спосіб оплати:</label>
          <div style="display:flex;gap:16px;flex-wrap:wrap;">
            <label style="display:flex;align-items:center;gap:8px;cursor:pointer;">
              <input type="radio" name="payment_method" value="online" checked style="cursor:pointer;width:18px;height:18px;">
              <span>Оплатити онлайн</span>
            </label>
            <label style="display:flex;align-items:center;gap:8px;cursor:pointer;">
              <input type="radio" name="payment_method" value="cash" style="cursor:pointer;width:18px;height:18px;">
              <span>Оплатити при отриманні</span>
            </label>
          </div>
        </div>
        <button type="submit" class="btn btn-primary btn-large">Оформити замовлення для клієнта</button>
      </form>
    <?php else: ?>
      <div style="text-align:center;margin-top:32px;padding:40px;background:linear-gradient(135deg, rgba(246,239,230,0.8), rgba(255,255,255,0.6));border-radius:16px;border:2px solid rgba(46,139,87,0.1);">
        <p style="color:var(--brown);font-size:16px;margin:0;">Для оформлення замовлення <a href="login.php" style="color:var(--green);text-decoration:none;font-weight:700;">увійдіть</a> як клієнт або менеджер.</p>
      </div>
    <?php endif; ?>
  <?php endif; ?>
</main>
<?php include __DIR__ . '/footer.php'; ?>
</body>
</html>

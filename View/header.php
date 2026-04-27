<?php
if (session_status() !== PHP_SESSION_ACTIVE) session_start();
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../Model/User.php';
$user = null;
$inView = strpos($_SERVER['SCRIPT_NAME'], '/View/') !== false;
$assetPrefix = $inView ? '../' : '';
$rootPrefix = $inView ? '../' : '';
try {
  $pdo = getPDO();
  if (!empty($_SESSION['user_id'])) {
    $user = findUserById($pdo, (int)$_SESSION['user_id']);
  }
} catch (Exception $e) {
}
?>

<header class="site-header">
  <div class="container top-row">
    <div class="phone-info">
      <a href="about.php" class="btn-about">Про нас</a>
      <a href="#" class="btn-contact" id="contact-popup-btn">Контакти</a>
    </div>

    <div class="brand-wrap">
      <a href="menu.php" class="brand-name">Amoria Flowers</a>
      <div class="brand-sub">Флористична продукція</div>
    </div>

    <div class="header-actions">
      <?php if ($user): ?>
        <div class="account-wrap">
          <button class="btn-account account-toggle"><?php echo htmlspecialchars($user['name'] . ' ' . $user['lastname']); ?></button>
          <div class="account-menu" aria-hidden="true">
            <a href="<?php echo $inView ? 'profile.php' : 'View/profile.php'; ?>">Мій кабінет</a>
            <?php if ($user['role'] === 'ADMIN'): ?>
              <a href="<?php echo $inView ? 'admin.php' : 'View/admin.php'; ?>">Адмін панель</a>
            <?php endif; ?>
            <?php if ($user['role'] === 'MANAGER'): ?>
              <a href="<?php echo $inView ? 'manager.php' : 'View/manager.php'; ?>">Менеджер панель</a>
            <?php endif; ?>
            <?php if ($user['role'] === 'SUPPLIER'): ?>
              <a href="<?php echo $inView ? 'supplier.php' : 'View/supplier.php'; ?>">Панель постачальника</a>
              <a href="<?php echo $inView ? 'supplier_orders.php' : 'View/supplier_orders.php'; ?>">Замовлення від адміна</a>
            <?php endif; ?>
            <a href="<?php echo $inView ? '../logout.php' : 'logout.php'; ?>">Вийти</a>
          </div>
        </div>
      <?php else: ?>
        <a href="<?php echo $inView ? 'login.php' : 'View/login.php'; ?>" class="btn-account">Особистий кабінет</a>
      <?php endif; ?>
      <a href="cart.php" class="btn-cart">Кошик <span class="cart-count">
        <?php
        $cartCount = 0;
        if (isset($_SESSION['cart']) && is_array($_SESSION['cart'])) {
          foreach ($_SESSION['cart'] as $qty) $cartCount += (int)$qty;
        }
        echo $cartCount;
        ?>
      </span></a>
    </div>
  </div>

  <div class="nav-row">
    <nav class="container nav-bar">
        <a href="#" data-filter="all">ВСІ КВІТИ</a>
      <a href="#" data-filter="букети">БУКЕТИ</a>
      <a href="#" data-filter="троянди">ТРОЯНДИ</a>
      <a href="#" data-filter="орхідеї">ОРХІДЕЇ</a>
      <a href="#" data-filter="коробці">КВІТИ В КОРОБЦІ</a>
      <a href="#" data-filter="лілії">ЛІЛІЇ</a>
      <a href="#" data-filter="тюльпани">ТЮЛЬПАНИ</a>
      <a href="#" data-filter="promo" class="promo">АКЦІЙНІ</a>
    </nav>
  </div>
</header>

<div id="contact-popup" style="display:none;position:fixed;top:80px;left:50%;transform:translateX(-50%) scale(0.8);background:#fff;border-radius:16px;box-shadow:0 12px 48px rgba(0,0,0,0.15);z-index:99999;min-width:320px;max-width:95vw;padding:0 0 18px 0;opacity:0;transition:all .35s cubic-bezier(.34,1.56,.64,1);">
  <div style="background:#ffb300;border-radius:16px 16px 0 0;padding:16px 0 10px 0;text-align:center;font-weight:600;font-size:1.1em;color:#fff;letter-spacing:0.5px;">Онлайн підтримка</div>
  <div style="padding:18px 24px 0 24px;">
    <div style="display:flex;gap:12px;justify-content:center;margin-bottom:16px;">
      <a href="https://t.me/insanerain" title="Чат" style="background:#e0f7fa;border-radius:50%;padding:8px;transition:all .2s;"><img src="https://cdn.jsdelivr.net/gh/simple-icons/simple-icons/icons/telegram.svg" alt="Telegram" style="width:28px;height:28px;"></a>
      <a href="#" title="Viber" style="background:#e0f7fa;border-radius:50%;padding:8px;transition:all .2s;"><img src="https://cdn.jsdelivr.net/gh/simple-icons/simple-icons/icons/viber.svg" alt="Viber" style="width:28px;height:28px;"></a>
    </div>
    <div style="margin-bottom:8px;font-weight:500;">Телефонуйте нам (безкоштовно):</div>
    <div style="font-size:1.2em;color:#388e3c;font-weight:600;margin-bottom:4px;">0 800 ХХХ ХХХ</div>
    <div style="color:#888;font-size:13px;margin-bottom:10px;">Цілодобово. Без вихідних</div>
    <hr style="border:none;border-top:1px solid #eee;margin:10px 0 12px 0;">
    <div style="font-weight:500;margin-bottom:6px;">Для мобільних дзвінків:</div>
    <div style="font-size:15px;line-height:1.7;">
      <span style="color:#388e3c;font-weight:600;">Київстар</span> <a href="tel:0673557755" style="color:#388e3c;text-decoration:none;">(067) ХХХ ХХ ХХ</a><br>
      <span style="color:#388e3c;font-weight:600;">Vodafone</span> <a href="tel:0993557755" style="color:#388e3c;text-decoration:none;">(099) ХХХ ХХ ХХ</a><br>
      <span style="color:#388e3c;font-weight:600;">lifecell</span> <a href="tel:0735655668" style="color:#388e3c;text-decoration:none;">(073) ХХХ ХХ ХХ</a>
    </div>
  </div>
  <button id="contact-popup-close" style="margin:18px auto 0 auto;display:block;background:#eee;border:none;border-radius:8px;padding:7px 24px;font-size:1em;cursor:pointer;transition:background .2s;">Закрити</button>
</div>
<style>
@keyframes popupEnter {
  0% {
    opacity: 0;
    transform: translateX(-50%) scale(0.7) translateY(-20px);
  }
  100% {
    opacity: 1;
    transform: translateX(-50%) scale(1) translateY(0);
  }
}

@keyframes popupExit {
  0% {
    opacity: 1;
    transform: translateX(-50%) scale(1) translateY(0);
  }
  100% {
    opacity: 0;
    transform: translateX(-50%) scale(0.7) translateY(-20px);
  }
}

#contact-popup.show {
  display: block !important;
  animation: popupEnter 0.35s cubic-bezier(0.34, 1.56, 0.64, 1) forwards;
  pointer-events: auto;
}

#contact-popup.closing {
  animation: popupExit 0.25s ease-out forwards;
}

#contact-popup {
  pointer-events: none;
}

#contact-popup.show a:hover {
  transform: scale(1.1);
  background-color: #b3e5fc !important;
}

#contact-popup.show #contact-popup-close:hover {
  background-color: #ddd;
}
</style>
<script>
document.addEventListener('DOMContentLoaded', function() {
  var btn = document.getElementById('contact-popup-btn');
  var popup = document.getElementById('contact-popup');
  var close = document.getElementById('contact-popup-close');
  if (btn && popup && close) {
    btn.addEventListener('click', function(e) {
      e.preventDefault();
      popup.classList.remove('closing');
      popup.classList.add('show');
    });
    close.addEventListener('click', function() {
      popup.classList.add('closing');
      popup.classList.remove('show');
      setTimeout(function() {
        popup.classList.remove('closing');
      }, 250);
    });
    document.addEventListener('mousedown', function(e) {
      if (popup.classList.contains('show') && !popup.contains(e.target) && e.target !== btn) {
        popup.classList.add('closing');
        popup.classList.remove('show');
        setTimeout(function() {
          popup.classList.remove('closing');
        }, 250);
      }
    });
  }
});
</script>
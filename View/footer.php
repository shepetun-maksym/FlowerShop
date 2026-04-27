<?php
if (session_status() !== PHP_SESSION_ACTIVE) session_start();
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../Model/User.php';
$footerUser = null;
$inView = strpos($_SERVER['SCRIPT_NAME'], '/View/') !== false;
$assetPrefix = $inView ? '../' : '';
$rootPrefix = $inView ? '../' : '';
try {
  $pdo = getPDO();
  if (!empty($_SESSION['user_id'])) $footerUser = findUserById($pdo, (int)$_SESSION['user_id']);
} catch (Exception $e) {}
?>
<footer class="site-footer">
  <div class="container footer-inner">
    <div class="footer-col">
      <h4>Про нас</h4>
      <p>Флористичний сервіс. Швидке замовлення.</p>
    </div>
    <div class="footer-col">
      <h4>Контакти</h4>
      <p>Тел: 067-XXX-XXXX</p>
      <p>Пошта: AmoriaFlowers@example.com</p>
    </div>
    <div class="footer-col">
      <h4>Соцмережі</h4>
      <p>Instagram · Facebook · Telegram</p>
    </div>
  </div>
  <div class="footer-bottom">© 2026 Amoria Flowers — Флористична продукція</div>
</footer>
<script src="<?php echo $assetPrefix; ?>assets/js/site.js"></script>

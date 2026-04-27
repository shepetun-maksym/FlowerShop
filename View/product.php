<?php
include __DIR__ . '/../Controller/ProductController.php';
$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$product = $id ? loadProductById($id) : null;
?>
<!doctype html>
<html lang="uk">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title><?php echo $product ? htmlspecialchars($product['name']) : 'Товар не знайдено'; ?></title>
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="../assets/css/header.css">
  <link rel="stylesheet" href="../assets/css/menu.css">
  <link rel="stylesheet" href="../assets/css/footer.css">
</head>
<body>
<?php include __DIR__ . '/header.php'; ?>

<main class="container product-detail">
  <?php if (!$product): ?>
    <p>Товар не знайдено. <a href="menu.php">Повернутись до меню</a></p>
  <?php else: ?>
      <?php $catLabels = ['SEED'=>'Саджанець','YOUNG'=>'Молода','BLOOMING'=>'Квітучий'];
            $catLabel = $product['category'] ? ($catLabels[$product['category']] ?? $product['category']) : '—'; ?>
      <div class="product-detail-row">
        <div class="product-detail-left">
          <?php
            if (!empty($product['image'])) {
                $imgRaw = $product['image'];
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
          <div class="product-placeholder product-placeholder-large"><img class="product-img" src="<?php echo htmlspecialchars($img); ?>" alt="<?php echo htmlspecialchars($product['name']); ?>" /></div>
        </div>
        <div class="product-detail-right">
          <div class="product-title-row">
            <h1 class="product-name"><?php echo htmlspecialchars($product['name']); ?></h1>
            <div class="category-badge product-category-badge"><?php echo htmlspecialchars($catLabel); ?></div>
          </div>
          <div class="product-description"><?php echo nl2br(htmlspecialchars($product['description'])); ?></div>
          <?php if (!empty($product['long_description'])): ?>
            <div class="product-long-description" style="margin: 20px 0; line-height: 1.6;">
              <?php echo nl2br(htmlspecialchars($product['long_description'])); ?>
            </div>
          <?php endif; ?>
          <div class="product-pricing">
            <div class="product-price-current"><?php echo number_format($product['price_current'],0,',',' '); ?>₴</div>
            <?php if ($product['price_old']): ?>
              <div class="product-price-old"><?php echo number_format($product['price_old'],0,',',' '); ?>₴</div>
            <?php endif; ?>
            <div class="stock-status" style="margin-top: 12px; font-size: 1em; color: <?php echo $product['stock'] > 0 ? '#388e3c' : '#d32f2f'; ?>; font-weight: 500;">
              <?php echo $product['stock'] > 0 ? 'є в наявності' : 'немає в наявності'; ?>
            </div>
          </div>
          <div class="product-actions">
            <?php if ($product['stock'] > 0): ?>
              <button class="btn btn-primary btn-add" data-id="<?php echo $product['id']; ?>">Додати у кошик</button>
            <?php endif; ?>
            <a class="btn btn-ghost" href="menu.php">Повернутись</a>
          </div>
        </div>
      </div>
  <?php endif; ?>
</main>

<?php include __DIR__ . '/footer.php'; ?>

<div id="cart-toast" style="position:fixed;top:80px;right:20px;background:#388e3c;color:#fff;padding:16px 20px;border-radius:8px;display:none;opacity:0;transition:opacity .3s ease;z-index:10000;box-shadow:0 8px 20px rgba(0,0,0,0.15);max-width:400px;">
	<span id="cart-toast-msg"></span>
</div>

<script>
// AJAX додавання в кошик
document.addEventListener('DOMContentLoaded', function() {
	document.querySelectorAll('.btn-add').forEach(function(btn) {
		btn.addEventListener('click', function(e) {
			e.preventDefault();
			var pid = btn.getAttribute('data-id');
			var pname = '<?php echo addslashes($product['name'] ?? 'Товар'); ?>';
			fetch('cart.php', {
				method: 'POST',
				headers: {'Content-Type': 'application/x-www-form-urlencoded'},
				body: 'action=add&product_id=' + encodeURIComponent(pid) + '&quantity=1'
			})
			.then(r => r.text())
			.then(function() {
				showCartToast('"' + pname + '" додано до кошика!');
				updateCartCount();
			});
		});
	});
});

function updateCartCount() {
	var cartCountEl = document.querySelector('.cart-count');
	if (cartCountEl) {
		var currentCount = parseInt(cartCountEl.textContent, 10) || 0;
		cartCountEl.textContent = currentCount + 1;
	}
}

function showCartToast(msg) {
	var toast = document.getElementById('cart-toast');
	var msgEl = document.getElementById('cart-toast-msg');
	msgEl.textContent = msg;
	toast.style.display = 'block';
	setTimeout(function() {
		toast.style.opacity = 1;
	}, 10);
	setTimeout(function() {
		toast.style.opacity = 0;
		setTimeout(function() { toast.style.display = 'none'; }, 300);
	}, 3000);
}
</script>

</body>
</html>
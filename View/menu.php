<!doctype html>
<html lang="uk">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Меню - Квіти</title>
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="../assets/css/header.css">
  <link rel="stylesheet" href="../assets/css/menu.css">
  <link rel="stylesheet" href="../assets/css/footer.css">
</head>
<body>

<?php include __DIR__ . '/header.php'; ?>

<?php
include __DIR__ . '/../Controller/ProductController.php';
$products = loadProductList(32);
?>

<main class="container">
	<div id="cart-toast" style="position:fixed;top:80px;right:20px;background:#388e3c;color:#fff;padding:16px 20px;border-radius:8px;display:none;opacity:0;transition:opacity .3s ease;z-index:10000;box-shadow:0 8px 20px rgba(0,0,0,0.15);max-width:400px;">
		<span id="cart-toast-msg"></span>
	</div>
		<div class="catalog-top">
		<div class="catalog-controls">
			<div class="sort-area">
					<label for="sort">Сортувати:</label>
					<select id="sort" class="sort-select">
						<option value="popularity">За популярністю</option>
						<option value="price_asc">За ціною: від низької</option>
						<option value="price_desc">За ціною: від високої</option>
					</select>
			</div>
			<div class="search-wrap-menu">
				<input type="search" class="search-input-menu" placeholder="Пошук по товарам..." aria-label="Пошук">
			</div>
		</div>

		<section class="product-grid" aria-label="Продукти">
			<?php
			$catLabels = ['SEED'=>'Саджанець','YOUNG'=>'Молода','BLOOMING'=>'Квітучий'];
			$filterKeywords = [
				'букети' => 'букет букети',
				'троянди' => 'троянда троянди роза',
				'орхідеї' => 'орхідея орхідеї орхід',
				'лілії' => 'лілія лілії',
				'тюльпани' => 'тюльпан тюльпани',
				'коробці' => 'коробка коробці',
				'подарунки' => 'подарунок подарунки подарунок'
			];
			
			foreach ($products as $i => $p):
				$name = htmlspecialchars($p['name'] ?? 'Без назви');
				$price = number_format((float)($p['price_current'] ?? 0), 0, ',', ' ');
				$old = isset($p['price_old']) ? number_format((float)$p['price_old'], 0, ',', ' ') : null;
				$discount = isset($p['discount']) && $p['discount'] ? (int)$p['discount'] : null;
				$discountName = htmlspecialchars($p['discount_name'] ?? '');
				$categoryRaw = $p['category'] ?? '';
				$categoryLabel = $categoryRaw ? ($catLabels[$categoryRaw] ?? $categoryRaw) : '';
				$category = htmlspecialchars($categoryLabel);
				$description_full = htmlspecialchars($p['description'] ?? '');
			$stock = isset($p['stock']) ? (int)$p['stock'] : 0;
			$stockStatus = $stock > 0 ? 'є в наявності' : 'немає в наявності';

				$desc_preview = strlen($description_full) > 120 ? mb_substr($description_full,0,120) . '…' : $description_full;
				
				$nameLower = strtolower($name);
				$descLower = strtolower($description_full);
				$keywords = $nameLower;
				
				foreach ($filterKeywords as $filter => $filterTerms) {
					$terms = explode(' ', $filterTerms);
					foreach ($terms as $term) {
						if (strpos($nameLower, $term) !== false || strpos($descLower, $term) !== false) {
							$keywords .= ' ' . $filter;
							break;
						}
					}
				}
			?>
			<?php
				if (!empty($p['image'])) {
					$imgRaw = $p['image'];
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
			<article class="card" data-id="<?php echo $p['id']; ?>"
				data-name="<?php echo $name; ?>"
				data-category="<?php echo $category; ?>"
				data-category-raw="<?php echo $categoryRaw; ?>"
				data-keywords="<?php echo htmlspecialchars($keywords); ?>"
				data-price="<?php echo $price; ?>"
				data-old="<?php echo $old ?? ''; ?>"
				data-discount="<?php echo $discount ?? ''; ?>"			data-stock="<?php echo $stock; ?>"				data-description="<?php echo $description_full; ?>">
				<?php if ($discount): ?>
					<div class="discount-badge">-<?php echo $discount; ?>%</div>
				<?php endif; ?>
				<div class="product-placeholder">
					<img class="product-img" src="<?php echo htmlspecialchars($img); ?>" alt="<?php echo $name; ?>" />
				</div>
				<div class="category-badge"><?php echo $category ? $category : '—'; ?></div>
				<div class="product-title"><?php echo $name; ?></div>
				<div class="product-desc"><?php echo $desc_preview; ?></div>
			<div class="stock-status" style="font-size: 0.9em; color: <?php echo $stock > 0 ? '#388e3c' : '#d32f2f'; ?>; margin: 8px 0;">
				<?php echo $stockStatus; ?>
			</div>
			<?php if ($discountName): ?>
				<div style="font-size: 0.85em; color: #f57c00; margin: 6px 0; font-weight: 500;">
					<?php echo $discountName; ?>
				</div>
			<?php endif; ?>
				<div class="price-row">
					<div class="price-current"><?php echo $price; ?>₴</div>
					<?php if ($old): ?><div class="price-old"><?php echo $old; ?>₴</div><?php endif; ?>
				</div>
				   <div class="card-actions">
				   <?php if ($stock > 0): ?>
					   <button class="btn btn-primary btn-add" data-id="<?php echo $p['id']; ?>" data-name="<?php echo $name; ?>">У кошик</button>
				   <?php endif; ?>
					   <a class="btn btn-ghost" href="product.php?id=<?php echo $p['id']; ?>">Переглянути</a>
				   </div>
			</article>
			<?php endforeach; ?>

		</section>
			 </main>
<script>
// AJAX додавання в кошик
document.addEventListener('DOMContentLoaded', function() {
	document.querySelectorAll('.btn-add').forEach(function(btn) {
		btn.addEventListener('click', function(e) {
			e.preventDefault();
			var pid = btn.getAttribute('data-id');
			var pname = btn.getAttribute('data-name');
			fetch('cart.php', {
				method: 'POST',
				headers: {'Content-Type': 'application/x-www-form-urlencoded'},
				body: 'action=add&product_id=' + encodeURIComponent(pid) + '&quantity=1'
			})
			.then(r => r.text())
			.then(function() {
				showCartToast('"' + pname + '" додано до кошика!');
				// Оновимо лічильник в хедері
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
	<?php include __DIR__ . '/footer.php'; ?>
	</body>
</html>
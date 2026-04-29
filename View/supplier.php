<!doctype html>
<html lang="uk">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Панель постачальника</title>
  <link rel="stylesheet" href="../assets/css/header.css">
  <link rel="stylesheet" href="../assets/css/menu.css">
  <link rel="stylesheet" href="../assets/css/footer.css">
  <link rel="stylesheet" href="../assets/css/profile.css">
  <link rel="stylesheet" href="../assets/css/admin.css">
</head>
<body>
<?php
require_once __DIR__ . '/../Controller/SupplierController.php';
require_once __DIR__ . '/../Model/Product.php';
$ctx = supplierController();
if (session_status() !== PHP_SESSION_ACTIVE) session_start();
?>
<?php include __DIR__ . '/header.php'; ?>
<main class="container" style="padding:40px 18px">
  <h1>Панель постачальника (Теплиця)</h1>
  
  <?php if (!empty($ctx['greenhouses']) && count($ctx['greenhouses']) > 1): ?>
    <div style="margin-bottom: 20px; padding: 15px; background: #f0f8f0; border-radius: 8px;">
      <label for="greenhouse-selector" style="font-weight: bold; margin-right: 10px;">Виберіть теплицю:</label>
      <select id="greenhouse-selector" style="padding: 8px; border: 1px solid #ccc; border-radius: 4px; font-size: 14px;" onchange="window.location.href = '?greenhouse_id=' + this.value">
        <?php foreach ($ctx['greenhouses'] as $gh): ?>
          <option value="<?php echo $gh['id']; ?>" <?php echo ($ctx['greenhouse']['id'] == $gh['id']) ? 'selected' : ''; ?>>
            <?php echo htmlspecialchars($gh['name']); ?>
          </option>
        <?php endforeach; ?>
      </select>
    </div>
  <?php endif; ?>
  
  <?php if (!empty($ctx['errors'])): ?>
    <div class="errors"><ul><?php foreach ($ctx['errors'] as $e) echo '<li>'.htmlspecialchars($e).'</li>'; ?></ul></div>
  <?php endif; ?>
  <?php if (!empty($ctx['success'])): ?>
    <div class="success"><?php echo htmlspecialchars($ctx['success']); ?></div>
  <?php endif; ?>

  <section class="admin-section">
    <h2>Додати новий товар у теплицю</h2>
    <form method="post">
      <input type="hidden" name="action" value="add_product">
      <div class="form-group">
        <label>Категорія
          <select name="category">
            <option value="SEED">Саджанець</option>
            <option value="YOUNG">Молода</option>
            <option value="BLOOMING">Квітучий</option>
          </select>
        </label>
      </div>
      <div class="form-group">
        <label>Назва квітки <input name="flower_name" required></label>
      </div>
      <div class="form-group">
        <label>Опис <textarea name="description"></textarea></label>
      </div>
      <div class="form-group">
        <label>Розширений опис <textarea name="long_description" style="min-height:120px;"></textarea></label>
      </div>
      <div class="form-group">
        <label>Ціна <input type="number" step="0.01" name="price" required></label>
      </div>
      <div class="form-group">
        <label>Кількість у теплиці <input type="number" name="quantity" value="0" min="0" required></label>
      </div>
      <div class="form-group">
        <label>Зображення (виберіть з папки Images)
          <select name="image">
            <option value="">-- Без зображення --</option>
            <?php foreach (($ctx['images'] ?? []) as $img): ?>
              <option value="<?php echo htmlspecialchars($img); ?>"><?php echo htmlspecialchars($img); ?></option>
            <?php endforeach; ?>
          </select>
        </label>
      </div>
      <button type="submit" class="btn btn-primary">Додати</button>
    </form>

    <h2 style="margin-top: 40px;">Товари у теплиці</h2>
    <?php if (empty($ctx['inventory'])): ?>
      <p>Теплиця порожня. Додайте товари вище.</p>
    <?php else: ?>
      <table class="admin-table">
        <thead>
          <tr>
            <th>ID</th>
            <th>Фото</th>
            <th>Категорія</th>
            <th>Назва</th>
            <th>Опис</th>
            <th>Ціна</th>
            <th>Кількість</th>
            <th>Дії</th>
          </tr>
        </thead>
        <tbody>
          <?php 
            // Сортування: товари з наявністю спочатку, потім з нульовою кількістю
            usort($ctx['inventory'], function($a, $b) {
                if ($a['quantity'] > 0 && $b['quantity'] == 0) return -1;
                if ($a['quantity'] == 0 && $b['quantity'] > 0) return 1;
                return 0;
            });
            foreach ($ctx['inventory'] as $item): 
          ?>
            <?php
              if (!empty($item['image'])) {
                  $imgRaw = $item['image'];
                  if (strpos($imgRaw, '://') !== false || strpos($imgRaw, '/') === 0) {
                      $imgPath = $imgRaw;
                  } elseif (strpos($imgRaw, 'Images/') === 0) {
                      $imgPath = '../' . $imgRaw;
                  } else {
                      $imgPath = '../Images/' . $imgRaw;
                  }
              } else {
                  $imgPath = '../Images/noflower.jpg';
              }
            ?>
            <tr>
              <td><?php echo htmlspecialchars($item['product_id']); ?></td>
              <td><img src="<?php echo htmlspecialchars($imgPath); ?>" alt="" class="admin-thumb"></td>
              <td><?php echo getCategoryLabel($item['category']); ?></td>
              <td><?php echo htmlspecialchars($item['flower_name']); ?></td>
              <td><?php echo htmlspecialchars($item['description']); ?></td>
              <td><?php echo number_format((float)$item['price'], 2); ?> грн</td>
              <td style="<?php echo $item['quantity'] == 0 ? 'color: red; font-weight: bold;' : ''; ?>"><?php echo htmlspecialchars($item['quantity']); ?> шт.</td>
              <td class="product-actions">
                <label for="edit-<?php echo $item['inventory_id']; ?>" class="btn btn-primary">Редагувати</label>
                <form method="post" style="display:inline;">
                  <input type="hidden" name="action" value="delete_product">
                  <input type="hidden" name="inventory_id" value="<?php echo $item['inventory_id']; ?>">
                  <input type="hidden" name="product_id" value="<?php echo $item['product_id']; ?>">
                  <button type="submit" class="btn btn-danger" onclick="return confirm('Видалити товар з теплиці?')">Видалити</button>
                </form>
              </td>
            </tr>

            <tr class="product-edit-row">
              <td colspan="8">
                <input type="checkbox" id="edit-<?php echo $item['inventory_id']; ?>" class="edit-toggle">
                <div class="edit-panel">
                  <h4>Редагувати товар #<?php echo htmlspecialchars($item['product_id']); ?></h4>
                  <form method="post">
                    <input type="hidden" name="action" value="update_product">
                    <input type="hidden" name="inventory_id" value="<?php echo $item['inventory_id']; ?>">
                    <input type="hidden" name="product_id" value="<?php echo $item['product_id']; ?>">
                    <div class="form-group">
                      <label>Категорія
                        <select name="category">
                          <option value="SEED" <?php if ($item['category']==='SEED') echo 'selected'; ?>>Саджанець</option>
                          <option value="YOUNG" <?php if ($item['category']==='YOUNG') echo 'selected'; ?>>Молода</option>
                          <option value="BLOOMING" <?php if ($item['category']==='BLOOMING') echo 'selected'; ?>>Квітучий</option>
                        </select>
                      </label>
                    </div>
                    <div class="form-group">
                      <label>Назва квітки <input name="flower_name" value="<?php echo htmlspecialchars($item['flower_name']); ?>" required></label>
                    </div>
                    <div class="form-group">
                      <label>Опис <textarea name="description"><?php echo htmlspecialchars($item['description']); ?></textarea></label>
                    </div>
                    <div class="form-group">
                      <label>Розширений опис <textarea name="long_description" style="min-height:120px;"><?php echo htmlspecialchars($item['long_description'] ?? ''); ?></textarea></label>
                    </div>
                    <div class="form-group">
                      <label>Ціна <input type="number" step="0.01" name="price" value="<?php echo htmlspecialchars($item['price']); ?>" required></label>
                    </div>
                    <div class="form-group">
                      <label>Кількість у теплиці <input type="number" name="quantity" value="<?php echo htmlspecialchars($item['quantity']); ?>" min="0" required></label>
                    </div>
                    <div class="form-group">
                      <label>Зображення (виберіть з папки Images)
                        <select name="image">
                          <option value="">-- Без зображення --</option>
                          <?php foreach (($ctx['images'] ?? []) as $img): ?>
                            <option value="<?php echo htmlspecialchars($img); ?>" <?php if ($item['image'] === $img) echo 'selected'; ?>><?php echo htmlspecialchars($img); ?></option>
                          <?php endforeach; ?>
                        </select>
                      </label>
                    </div>
                    <button type="submit" class="btn btn-primary">Зберегти</button>
                    <label for="edit-<?php echo $item['inventory_id']; ?>" class="btn btn-ghost">Скасувати</label>
                  </form>
                </div>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    <?php endif; ?>
  </section>
</main>

<?php include __DIR__ . '/footer.php'; ?>

<script>

document.querySelectorAll('.edit-toggle').forEach(function(checkbox) {
  checkbox.addEventListener('change', function() {
    var editRow = this.closest('tr').nextElementSibling;
    if (editRow && editRow.classList.contains('product-edit-row')) {
      editRow.style.display = this.checked ? 'table-row' : 'none';
    }
  });
});
</script>
</body>
</html>

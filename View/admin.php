<!doctype html>
<html lang="uk">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Адмін панель</title>
  <link rel="stylesheet" href="../assets/css/header.css">
  <link rel="stylesheet" href="../assets/css/menu.css">
  <link rel="stylesheet" href="../assets/css/footer.css">
  <link rel="stylesheet" href="../assets/css/profile.css">
  <link rel="stylesheet" href="../assets/css/admin.css">
</head>
<body>
<?php
require_once __DIR__ . '/../Controller/AdminController.php';
$ctx = adminController();
if (session_status() !== PHP_SESSION_ACTIVE) session_start();
?>
<?php include __DIR__ . '/header.php'; ?>
<main class="container" style="padding:40px 18px">
  <h1>Адмін панель</h1>
  <?php if (!empty($ctx['errors'])): ?>
    <div class="errors"><ul><?php foreach ($ctx['errors'] as $e) echo '<li>'.htmlspecialchars($e).'</li>'; ?></ul></div>
  <?php endif; ?>
  <?php if (!empty($ctx['success'])): ?>
    <div class="success"><?php echo htmlspecialchars($ctx['success']); ?></div>
  <?php endif; ?>

  <section class="admin-section">
    <a href="order_stats.php" class="admin-btn">Аналітика</a>
    <a href="greenhouse_orders.php" class="admin-btn">Замовлення у теплиці</a>
    <a href="greenhouseorder_history.php" class="admin-btn">Історія замовлень</a>
    <h2>Управління користувачами</h2>
    <div class="admin-table-wrapper">
    <table class="admin-table">
      <thead>
        <tr>
          <th>ID</th>
          <th>Ім'я</th>
          <th>Прізвище</th>
          <th>Email</th>
          <th>Телефон</th>
          <th>Роль</th>
          <th>Дії</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($ctx['users'] as $user): ?>
          <tr>
            <td><?php echo htmlspecialchars($user['id']); ?></td>
            <td><?php echo htmlspecialchars($user['name']); ?></td>
            <td><?php echo htmlspecialchars($user['lastname']); ?></td>
            <td><?php echo htmlspecialchars($user['email']); ?></td>
            <td><?php echo htmlspecialchars($user['phone'] ?? ''); ?></td>
            <td><?php echo htmlspecialchars($user['role']); ?></td>
            <td>
              <?php if ($user['id'] === $ctx['current_user_id']): ?>
                <span style="color: #888; font-style: italic;">(Ви не можете змінювати самого себе)</span>
              <?php else: ?>
                <form method="post" style="display:inline;">
                  <input type="hidden" name="action" value="update_role">
                  <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
                  <select name="role">
                    <option value="CLIENT" <?php if ($user['role'] === 'CLIENT') echo 'selected'; ?>>CLIENT</option>
                    <option value="SUPPLIER" <?php if ($user['role'] === 'SUPPLIER') echo 'selected'; ?>>SUPPLIER</option>
                    <option value="MANAGER" <?php if ($user['role'] === 'MANAGER') echo 'selected'; ?>>MANAGER</option>
                    <option value="ADMIN" <?php if ($user['role'] === 'ADMIN') echo 'selected'; ?>>ADMIN</option>
                  </select>
                  <button type="submit" class="btn btn-primary">Змінити</button>
                </form>
                <form method="post" style="display:inline;">
                  <input type="hidden" name="action" value="delete_user">
                  <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
                  <button type="submit" class="btn btn-danger" onclick="return confirm('Видалити користувача? Це необоротне.')">Видалити</button>
                </form>
              <?php endif; ?>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
    </div>

    <h2 style="margin-top: 40px;">Додавання теплиці</h2>
    <form method="post" class="admin-form">
      <input type="hidden" name="action" value="add_greenhouse">
      <div class="form-group">
        <label>Назва теплиці <input name="gh_name" required></label>
      </div>
      <div class="form-group">
        <label>Адреса <input name="gh_address"></label>
      </div>
      <div class="form-group">
        <label>Телефон <input name="gh_phone"></label>
      </div>
      <button type="submit" class="btn btn-primary">Додати теплицю</button>
    </form>

    <h2 style="margin-top: 40px;">Додавання магазину</h2>
    <form method="post" class="admin-form">
      <input type="hidden" name="action" value="add_store">
      <div class="form-group">
        <label>Назва магазину <input name="store_name" required></label>
      </div>
      <div class="form-group">
        <label>Адреса <input name="store_address"></label>
      </div>
      <div class="form-group">
        <label>Телефон <input name="store_phone"></label>
      </div>
      <button type="submit" class="btn btn-primary">Додати магазин</button>
    </form>

    <h2 style="margin-top: 40px;">Список магазинів</h2>
    <div class="admin-table-wrapper">
    <table class="admin-table">
      <thead>
        <tr>
          <th>ID</th>
          <th>Назва</th>
          <th>Адреса</th>
          <th>Телефон</th>
          <th>Дії</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($ctx['stores'] as $store): ?>
          <tr <?php echo !$store['is_active'] ? 'style="opacity:0.6;background-color:#f5f5f5;"' : ''; ?>>
            <td><?php echo htmlspecialchars($store['id']); ?></td>
            <td><?php echo htmlspecialchars($store['name']); ?> <?php echo !$store['is_active'] ? '<span style="color:red;"> (деактивовано)</span>' : ''; ?></td>
            <td><?php echo htmlspecialchars($store['address'] ?? ''); ?></td>
            <td><?php echo htmlspecialchars($store['phone'] ?? ''); ?></td>
            <td>
              <?php if ($store['is_active']): ?>
                <form method="post" style="display:inline;">
                  <input type="hidden" name="action" value="deactivate_store">
                  <input type="hidden" name="store_id" value="<?php echo $store['id']; ?>">
                  <button type="submit" class="btn btn-warning" onclick="return confirm('Деактивувати магазин?')">Деактивувати</button>
                </form>
              <?php else: ?>
                <form method="post" style="display:inline;">
                  <input type="hidden" name="action" value="activate_store">
                  <input type="hidden" name="store_id" value="<?php echo $store['id']; ?>">
                  <button type="submit" class="btn btn-success">Активувати</button>
                </form>
              <?php endif; ?>
              <form method="post" style="display:inline;">
                <input type="hidden" name="action" value="delete_store">
                <input type="hidden" name="store_id" value="<?php echo $store['id']; ?>">
                <button type="submit" class="btn btn-danger" onclick="return confirm('Видалити магазин повністю? Це необоротне.')">Видалити</button>
              </form>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
    </div>

    <h2 style="margin-top: 40px;">Список теплиць</h2>
    <div class="admin-table-wrapper">
    <table class="admin-table">
      <thead>
        <tr>
          <th>ID</th>
          <th>Назва теплиці</th>
          <th>Адреса</th>
          <th>Телефон</th>
          <th>Поточний постачальник</th>
          <th>Дії</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($ctx['greenhouses'] as $gh): ?>
          <tr <?php echo !$gh['is_active'] ? 'style="opacity:0.6;background-color:#f5f5f5;"' : ''; ?>>
            <td><?php echo htmlspecialchars($gh['id']); ?></td>
            <td><?php echo htmlspecialchars($gh['name']); ?> <?php echo !$gh['is_active'] ? '<span style="color:red;"> (деактивовано)</span>' : ''; ?></td>
            <td><?php echo htmlspecialchars($gh['address'] ?? ''); ?></td>
            <td><?php echo htmlspecialchars($gh['phone'] ?? ''); ?></td>
            <td><?php echo $gh['supplier_name'] ? htmlspecialchars($gh['supplier_name'] . ' ' . ($gh['supplier_lastname'] ?? '')) : 'Не призначено'; ?></td>
            <td>
              <form method="post" style="display:inline;">
                <input type="hidden" name="action" value="assign_supplier">
                <input type="hidden" name="greenhouse_id" value="<?php echo $gh['id']; ?>">
                <select name="supplier_id">
                  <option value="">-- Без постачальника --</option>
                  <?php foreach ($ctx['suppliers'] as $supplier): ?>
                    <option value="<?php echo $supplier['id']; ?>" <?php if ($gh['supplier_id'] == $supplier['id']) echo 'selected'; ?>>
                      <?php echo htmlspecialchars($supplier['name'] . ' ' . ($supplier['lastname'] ?? '')); ?>
                    </option>
                  <?php endforeach; ?>
                </select>
                <button type="submit" class="btn btn-primary">Прив'язати</button>
              </form>
              <?php if ($gh['is_active']): ?>
                <form method="post" style="display:inline;">
                  <input type="hidden" name="action" value="deactivate_greenhouse">
                  <input type="hidden" name="greenhouse_id" value="<?php echo $gh['id']; ?>">
                  <button type="submit" class="btn btn-warning" onclick="return confirm('Деактивувати теплицю?')">Деактивувати</button>
                </form>
              <?php else: ?>
                <form method="post" style="display:inline;">
                  <input type="hidden" name="action" value="activate_greenhouse">
                  <input type="hidden" name="greenhouse_id" value="<?php echo $gh['id']; ?>">
                  <button type="submit" class="btn btn-success">Активувати</button>
                </form>
              <?php endif; ?>
              <form method="post" style="display:inline;">
                <input type="hidden" name="action" value="delete_greenhouse">
                <input type="hidden" name="greenhouse_id" value="<?php echo $gh['id']; ?>">
                <button type="submit" class="btn btn-danger" onclick="return confirm('Видалити теплицю повністю? Це необоротне.')">Видалити</button>
              </form>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
    </div>
  </section>

  <section class="admin-section">
    <h2>Управління каталогом продукції</h2>

    <h3>Додати продукт</h3>
    <form method="post">
      <input type="hidden" name="action" value="add_product">
      <div class="form-group">
        <label>Категорія
          <select name="category">
            <option value="SEED">Саджанець</option>
            <option value="YOUNG">Молода</option>
            <option value="BLOOMING" selected>Квітучий</option>
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
        <label>Кількість на складі <input type="number" name="stock" value="0" min="0" required></label>
      </div>
      <div class="form-group">
        <label>Магазин
          <select name="store_id" required>
            <option value="">-- Виберіть магазин --</option>
            <?php foreach ($ctx['stores'] as $store): ?>
              <?php if ($store['is_active']): ?>
                <option value="<?php echo $store['id']; ?>"><?php echo htmlspecialchars($store['name']); ?></option>
              <?php endif; ?>
            <?php endforeach; ?>
          </select>
        </label>
      </div>
      <div class="form-group">
        <label>Знижка
          <select name="discount_id">
            <option value="">Без знижки</option>
            <?php foreach ($ctx['discounts'] as $discount): ?>
              <option value="<?php echo $discount['id']; ?>"><?php echo htmlspecialchars($discount['name']); ?> (<?php echo $discount['percentage']; ?>%)</option>
            <?php endforeach; ?>
          </select>
        </label>
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

    <h3>Список продуктів</h3>
    <div class="admin-table-wrapper">
    <table class="admin-table">
      <thead>
        <tr>
          <th>ID</th>
          <th>Фото</th>
          <th>Категорія</th>
          <th>Назва</th>
          <th>Опис</th>
          <th>Ціна</th>
          <th>На складі</th>
          <th>Магазини</th>
          <th>Знижка %</th>
          <th>Активний</th>
          <th>Дії</th>
        </tr>
      </thead>
      <tbody>
        <?php 
          // Сортування: товари з наявністю спочатку, потім з нульовою кількістю
          usort($ctx['products'], function($a, $b) {
              if ($a['stock'] > 0 && $b['stock'] == 0) return -1;
              if ($a['stock'] == 0 && $b['stock'] > 0) return 1;
              return 0;
          });
          foreach ($ctx['products'] as $product): 
        ?>
          <?php
            if (!empty($product['image'])) {
                $imgRaw = $product['image'];
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
            <td><?php echo htmlspecialchars($product['id']); ?></td>
            <td><img src="<?php echo htmlspecialchars($imgPath); ?>" alt="" class="admin-thumb"></td>
            <td><?php echo htmlspecialchars($product['category']); ?></td>
            <td><?php echo htmlspecialchars($product['flower_name']); ?></td>
            <td><?php echo htmlspecialchars($product['description']); ?></td>
            <td><?php echo htmlspecialchars($product['price']); ?></td>
            <td style="<?php echo $product['stock'] == 0 ? 'color: red; font-weight: bold;' : ''; ?>"><?php echo htmlspecialchars($product['stock']); ?></td>
            <td><?php echo htmlspecialchars($product['stores'] ?? 'Немає'); ?></td>
            <td><?php echo htmlspecialchars($product['discount_percentage'] ? $product['discount_percentage'] . '%' : 'Немає'); ?></td>
            <td><?php echo $product['is_active'] ? 'Так' : 'Ні'; ?></td>
            <td class="product-actions">
              <label for="edit-<?php echo $product['id']; ?>" class="btn btn-primary">Редагувати</label>
              <form method="post" style="display:inline;">
                <input type="hidden" name="action" value="delete_product">
                <input type="hidden" name="product_id" value="<?php echo $product['id']; ?>">
                <button type="submit" class="btn btn-danger" onclick="return confirm('Видалити продукт?')">Видалити</button>
              </form>
            </td>
          </tr>

          <tr class="product-edit-row">
            <td colspan="11">
              <input type="checkbox" id="edit-<?php echo $product['id']; ?>" class="edit-toggle">
              <div class="edit-panel">
                <h4>Редагувати продукт #<?php echo htmlspecialchars($product['id']); ?></h4>
                <form method="post">
                  <input type="hidden" name="action" value="update_product">
                  <input type="hidden" name="product_id" value="<?php echo $product['id']; ?>">
                  <div class="form-group">
                    <label>Категорія
                      <select name="category">
                        <option value="SEED" <?php if ($product['category']==='SEED') echo 'selected'; ?>>Саджанець</option>
                        <option value="YOUNG" <?php if ($product['category']==='YOUNG') echo 'selected'; ?>>Молода</option>
                        <option value="BLOOMING" <?php if ($product['category']==='BLOOMING') echo 'selected'; ?>>Квітучий</option>
                      </select>
                    </label>
                  </div>
                  <div class="form-group">
                    <label>Назва квітки <input name="flower_name" value="<?php echo htmlspecialchars($product['flower_name']); ?>" required></label>
                  </div>
                  <div class="form-group">
                    <label>Опис <textarea name="description"><?php echo htmlspecialchars($product['description']); ?></textarea></label>
                  </div>
                  <div class="form-group">
                    <label>Розширений опис <textarea name="long_description" style="min-height:120px;"><?php echo htmlspecialchars($product['long_description'] ?? ''); ?></textarea></label>
                  </div>
                  <div class="form-group">
                    <label>Ціна <input type="number" step="0.01" name="price" value="<?php echo htmlspecialchars($product['price']); ?>" required></label>
                  </div>
                  <div class="form-group">
                    <label>Кількість на складі <input type="number" name="stock" value="<?php echo htmlspecialchars($product['stock'] ?? 0); ?>" min="0" required></label>
                  </div>
                  <div class="form-group">
                    <label>Знижка
                      <select name="discount_id">
                        <option value="">Без знижки</option>
                        <?php foreach ($ctx['discounts'] as $discount): ?>
                          <option value="<?php echo $discount['id']; ?>" <?php if (!empty($product['discount_id']) && $product['discount_id']==$discount['id']) echo 'selected'; ?>><?php echo htmlspecialchars($discount['name']); ?> (<?php echo $discount['percentage']; ?>%)</option>
                        <?php endforeach; ?>
                      </select>
                    </label>
                  </div>
                  <div class="form-group">
                    <label>Зображення (виберіть з папки Images)
                      <?php $curImg = !empty($product['image']) ? basename($product['image']) : ''; ?>
                      <select name="image">
                        <option value="">-- Без зображення --</option>
                        <?php foreach (($ctx['images'] ?? []) as $img): ?>
                          <option value="<?php echo htmlspecialchars($img); ?>" <?php if ($curImg === $img) echo 'selected'; ?>><?php echo htmlspecialchars($img); ?></option>
                        <?php endforeach; ?>
                      </select>
                    </label>
                  </div>
                  <div class="form-group">
                    <label><input type="checkbox" name="is_active" <?php if ($product['is_active']) echo 'checked'; ?>> Активний</label>
                  </div>
                  <div style="display:flex;gap:10px;margin-top:8px;align-items:center">
                    <button type="submit" class="btn btn-primary">Оновити</button>
                    <label for="edit-<?php echo $product['id']; ?>" class="btn btn-ghost">Скасувати</label>
                  </div>
                </form>
              </div>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
    </div>
  </section>
</main>
<?php include __DIR__ . '/footer.php'; ?>
</body>
</html>
</body>
</html>
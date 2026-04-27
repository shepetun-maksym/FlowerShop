<!doctype html>
<html lang="uk">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Замовлення у теплиці</title>
  <link rel="stylesheet" href="../assets/css/header.css">
  <link rel="stylesheet" href="../assets/css/menu.css">
  <link rel="stylesheet" href="../assets/css/footer.css">
  <link rel="stylesheet" href="../assets/css/profile.css">
  <link rel="stylesheet" href="../assets/css/admin.css">
</head>
<body>
<?php
if (session_status() !== PHP_SESSION_ACTIVE) session_start();
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../Model/User.php';
require_once __DIR__ . '/../Model/Product.php';
require_once __DIR__ . '/../Controller/AdminController.php';

if (empty($_SESSION['user_id'])) {
    header('Location: login.php'); exit;
}

$pdo = getPDO();
$userId = (int)$_SESSION['user_id'];
$user = findUserById($pdo, $userId);

if (!$user || $user['role'] !== 'ADMIN') {
    echo 'Доступ заборонено';
    exit;
}

$errors = [];
$success = false;
$greenhouses = getAllGreenhouses($pdo);
$selectedGreenhouse = null;
$inventory = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'create_order') {
        $greenhouseId = (int)($_POST['greenhouse_id'] ?? 0);
        $storeId = (int)($_POST['store_id'] ?? 0);
        
        if (!$storeId) {
            $errors[] = 'Виберіть магазин.';
        } else {
            // Збираємо товари
            $items = [];
            foreach ($_POST as $key => $value) {
                if (strpos($key, 'qty_') === 0) {
                    $productId = (int)substr($key, 4);
                    $qty = max(0, (int)$value);
                    if ($qty > 0) {
                        $items[] = ['product_id' => $productId, 'quantity' => $qty];
                    }
                }
            }
            
            if (empty($items)) {
                $errors[] = 'Виберіть хоча б один товар.';
            } else {
                try {
                    $orderId = createGreenhouseOrder($pdo, $userId, $greenhouseId, $storeId, $items);
                    if ($orderId) {
                        $success = 'Замовлення створено! ID: ' . $orderId;
                    } else {
                        $errors[] = 'Помилка при створенні замовлення.';
                    }
                } catch (Exception $e) {
                    $errors[] = 'Помилка: ' . $e->getMessage();
                }
            }
        }
    }
}

// Отримамо товари якщо обрана теплиця
if (isset($_GET['greenhouse_id'])) {
    $selectedGreenhouseId = (int)$_GET['greenhouse_id'];
    $inventory = getGreenhouseInventoryByLocation($pdo, $selectedGreenhouseId);
    
    foreach ($greenhouses as $gh) {
        if ($gh['id'] == $selectedGreenhouseId) {
            $selectedGreenhouse = $gh;
            break;
        }
    }
}
?>
<?php include __DIR__ . '/header.php'; ?>
<main class="container" style="padding:40px 18px">
  <h1>Замовлення продукції у теплиці</h1>
  
  <?php if (!empty($errors)): ?>
    <div class="errors"><ul><?php foreach ($errors as $e) echo '<li>'.htmlspecialchars($e).'</li>'; ?></ul></div>
  <?php endif; ?>
  <?php if ($success): ?>
    <div class="success"><?php echo htmlspecialchars($success); ?></div>
  <?php endif; ?>

  <section class="admin-section">
    <h2>Оберіть теплицю</h2>
    <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(250px,1fr));gap:20px;">
      <?php foreach ($greenhouses as $gh): ?>
        <div style="border:1px solid #ddd;padding:15px;border-radius:8px;">
          <h3><?php echo htmlspecialchars($gh['name']); ?></h3>
          <p><strong>Постачальник:</strong> <?php echo htmlspecialchars($gh['supplier_name'] . ' ' . $gh['supplier_lastname']); ?></p>
          <p><strong>Адреса:</strong> <?php echo htmlspecialchars($gh['address'] ?? 'Не вказано'); ?></p>
          <p><strong>Телефон:</strong> <?php echo htmlspecialchars($gh['phone'] ?? 'Не вказано'); ?></p>
          <a href="?greenhouse_id=<?php echo $gh['id']; ?>" class="btn btn-primary">Вибрати</a>
        </div>
      <?php endforeach; ?>
    </div>

    <?php if ($selectedGreenhouse): ?>
      <h2 style="margin-top:40px;">Замовлення з теплиці "<?php echo htmlspecialchars($selectedGreenhouse['name']); ?>"</h2>
      
      <?php if (empty($inventory)): ?>
        <p>В цій теплиці немає товарів.</p>
      <?php else: ?>
        <form method="post">
          <input type="hidden" name="action" value="create_order">
          <input type="hidden" name="greenhouse_id" value="<?php echo $selectedGreenhouse['id']; ?>">
          
          <div style="margin-bottom:24px;padding:16px;background:#f9f7f5;border-radius:8px;border:1px solid #e0d5cb;">
            <label for="store_id" style="display:block;margin-bottom:8px;font-weight:500;color:var(--brown);">Виберіть магазин для поповнення запасів:</label>
            <select name="store_id" id="store_id" required style="width:100%;padding:10px;border:1px solid #ddd;border-radius:6px;font-size:14px;">
              <option value="">-- Виберіть магазин --</option>
              <?php 
              require_once __DIR__ . '/../Model/Order.php';
              $stores = getStores($pdo);
              foreach ($stores as $store): 
              ?>
                <option value="<?php echo $store['id']; ?>">
                  <?php echo htmlspecialchars($store['name']); ?> (<?php echo htmlspecialchars($store['address']); ?>)
                </option>
              <?php endforeach; ?>
            </select>
          </div>
          
          <table class="admin-table">
            <thead>
              <tr>
                <th>Товар</th>
                <th>Категорія</th>
                <th>Ціна</th>
                <th>Доступно</th>
                <th>Замовити</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($inventory as $item): ?>
                <tr>
                  <td><?php echo htmlspecialchars($item['flower_name']); ?></td>
                  <td><?php echo getCategoryLabel($item['category']); ?></td>
                  <td><?php echo number_format((float)$item['price'], 2); ?> грн</td>
                  <td><?php echo $item['quantity']; ?> шт.</td>
                  <td>
                    <input type="number" name="qty_<?php echo $item['product_id']; ?>" value="0" min="0" max="<?php echo $item['quantity']; ?>" style="width:80px;">
                  </td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
          
          <button type="submit" class="btn btn-primary" style="margin-top:20px;">Створити замовлення</button>
        </form>
      <?php endif; ?>
    <?php endif; ?>
  </section>
</main>

<?php include __DIR__ . '/footer.php'; ?>
</body>
</html>

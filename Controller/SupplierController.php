<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../Model/User.php';
require_once __DIR__ . '/../Model/Product.php';

function supplierController(): array {
    if (session_status() !== PHP_SESSION_ACTIVE) session_start();
    $result = ['errors' => [], 'success' => false, 'inventory' => [], 'images' => [], 'greenhouse' => null, 'greenhouses' => []];

    if (empty($_SESSION['user_id'])) {
        header('Location: login.php'); exit;
    }

    try {
        $pdo = getPDO();
        $userId = (int)$_SESSION['user_id'];
        $user = findUserById($pdo, $userId);
        if (!$user || $user['role'] !== 'SUPPLIER') {
            $result['errors'][] = 'Доступ заборонено. Тільки постачальник може переглядати цю сторінку.';
            return $result;
        }

        // отримати всі теплиці постачальника
        $ghStmt = $pdo->prepare("SELECT id, name FROM greenhouses WHERE supplier_id = :supplier_id ORDER BY name");
        $ghStmt->execute([':supplier_id' => $userId]);
        $greenhouses = $ghStmt->fetchAll(PDO::FETCH_ASSOC);
        
        $result['greenhouses'] = $greenhouses;

        // Отримати обрану теплицю або першу за замовчуванням
        $selectedGreenhouseId = null;
        if (isset($_GET['greenhouse_id'])) {
            $selectedGreenhouseId = (int)$_GET['greenhouse_id'];
            // Перевіримо що теплиця належить цьому постачальнику
            $checkStmt = $pdo->prepare("SELECT id FROM greenhouses WHERE id = :id AND supplier_id = :supplier_id");
            $checkStmt->execute([':id' => $selectedGreenhouseId, ':supplier_id' => $userId]);
            if (!$checkStmt->fetch()) {
                $selectedGreenhouseId = null;
            }
        }
        
        if (!$selectedGreenhouseId && !empty($greenhouses)) {
            $selectedGreenhouseId = (int)$greenhouses[0]['id'];
        }

        if (!$selectedGreenhouseId) {
            $result['errors'][] = 'Теплиця не знайдена для цього постачальника.';
            return $result;
        }

        // отримати обрану теплицю
        $ghStmt = $pdo->prepare("SELECT id, name FROM greenhouses WHERE id = :id AND supplier_id = :supplier_id");
        $ghStmt->execute([':id' => $selectedGreenhouseId, ':supplier_id' => $userId]);
        $greenhouse = $ghStmt->fetch(PDO::FETCH_ASSOC);

        $result['greenhouse'] = $greenhouse;
        $greenhouseId = (int)$greenhouse['id'];

        // отримати доступні файли зображень із папки іmages
        $images = [];
        $imgPath = __DIR__ . '/../Images/';
        if (is_dir($imgPath)) {
            $files = scandir($imgPath);
            foreach ($files as $f) {
                if (in_array(pathinfo($f, PATHINFO_EXTENSION), ['jpg', 'jpeg', 'png', 'gif', 'webp'])) {
                    $images[] = $f;
                }
            }
        }
        $result['images'] = $images;

        // Переглянути поточний асортимент у GREENHOUSE
        $result['inventory'] = getGreenhouseInventoryWithProducts($pdo, $greenhouseId);

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $action = $_POST['action'] ?? '';

            if ($action === 'add_product') {
                $category = $_POST['category'] ?? '';
                $flowerName = trim($_POST['flower_name'] ?? '');
                $description = trim($_POST['description'] ?? '');
                $longDescription = trim($_POST['long_description'] ?? '');
                $price = (float)($_POST['price'] ?? 0);
                $quantity = max(0, (int)($_POST['quantity'] ?? 0));
                $image = $_POST['image'] ?? '';

                if (!$flowerName || $price <= 0 || $quantity <= 0) {
                    $result['errors'][] = 'Заповніть усі необхідні поля (назва, ціна, кількість).';
                } else {
                    if (addProductToGreenhouse($pdo, $greenhouseId, $category, $flowerName, $description, $longDescription, $price, $quantity, $image)) {
                        $result['success'] = 'Товар додано в теплицю.';
                        // Оновлення інвентаря
                        $result['inventory'] = getGreenhouseInventoryWithProducts($pdo, $greenhouseId);
                    } else {
                        $result['errors'][] = 'Помилка при додаванні товару.';
                    }
                }
            } elseif ($action === 'update_product') {
                $inventoryId = (int)($_POST['inventory_id'] ?? 0);
                $productId = (int)($_POST['product_id'] ?? 0);
                $flowerName = trim($_POST['flower_name'] ?? '');
                $description = trim($_POST['description'] ?? '');
                $longDescription = trim($_POST['long_description'] ?? '');
                $category = $_POST['category'] ?? '';
                $price = (float)($_POST['price'] ?? 0);
                $quantity = max(0, (int)($_POST['quantity'] ?? 0));
                $image = $_POST['image'] ?? '';

                if (!$flowerName || $price <= 0) {
                    $result['errors'][] = 'Заповніть усі необхідні поля.';
                } else {
                    if (updateGreenhouseProduct($pdo, $inventoryId, $productId, $flowerName, $description, $longDescription, $category, $price, $quantity, $image, $greenhouseId)) {
                        $result['success'] = 'Товар оновлено.';
                  
                        $result['inventory'] = getGreenhouseInventoryWithProducts($pdo, $greenhouseId);
                    } else {
                        $result['errors'][] = 'Помилка при оновленні товару.';
                    }
                }
            } elseif ($action === 'delete_product') {
                $inventoryId = (int)($_POST['inventory_id'] ?? 0);
                $productId = (int)($_POST['product_id'] ?? 0);

                if ($inventoryId && $productId) {
                    if (deleteGreenhouseProduct($pdo, $inventoryId, $productId)) {
                        $result['success'] = 'Товар видалено з теплиці.';
                        
                        $result['inventory'] = getGreenhouseInventoryWithProducts($pdo, $greenhouseId);
                    } else {
                        $result['errors'][] = 'Помилка при видаленні.';
                    }
                }
            }
        }
    } catch (Exception $e) {
        $result['errors'][] = 'Помилка: ' . $e->getMessage();
    }

    return $result;
}

//отримати весь inventory теплиці з деталями продуктів
function getGreenhouseInventoryWithProducts(PDO $pdo, int $locationId): array {
    $sql = "SELECT i.id as inventory_id, i.product_id, p.flower_name, p.category, p.description, p.long_description, p.price, p.image, i.quantity
            FROM inventory i
            JOIN products p ON i.product_id = p.id
            WHERE i.location_type = 'GREENHOUSE' AND i.location_id = :location_id
            ORDER BY i.id";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([':location_id' => $locationId]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

//додати новий товар в теплицю 
function addProductToGreenhouse(PDO $pdo, int $greenhouseId, string $category, string $flowerName, string $description, string $longDescription, float $price, int $quantity, string $image = ''): bool {

    $sql = "INSERT INTO products (category, flower_name, description, long_description, price, image, is_active) 
            VALUES (:category, :flower_name, :description, :long_description, :price, :image, TRUE) 
            RETURNING id";
    $stmt = $pdo->prepare($sql);
    $success = $stmt->execute([
        ':category' => $category ?: null,
        ':flower_name' => $flowerName,
        ':description' => $description,
        ':long_description' => $longDescription ?: null,
        ':price' => $price,
        ':image' => $image ?: null
    ]);

    if ($success) {
        $productId = $stmt->fetchColumn();
        // додати в inventory теплиці з location_id = greenhouse_id
        $invStmt = $pdo->prepare("INSERT INTO inventory (product_id, location_type, location_id, quantity) VALUES (:product_id, 'GREENHOUSE', :location_id, :quantity)");
        return $invStmt->execute([
            ':product_id' => $productId,
            ':location_id' => $greenhouseId,
            ':quantity' => $quantity
        ]);
    }
    return false;
}

//оновити товар у теплиці (оновити в products + inventory quantity)
function updateGreenhouseProduct(PDO $pdo, int $inventoryId, int $productId, string $flowerName, string $description, string $longDescription, string $category, float $price, int $quantity, string $image, int $greenhouseId): bool {
    // перевірити що це товар у теплиці
    $checkStmt = $pdo->prepare("SELECT id FROM inventory WHERE id = :inv_id AND location_type = 'GREENHOUSE' AND location_id = :gh_id LIMIT 1");
    $checkStmt->execute([':inv_id' => $inventoryId, ':gh_id' => $greenhouseId]);
    if (!$checkStmt->fetch()) {
        return false;
    }

    // оновити product
    $sql = "UPDATE products SET category = :category, flower_name = :flower_name, description = :description, long_description = :long_description, price = :price, image = :image WHERE id = :id";
    $stmt = $pdo->prepare($sql);
    $stmt->bindValue(':category', $category ?: null, $category ? PDO::PARAM_STR : PDO::PARAM_NULL);
    $stmt->bindValue(':flower_name', $flowerName, PDO::PARAM_STR);
    $stmt->bindValue(':description', $description, PDO::PARAM_STR);
    $stmt->bindValue(':long_description', $longDescription ?: null, $longDescription ? PDO::PARAM_STR : PDO::PARAM_NULL);
    $stmt->bindValue(':price', $price, PDO::PARAM_STR);
    $stmt->bindValue(':image', $image ?: null, $image ? PDO::PARAM_STR : PDO::PARAM_NULL);
    $stmt->bindValue(':id', $productId, PDO::PARAM_INT);
    
    $success = $stmt->execute();
    
    // оновити quantity в inventory
    if ($success) {
        $invStmt = $pdo->prepare("UPDATE inventory SET quantity = :quantity WHERE id = :inv_id");
        $success = $invStmt->execute([':quantity' => $quantity, ':inv_id' => $inventoryId]);
    }
    
    return $success;
}

//Видалити товар з теплиці (видалити тільки з inventory)
//Товар залишається в products, якщо він також є в інших локаціях (STORE тощо)
function deleteGreenhouseProduct(PDO $pdo, int $inventoryId, int $productId): bool {

    // Видалити тільки з inventory
    $invStmt = $pdo->prepare("DELETE FROM inventory WHERE id = :inv_id");
    return $invStmt->execute([':inv_id' => $inventoryId]);
}
?>
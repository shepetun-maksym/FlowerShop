<?php

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../Model/User.php';
require_once __DIR__ . '/../Model/Product.php';

//основний контролер адміністратора, який обробляє запити для керування користувачами, товарами, теплицями та магазинами
function adminController(): array {
    if (session_status() !== PHP_SESSION_ACTIVE) {
        session_start();
    }

    $result = [
        'errors' => [],
        'success' => false,
        'users' => [],
        'products' => [],
        'discounts' => [],
        'greenhouses' => [],
        'suppliers' => [],
        'stores' => [],
        'images' => [],
        'current_user_id' => null
    ];

    if (empty($_SESSION['user_id'])) {
        header('Location: login.php');
        exit;
    }

    try {
        $pdo = getPDO();
        $userId = (int)$_SESSION['user_id'];
        $result['current_user_id'] = $userId;

        $user = findUserById($pdo, $userId);
        if (!$user || $user['role'] !== 'ADMIN') {
            $result['errors'][] = 'Доступ заборонено. Тільки адміністратор може переглядати цю сторінку.';
            return $result;
        }

        // завантажуємо дані для сторінки
        $result['users'] = getAllUsers($pdo);
        $result['products'] = getAllProductsAdmin($pdo);
        $result['greenhouses'] = getAllGreenhousesWithSuppliers($pdo);
        $result['suppliers'] = getAllSuppliers($pdo);
        $result['stores'] = getAllStores($pdo);
        $result['images'] = loadAvailableImages();
        $result['discounts'] = getAllDiscounts($pdo);

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $action = $_POST['action'] ?? '';
            handleAdminAction($pdo, $userId, $action, $result);

            // оновлюємо дані після виконання дії
            $result['users'] = getAllUsers($pdo);
            $result['products'] = getAllProductsAdmin($pdo);
            $result['greenhouses'] = getAllGreenhousesWithSuppliers($pdo);
            $result['suppliers'] = getAllSuppliers($pdo);
            $result['stores'] = getAllStores($pdo);
            $result['discounts'] = getAllDiscounts($pdo);
        }

    } catch (Exception $e) {
        $result['errors'][] = 'Системна помилка: ' . $e->getMessage();
    }

    return $result;
}

// допоміжні функції для обробки дій

//Завантажує доступні зображення з директорії Images
function loadAvailableImages(): array {
    $images = [];
    $imgDir = __DIR__ . '/../Images';

    if (!is_dir($imgDir)) {
        return $images;
    }

    $files = scandir($imgDir);
    $allowedExtensions = ['jpg', 'jpeg', 'png', 'gif', 'webp'];

    foreach ($files as $file) {
        if ($file === '.' || $file === '..') {
            continue;
        }

        $ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));
        if (in_array($ext, $allowedExtensions)) {
            $images[] = $file;
        }
    }

    return $images;
}

//Обробляє дії адміністратора з POST запиту
function handleAdminAction(PDO $pdo, int $userId, string $action, array &$result): void {
    switch ($action) {
        case 'update_role':
            handleUpdateUserRole($pdo, $userId, $result);
            break;

        case 'delete_user':
            handleDeleteUser($pdo, $userId, $result);
            break;

        case 'assign_supplier':
            handleAssignSupplier($pdo, $result);
            break;

        case 'add_greenhouse':
            handleAddGreenhouse($pdo, $result);
            break;

        case 'add_store':
            handleAddStore($pdo, $result);
            break;

        case 'delete_greenhouse':
            handleDeleteGreenhouse($pdo, $result);
            break;

        case 'deactivate_greenhouse':
            handleDeactivateGreenhouse($pdo, $result);
            break;

        case 'activate_greenhouse':
            handleActivateGreenhouse($pdo, $result);
            break;

        case 'delete_store':
            handleDeleteStore($pdo, $result);
            break;

        case 'deactivate_store':
            handleDeactivateStore($pdo, $result);
            break;

        case 'activate_store':
            handleActivateStore($pdo, $result);
            break;

        case 'add_product':
            handleAddProduct($pdo, $result);
            break;

        case 'update_product':
            handleUpdateProduct($pdo, $result);
            break;

        case 'delete_product':
            handleDeleteProduct($pdo, $result);
            break;
    }
}

//обробляє оновлення ролі користувача
function handleUpdateUserRole(PDO $pdo, int $userId, array &$result): void {
    $userIdToUpdate = (int)($_POST['user_id'] ?? 0);
    $newRole = $_POST['role'] ?? '';

    if ($userIdToUpdate === $userId) {
        $result['errors'][] = 'Ви не можете змінити свою роль.';
        return;
    }

    $validRoles = ['CLIENT', 'SUPPLIER', 'MANAGER', 'ADMIN'];
    if (!in_array($newRole, $validRoles)) {
        $result['errors'][] = 'Неправильна роль.';
        return;
    }

    if (updateUserRole($pdo, $userIdToUpdate, $newRole)) {
        $result['success'] = 'Роль користувача оновлено.';
    } else {
        $result['errors'][] = 'Помилка при оновленні ролі.';
    }
}

//обробляє видалення користувача
function handleDeleteUser(PDO $pdo, int $userId, array &$result): void {
    $userIdToDelete = (int)($_POST['user_id'] ?? 0);

    if ($userIdToDelete === $userId) {
        $result['errors'][] = 'Ви не можете видалити самого себе.';
        return;
    }

    if (deleteUser($pdo, $userIdToDelete)) {
        $result['success'] = 'Користувача видалено.';
    } else {
        $result['errors'][] = 'Помилка при видаленні користувача.';
    }
}

//обробляє прив'язування постачальника до теплиці
function handleAssignSupplier(PDO $pdo, array &$result): void {
    $greenhouseId = (int)($_POST['greenhouse_id'] ?? 0);
    $supplierId = !empty($_POST['supplier_id']) ? (int)$_POST['supplier_id'] : null;

    if (assignSupplierToGreenhouse($pdo, $greenhouseId, $supplierId)) {
        $result['success'] = 'Постачальника прив\'язано до теплиці.';
    } else {
        $result['errors'][] = 'Помилка при прив\'язуванні постачальника.';
    }
}

//обробляє додавання нової теплиці
function handleAddGreenhouse(PDO $pdo, array &$result): void {
    $name = trim($_POST['gh_name'] ?? '');
    $address = trim($_POST['gh_address'] ?? '');
    $phone = trim($_POST['gh_phone'] ?? '');

    if ($name === '') {
        $result['errors'][] = 'Назва теплиці обов\'язкова.';
        return;
    }

    if (addGreenhouse($pdo, $name, $address, $phone)) {
        $result['success'] = 'Теплицю додано.';
    } else {
        $result['errors'][] = 'Помилка при додаванні теплиці.';
    }
}

//обробляє додавання нового магазину
function handleAddStore(PDO $pdo, array &$result): void {
    $name = trim($_POST['store_name'] ?? '');
    $address = trim($_POST['store_address'] ?? '');
    $phone = trim($_POST['store_phone'] ?? '');

    if ($name === '') {
        $result['errors'][] = 'Назва магазину обов\'язкова.';
        return;
    }

    if (addStore($pdo, $name, $address, $phone)) {
        $result['success'] = 'Магазин додано.';
    } else {
        $result['errors'][] = 'Помилка при додаванні магазину.';
    }
}

//обробляє видалення теплиці
function handleDeleteGreenhouse(PDO $pdo, array &$result): void {
    $greenhouseId = (int)($_POST['greenhouse_id'] ?? 0);

    if (deleteGreenhouse($pdo, $greenhouseId)) {
        $result['success'] = 'Теплицю повністю видалено.';
    } else {
        $result['errors'][] = 'Помилка при видаленні теплиці.';
    }
}

//обробляє деактивацію теплиці
function handleDeactivateGreenhouse(PDO $pdo, array &$result): void {
    $greenhouseId = (int)($_POST['greenhouse_id'] ?? 0);

    if (deactivateGreenhouse($pdo, $greenhouseId)) {
        $result['success'] = 'Теплицю деактивовано.';
    } else {
        $result['errors'][] = 'Помилка при деактивації теплиці.';
    }
}

//обробляє активацію теплиці
function handleActivateGreenhouse(PDO $pdo, array &$result): void {
    $greenhouseId = (int)($_POST['greenhouse_id'] ?? 0);

    if (activateGreenhouse($pdo, $greenhouseId)) {
        $result['success'] = 'Теплицю активовано.';
    } else {
        $result['errors'][] = 'Помилка при активації теплиці.';
    }
}

//обробляє видалення магазину
function handleDeleteStore(PDO $pdo, array &$result): void {
    $storeId = (int)($_POST['store_id'] ?? 0);

    if (deleteStore($pdo, $storeId)) {
        $result['success'] = 'Магазин повністю видалено.';
    } else {
        $result['errors'][] = 'Помилка при видаленні магазину.';
    }
}

//обробляє деактивацію магазину
function handleDeactivateStore(PDO $pdo, array &$result): void {
    $storeId = (int)($_POST['store_id'] ?? 0);

    if (deactivateStore($pdo, $storeId)) {
        $result['success'] = 'Магазин деактивовано.';
    } else {
        $result['errors'][] = 'Помилка при деактивації магазину.';
    }
}

//обробляє активацію магазину
function handleActivateStore(PDO $pdo, array &$result): void {
    $storeId = (int)($_POST['store_id'] ?? 0);

    if (activateStore($pdo, $storeId)) {
        $result['success'] = 'Магазин активовано.';
    } else {
        $result['errors'][] = 'Помилка при активації магазину.';
    }
}

//обробляє додавання продукту
function handleAddProduct(PDO $pdo, array &$result): void {
    $category = $_POST['category'] ?? 'BLOOMING';
    $flowerName = trim($_POST['flower_name'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $longDescription = trim($_POST['long_description'] ?? '');
    $price = (float)($_POST['price'] ?? 0);
    $stock = max(0, (int)($_POST['stock'] ?? 0));
    $discountId = !empty($_POST['discount_id']) ? (int)$_POST['discount_id'] : null;
    $image = trim($_POST['image'] ?? '');
    $storeId = !empty($_POST['store_id']) ? (int)$_POST['store_id'] : null;

    if ($flowerName === '' || $price <= 0) {
        $result['errors'][] = 'Назва та ціна обов\'язкові.';
        return;
    }

    if ($storeId === null) {
        $result['errors'][] = 'Виберіть магазин.';
        return;
    }

    if (addProduct($pdo, $category, $flowerName, $description, $longDescription, $price, $stock, $discountId, $image, $storeId)) {
        $result['success'] = 'Продукт додано.';
    } else {
        $result['errors'][] = 'Помилка при додаванні продукту.';
    }
}

//обробляє оновлення продукту
function handleUpdateProduct(PDO $pdo, array &$result): void {
    $productId = (int)($_POST['product_id'] ?? 0);
    $category = $_POST['category'] ?? 'BLOOMING';
    $flowerName = trim($_POST['flower_name'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $longDescription = trim($_POST['long_description'] ?? '');
    $price = (float)($_POST['price'] ?? 0);
    $stock = max(0, (int)($_POST['stock'] ?? 0));
    $discountId = !empty($_POST['discount_id']) ? (int)$_POST['discount_id'] : null;
    $isActive = isset($_POST['is_active']);
    $image = trim($_POST['image'] ?? '');

    if ($flowerName === '' || $price <= 0) {
        $result['errors'][] = 'Назва та ціна обов\'язкові.';
        return;
    }

    if (updateProduct($pdo, $productId, $category, $flowerName, $description, $longDescription, $price, $stock, $discountId, $isActive, $image)) {
        $result['success'] = 'Продукт оновлено.';
    } else {
        $result['errors'][] = 'Помилка при оновленні продукту.';
    }
}

//обробляє видалення продукту
function handleDeleteProduct(PDO $pdo, array &$result): void {
    $productId = (int)($_POST['product_id'] ?? 0);

    if (deleteProduct($pdo, $productId)) {
        $result['success'] = 'Продукт видалено з магазину.';
    } else {
        $result['errors'][] = 'Помилка при видаленні продукту.';
    }
}

// функції для роботи з користувачами
//отримує всіх користувачів з бази даних
function getAllUsers(PDO $pdo): array {
    $sql = "SELECT id, name, lastname, email, phone, role FROM users ORDER BY id";
    $stmt = $pdo->prepare($sql);
    $stmt->execute();

    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

//оновлює роль користувача
function updateUserRole(PDO $pdo, int $userId, string $role): bool {
    $sql = "UPDATE users SET role = :role WHERE id = :id";
    $stmt = $pdo->prepare($sql);

    return $stmt->execute([
        ':role' => $role,
        ':id' => $userId
    ]);
}

//видаляє користувача з бази даних
function deleteUser(PDO $pdo, int $userId): bool {
    $sql = "DELETE FROM users WHERE id = :id";
    $stmt = $pdo->prepare($sql);

    return $stmt->execute([':id' => $userId]);
}

// функції для роботи з товарами
//отримує всі товари для адміністратора
function getAllProductsAdmin(PDO $pdo): array {
    $sql = "SELECT 
                p.id,
                p.category,
                p.flower_name,
                p.description,
                p.long_description,
                p.price,
                p.is_active,
                p.discount_id,
                p.image,
                d.percentage AS discount_percentage,
                COALESCE((SELECT SUM(quantity) FROM inventory WHERE product_id = p.id AND location_type = 'STORE'), 0) AS stock,
                STRING_AGG(DISTINCT s.name, ', ') AS stores
            FROM products p
            LEFT JOIN discounts d ON p.discount_id = d.id
            LEFT JOIN inventory i ON p.id = i.product_id AND i.location_type = 'STORE'
            LEFT JOIN stores s ON i.location_id = s.id
            WHERE p.is_active = TRUE
            AND EXISTS (SELECT 1 FROM inventory WHERE product_id = p.id AND location_type = 'STORE')
            GROUP BY 
                p.id, p.category, p.flower_name, p.description, p.long_description, 
                p.price, p.is_active, p.discount_id, p.image, d.percentage
            ORDER BY p.id";

    $stmt = $pdo->prepare($sql);
    $stmt->execute();
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    return array_filter($rows, function ($row) {
        return intval($row['stock']) >= 0;
    });
}

//отримує всі знижки з бази даних
function getAllDiscounts(PDO $pdo): array {
    $sql = "SELECT id, name, percentage, is_active FROM discounts ORDER BY id";
    $stmt = $pdo->prepare($sql);
    $stmt->execute();

    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

//додає новий товар до бази даних
function addProduct(
    PDO $pdo,
    string $category,
    string $flowerName,
    string $description,
    string $longDescription,
    float $price,
    int $stock,
    ?int $discountId,
    string $image = '',
    int $storeId = 1
): bool {
    $sql = "INSERT INTO products 
            (category, flower_name, description, long_description, price, discount_id, image) 
            VALUES (:category, :flower_name, :description, :long_description, :price, :discount_id, :image) 
            RETURNING id";

    $stmt = $pdo->prepare($sql);
    $success = $stmt->execute([
        ':category' => $category,
        ':flower_name' => $flowerName,
        ':description' => $description,
        ':long_description' => $longDescription !== '' ? $longDescription : null,
        ':price' => $price,
        ':discount_id' => $discountId,
        ':image' => $image !== '' ? $image : null
    ]);

    if (!$success) {
        return false;
    }

    $productId = $stmt->fetchColumn();

    // додаємо товар до inventory для конкретного магазину
    if ($stock > 0) {
        $invSql = "INSERT INTO inventory (product_id, location_type, location_id, quantity) 
                   VALUES (:product_id, 'STORE', :store_id, :quantity)";
        $invStmt = $pdo->prepare($invSql);
        $invStmt->execute([
            ':product_id' => $productId,
            ':store_id' => $storeId,
            ':quantity' => $stock
        ]);
    }

    return true;
}

//оновлює інформацію про товар
function updateProduct(
    PDO $pdo,
    int $id,
    string $category,
    string $flowerName,
    string $description,
    string $longDescription,
    float $price,
    int $stock,
    ?int $discountId,
    bool $isActive,
    string $image = ''
): bool {
    $sql = "UPDATE products 
            SET category = :category, 
                flower_name = :flower_name, 
                description = :description, 
                long_description = :long_description, 
                price = :price, 
                discount_id = :discount_id, 
                is_active = :is_active, 
                image = :image 
            WHERE id = :id";

    $stmt = $pdo->prepare($sql);
    $stmt->bindValue(':category', $category, PDO::PARAM_STR);
    $stmt->bindValue(':flower_name', $flowerName, PDO::PARAM_STR);
    $stmt->bindValue(':description', $description, PDO::PARAM_STR);
    $stmt->bindValue(
        ':long_description',
        $longDescription !== '' ? $longDescription : null,
        $longDescription !== '' ? PDO::PARAM_STR : PDO::PARAM_NULL
    );
    $stmt->bindValue(':price', $price, PDO::PARAM_STR);
    $stmt->bindValue(
        ':discount_id',
        $discountId,
        $discountId === null ? PDO::PARAM_NULL : PDO::PARAM_INT
    );
    $stmt->bindValue(':is_active', $isActive, PDO::PARAM_BOOL);
    $stmt->bindValue(
        ':image',
        $image !== '' ? $image : null,
        $image !== '' ? PDO::PARAM_STR : PDO::PARAM_NULL
    );
    $stmt->bindValue(':id', $id, PDO::PARAM_INT);
    $success = $stmt->execute();

    if (!$success) {
        return false;
    }

    // оновлюємо stock у inventory для STORE
    $checkSql = "SELECT id, quantity FROM inventory 
                 WHERE product_id = :id AND location_type = 'STORE' LIMIT 1";
    $checkStmt = $pdo->prepare($checkSql);
    $checkStmt->execute([':id' => $id]);
    $inv = $checkStmt->fetch(PDO::FETCH_ASSOC);

    if ($inv) {
        // оновлюємо існуючий запис
        $updateSql = "UPDATE inventory SET quantity = :quantity WHERE id = :inv_id";
        $updateStmt = $pdo->prepare($updateSql);
        $updateStmt->execute([
            ':quantity' => $stock,
            ':inv_id' => $inv['id']
        ]);
    } elseif ($stock > 0) {
        // створюємо новий запис, якщо його не було
        $insertSql = "INSERT INTO inventory (product_id, location_type, location_id, quantity) 
                      VALUES (:product_id, 'STORE', 1, :quantity)";
        $insertStmt = $pdo->prepare($insertSql);
        $insertStmt->execute([
            ':product_id' => $id,
            ':quantity' => $stock
        ]);
    }

    return true;
}

//видаляє товар з бази даних якщо товар тільки у Store - видаляється повністю якщо товар у Store та у Greenhouse - видаляється тільки з Store inventory
function deleteProduct(PDO $pdo, int $id): bool {
    try {
        $pdo->beginTransaction();

        // перевіряємо, чи є товар у GREENHOUSE
        $checkSql = "SELECT COUNT(*) as count 
                     FROM inventory 
                     WHERE product_id = :id 
                     AND location_type = 'GREENHOUSE' 
                     AND quantity > 0";
        $checkStmt = $pdo->prepare($checkSql);
        $checkStmt->execute([':id' => $id]);
        $result = $checkStmt->fetch(PDO::FETCH_ASSOC);
        $hasInGreenhouse = intval($result['count']) > 0;

        if ($hasInGreenhouse) {
            // якщо є у GREENHOUSE - видаляємо тільки з STORE
            $deleteStmt = $pdo->prepare("DELETE FROM inventory WHERE product_id = :id AND location_type = 'STORE'");
            $deleteStmt->execute([':id' => $id]);
        } else {
            // якщо немає у GREENHOUSE - видаляємо товар повністю
            $deleteInventory = $pdo->prepare("DELETE FROM inventory WHERE product_id = :id");
            $deleteInventory->execute([':id' => $id]);

            $deleteProductStmt = $pdo->prepare("DELETE FROM products WHERE id = :id");
            $deleteProductStmt->execute([':id' => $id]);
        }

        $pdo->commit();
        return true;
    } catch (Exception $e) {
        $pdo->rollBack();
        error_log("Error in deleteProduct: " . $e->getMessage());
        return false;
    }
}

// функції для роботи з теплицями
//отримує всі теплиці з інформацією про постачальника
function getAllGreenhouses(PDO $pdo): array {
    $sql = "SELECT 
                g.id, g.name, g.address, g.phone, g.is_active, 
                u.name as supplier_name, u.lastname as supplier_lastname
            FROM greenhouses g
            LEFT JOIN users u ON g.supplier_id = u.id
            WHERE g.is_active = TRUE
            ORDER BY g.name";

    $stmt = $pdo->prepare($sql);
    $stmt->execute();

    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

//Отримує товари в теплиці за її ID
function getGreenhouseInventoryByLocation(PDO $pdo, int $greenhouseId): array {
    $sql = "SELECT 
                i.id as inventory_id, 
                i.product_id, 
                p.flower_name, 
                p.category, 
                p.price, 
                i.quantity
            FROM inventory i
            JOIN products p ON i.product_id = p.id
            WHERE i.location_type = 'GREENHOUSE' 
            AND i.location_id = :location_id 
            AND i.quantity > 0
            ORDER BY p.flower_name";

    $stmt = $pdo->prepare($sql);
    $stmt->execute([':location_id' => $greenhouseId]);

    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

//Створює замовлення товарів для теплиці
//Товари будуть додані в STORE тільки після доставки (статус DELIVERED)
function createGreenhouseOrder(PDO $pdo, int $adminId, int $greenhouseId, int $storeId, array $items): ?int {
    if (empty($items)) {
        throw new Exception("Не вибрано товарів для замовлення.");
    }

    try {
        $pdo->beginTransaction();

        // перевіряємо, чи існує теплиця
        $ghStmt = $pdo->prepare("SELECT id FROM greenhouses WHERE id = :id LIMIT 1");
        $ghStmt->execute([':id' => $greenhouseId]);
        if (!$ghStmt->fetch()) {
            throw new Exception("Обрана теплиця не існує.");
        }

        // перевіряємо, чи існує магазин
        $storeStmt = $pdo->prepare("SELECT id FROM stores WHERE id = :id AND is_active = true LIMIT 1");
        $storeStmt->execute([':id' => $storeId]);
        if (!$storeStmt->fetch()) {
            throw new Exception("Обраний магазин не існує або неактивний.");
        }

        // отримуємо ціни товарів
        $productIds = array_column($items, 'product_id');
        $placeholders = implode(',', array_fill(0, count($productIds), '?'));
        $sql = "SELECT id, price FROM products WHERE id IN ($placeholders)";
        $stmt = $pdo->prepare($sql);
        $stmt->execute($productIds);
        $products = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);

        // перевіряємо остатки товарів в теплиці
        foreach ($items as $item) {
            $productId = (int)$item['product_id'];
            $qty = (int)$item['quantity'];

            if ($qty <= 0) {
                continue;
            }

            if (!isset($products[$productId])) {
                throw new Exception("Товар з ID {$productId} не існує.");
            }

            // перевіряємо наявність товару в теплиці
            $invStmt = $pdo->prepare("SELECT quantity FROM inventory 
                                      WHERE product_id = :product_id 
                                      AND location_type = 'GREENHOUSE' 
                                      AND location_id = :gh_id");
            $invStmt->execute([
                ':product_id' => $productId,
                ':gh_id' => $greenhouseId
            ]);
            $inventory = $invStmt->fetch(PDO::FETCH_ASSOC);

            if (!$inventory || $inventory['quantity'] < $qty) {
                $available = $inventory['quantity'] ?? 0;
                throw new Exception("Недостатньо товару у теплиці. Доступно: $available шт.");
            }
        }

        // створюємо замовлення
        $orderSql = "INSERT INTO greenhouse_orders (greenhouse_id, admin_id, store_id) 
                     VALUES (:gh_id, :admin_id, :store_id) 
                     RETURNING id";
        $orderStmt = $pdo->prepare($orderSql);
        $orderStmt->execute([
            ':gh_id' => $greenhouseId,
            ':admin_id' => $adminId,
            ':store_id' => $storeId
        ]);
        $orderId = $orderStmt->fetchColumn();

        if (!$orderId) {
            throw new Exception("Failed to create greenhouse order");
        }

        // додаємо items до замовлення
        $itemSql = "INSERT INTO greenhouse_order_items 
                    (greenhouse_order_id, product_id, quantity, price_per_unit) 
                    VALUES (:order_id, :product_id, :qty, :price)";
        $itemStmt = $pdo->prepare($itemSql);

        foreach ($items as $item) {
            $productId = (int)$item['product_id'];
            $qty = (int)$item['quantity'];

            if ($qty <= 0 || !isset($products[$productId])) {
                continue;
            }

            $itemStmt->execute([
                ':order_id' => $orderId,
                ':product_id' => $productId,
                ':qty' => $qty,
                ':price' => $products[$productId]
            ]);
        }

        $pdo->commit();
        return $orderId;
    } catch (Exception $e) {
        $pdo->rollBack();
        error_log("Error in createGreenhouseOrder: " . $e->getMessage());
        throw $e;
    }
}

//отримує всі замовлення теплиці, створені адміністратором
function getAdminGreenhouseOrders(PDO $pdo, int $adminId): array {
    $sql = "SELECT 
                go.id, go.status, go.created_at, go.confirmed_at, go.delivered_at,
                g.id as greenhouse_id, g.name as greenhouse_name, 
                g.address as greenhouse_address, g.phone as greenhouse_phone,
                s.id as store_id, s.name as store_name, s.address as store_address,
                u.name as supplier_name, u.lastname as supplier_lastname, u.phone as supplier_phone
            FROM greenhouse_orders go
            LEFT JOIN greenhouses g ON go.greenhouse_id = g.id
            LEFT JOIN stores s ON go.store_id = s.id
            LEFT JOIN users u ON g.supplier_id = u.id
            WHERE go.admin_id = :admin_id
            ORDER BY go.created_at DESC";

    $stmt = $pdo->prepare($sql);
    $stmt->execute([':admin_id' => $adminId]);
    $orders = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // отримуємо items для кожного замовлення
    foreach ($orders as &$order) {
        $itemSql = "SELECT 
                        goi.id, goi.product_id, goi.quantity, 
                        goi.quantity_delivered, goi.price_per_unit, p.flower_name
                    FROM greenhouse_order_items goi
                    JOIN products p ON goi.product_id = p.id
                    WHERE goi.greenhouse_order_id = :order_id";
        $itemStmt = $pdo->prepare($itemSql);
        $itemStmt->execute([':order_id' => $order['id']]);
        $order['items'] = $itemStmt->fetchAll(PDO::FETCH_ASSOC);
    }

    return $orders;
}

//отримує всі теплиці з інформацією про постачальника (включно деактивовані)
function getAllGreenhousesWithSuppliers(PDO $pdo): array {
    $sql = "SELECT 
                g.id, g.name, g.address, g.phone, g.is_active, g.supplier_id,
                u.id as supplier_id, u.name as supplier_name, u.lastname as supplier_lastname
            FROM greenhouses g
            LEFT JOIN users u ON g.supplier_id = u.id
            ORDER BY g.name";

    $stmt = $pdo->prepare($sql);
    $stmt->execute();

    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

//прив'язує постачальника до теплиці
function assignSupplierToGreenhouse(PDO $pdo, int $greenhouseId, ?int $supplierId): bool {
    $sql = "UPDATE greenhouses SET supplier_id = :supplier_id WHERE id = :greenhouse_id";
    $stmt = $pdo->prepare($sql);

    return $stmt->execute([
        ':supplier_id' => $supplierId,
        ':greenhouse_id' => $greenhouseId
    ]);
}

//додає нову теплицю до бази даних
function addGreenhouse(PDO $pdo, string $name, string $address, string $phone): bool {
    // отримуємо першого доступного постачальника
    $suppliersStmt = $pdo->prepare("SELECT id FROM users WHERE role = 'SUPPLIER' LIMIT 1");
    $suppliersStmt->execute();
    $supplier = $suppliersStmt->fetch(PDO::FETCH_ASSOC);
    $supplierId = $supplier ? (int)$supplier['id'] : 1;

    $sql = "INSERT INTO greenhouses (name, address, phone, is_active, supplier_id) 
            VALUES (:name, :address, :phone, true, :supplier_id)";
    $stmt = $pdo->prepare($sql);

    return $stmt->execute([
        ':name' => $name,
        ':address' => $address,
        ':phone' => $phone,
        ':supplier_id' => $supplierId
    ]);
}

//видаляє теплицю повністю
function deleteGreenhouse(PDO $pdo, int $greenhouseId): bool {
    try {
        $pdo->beginTransaction();

        // видаляємо товари з inventory для цієї теплиці
        $pdo->prepare("DELETE FROM inventory WHERE location_type = 'GREENHOUSE' AND location_id = :id")
            ->execute([':id' => $greenhouseId]);

        // видаляємо саму теплицю
        $pdo->prepare("DELETE FROM greenhouses WHERE id = :id")
            ->execute([':id' => $greenhouseId]);

        $pdo->commit();
        return true;
    } catch (Exception $e) {
        $pdo->rollBack();
        error_log("Error in deleteGreenhouse: " . $e->getMessage());
        return false;
    }
}

//деактивує теплицю (is_active = FALSE)
function deactivateGreenhouse(PDO $pdo, int $greenhouseId): bool {
    $sql = "UPDATE greenhouses SET is_active = FALSE WHERE id = :id";
    $stmt = $pdo->prepare($sql);

    return $stmt->execute([':id' => $greenhouseId]);
}

//активує теплицю (is_active = TRUE)
function activateGreenhouse(PDO $pdo, int $greenhouseId): bool {
    $sql = "UPDATE greenhouses SET is_active = TRUE WHERE id = :id";
    $stmt = $pdo->prepare($sql);

    return $stmt->execute([':id' => $greenhouseId]);
}

// функції для роботи з магазинами
//отримує всіх постачальників (користувачів з роллю SUPPLIER)
function getAllSuppliers(PDO $pdo): array {
    $sql = "SELECT id, name, lastname, email, phone 
            FROM users 
            WHERE role = 'SUPPLIER' 
            ORDER BY lastname, name";

    $stmt = $pdo->prepare($sql);
    $stmt->execute();

    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

//додає новий магазин до бази даних
function addStore(PDO $pdo, string $name, string $address, string $phone): bool {
    $sql = "INSERT INTO stores (name, address, phone, is_active) 
            VALUES (:name, :address, :phone, true)";
    $stmt = $pdo->prepare($sql);

    return $stmt->execute([
        ':name' => $name,
        ':address' => $address,
        ':phone' => $phone
    ]);
}

//отримує всі магазини (включно деактивовані)
function getAllStores(PDO $pdo): array {
    $sql = "SELECT id, name, address, phone, is_active FROM stores ORDER BY name";
    $stmt = $pdo->prepare($sql);
    $stmt->execute();

    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

//видаляє магазин повністю
function deleteStore(PDO $pdo, int $storeId): bool {
    try {
        $pdo->beginTransaction();

        // видаляємо товари з inventory для цього магазину
        $pdo->prepare("DELETE FROM inventory WHERE location_type = 'STORE' AND location_id = :id")
            ->execute([':id' => $storeId]);

        // видаляємо сам магазин
        $pdo->prepare("DELETE FROM stores WHERE id = :id")
            ->execute([':id' => $storeId]);

        $pdo->commit();
        return true;
    } catch (Exception $e) {
        $pdo->rollBack();
        error_log("Error in deleteStore: " . $e->getMessage());
        return false;
    }
}

//деактивує магазин (is_active = FALSE)
function deactivateStore(PDO $pdo, int $storeId): bool {
    $sql = "UPDATE stores SET is_active = FALSE WHERE id = :id";
    $stmt = $pdo->prepare($sql);

    return $stmt->execute([':id' => $storeId]);
}

//активує магазин (is_active = TRUE)
function activateStore(PDO $pdo, int $storeId): bool {
    $sql = "UPDATE stores SET is_active = TRUE WHERE id = :id";
    $stmt = $pdo->prepare($sql);

    return $stmt->execute([':id' => $storeId]);
}
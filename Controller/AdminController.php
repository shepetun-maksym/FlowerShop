<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../Model/User.php';
require_once __DIR__ . '/../Model/Product.php';

function adminController(): array {
    if (session_status() !== PHP_SESSION_ACTIVE) session_start();
    $result = ['errors' => [], 'success' => false, 'users' => [], 'products' => [], 'discounts' => [], 'greenhouses' => [], 'suppliers' => [], 'stores' => [], 'current_user_id' => null];

    if (empty($_SESSION['user_id'])) {
        header('Location: login.php'); exit;
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

        $result['users'] = getAllUsers($pdo);
        $result['products'] = getAllProductsAdmin($pdo);
        $result['greenhouses'] = getAllGreenhousesWithSuppliers($pdo);
        $result['suppliers'] = getAllSuppliers($pdo);
        $result['stores'] = getAllStores($pdo);

        $images = [];
        $imgDir = __DIR__ . '/../Images';
        if (is_dir($imgDir)) {
            $files = scandir($imgDir);
            foreach ($files as $f) {
                if ($f === '.' || $f === '..') continue;
                $ext = strtolower(pathinfo($f, PATHINFO_EXTENSION));
                if (in_array($ext, ['jpg','jpeg','png','gif','webp'])) {
                    $images[] = $f;
                }
            }
        }
        $result['images'] = $images;

        $result['discounts'] = getAllDiscounts($pdo);

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $action = $_POST['action'] ?? '';

            if ($action === 'update_role') {
                $userIdToUpdate = (int)($_POST['user_id'] ?? 0);
                $newRole = $_POST['role'] ?? '';
                if ($userIdToUpdate === $userId) {
                    $result['errors'][] = 'Ви не можете змінити свою роль.';
                } elseif (in_array($newRole, ['CLIENT', 'SUPPLIER', 'MANAGER', 'ADMIN'])) {
                    if (updateUserRole($pdo, $userIdToUpdate, $newRole)) {
                        $result['success'] = 'Роль користувача оновлено.';
                    } else {
                        $result['errors'][] = 'Помилка при оновленні ролі.';
                    }
                } else {
                    $result['errors'][] = 'Неправильна роль.';
                }
            } elseif ($action === 'delete_user') {
                $userIdToDelete = (int)($_POST['user_id'] ?? 0);
                if ($userIdToDelete === $userId) {
                    $result['errors'][] = 'Ви не можете видалити самого себе.';
                } else {
                    if (deleteUser($pdo, $userIdToDelete)) {
                        $result['success'] = 'Користувача видалено.';
                    } else {
                        $result['errors'][] = 'Помилка при видаленні користувача.';
                    }
                }
            } elseif ($action === 'assign_supplier') {
                $greenhouseId = (int)($_POST['greenhouse_id'] ?? 0);
                $supplierId = !empty($_POST['supplier_id']) ? (int)$_POST['supplier_id'] : null;
                if (assignSupplierToGreenhouse($pdo, $greenhouseId, $supplierId)) {
                    $result['success'] = 'Постачальника прив\'язано до теплиці.';
                } else {
                    $result['errors'][] = 'Помилка при прив\'язуванні постачальника.';
                }
            } elseif ($action === 'add_greenhouse') {
                $name = trim($_POST['gh_name'] ?? '');
                $address = trim($_POST['gh_address'] ?? '');
                $phone = trim($_POST['gh_phone'] ?? '');
                if ($name === '') {
                    $result['errors'][] = 'Назва теплиці обов\'язкова.';
                } else {
                    if (addGreenhouse($pdo, $name, $address, $phone)) {
                        $result['success'] = 'Теплицю додано.';
                    } else {
                        $result['errors'][] = 'Помилка при додаванні теплиці.';
                    }
                }
            } elseif ($action === 'add_store') {
                $name = trim($_POST['store_name'] ?? '');
                $address = trim($_POST['store_address'] ?? '');
                $phone = trim($_POST['store_phone'] ?? '');
                if ($name === '') {
                    $result['errors'][] = 'Назва магазину обов\'язкова.';
                } else {
                    if (addStore($pdo, $name, $address, $phone)) {
                        $result['success'] = 'Магазин додано.';
                    } else {
                        $result['errors'][] = 'Помилка при додаванні магазину.';
                    }
                }
            } elseif ($action === 'delete_greenhouse') {
                $greenhouseId = (int)($_POST['greenhouse_id'] ?? 0);
                if (deleteGreenhouse($pdo, $greenhouseId)) {
                    $result['success'] = 'Теплицю повністю видалено.';
                } else {
                    $result['errors'][] = 'Помилка при видаленні теплиці.';
                }
            } elseif ($action === 'deactivate_greenhouse') {
                $greenhouseId = (int)($_POST['greenhouse_id'] ?? 0);
                if (deactivateGreenhouse($pdo, $greenhouseId)) {
                    $result['success'] = 'Теплицю деактивовано.';
                } else {
                    $result['errors'][] = 'Помилка при деактивації теплиці.';
                }
            } elseif ($action === 'activate_greenhouse') {
                $greenhouseId = (int)($_POST['greenhouse_id'] ?? 0);
                if (activateGreenhouse($pdo, $greenhouseId)) {
                    $result['success'] = 'Теплицю активовано.';
                } else {
                    $result['errors'][] = 'Помилка при активації теплиці.';
                }
            } elseif ($action === 'delete_store') {
                $storeId = (int)($_POST['store_id'] ?? 0);
                if (deleteStore($pdo, $storeId)) {
                    $result['success'] = 'Магазин повністю видалено.';
                } else {
                    $result['errors'][] = 'Помилка при видаленні магазину.';
                }
            } elseif ($action === 'deactivate_store') {
                $storeId = (int)($_POST['store_id'] ?? 0);
                if (deactivateStore($pdo, $storeId)) {
                    $result['success'] = 'Магазин деактивовано.';
                } else {
                    $result['errors'][] = 'Помилка при деактивації магазину.';
                }
            } elseif ($action === 'activate_store') {
                $storeId = (int)($_POST['store_id'] ?? 0);
                if (activateStore($pdo, $storeId)) {
                    $result['success'] = 'Магазин активовано.';
                } else {
                    $result['errors'][] = 'Помилка при активації магазину.';
                }
            } elseif ($action === 'add_product') {
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
                } elseif ($storeId === null) {
                    $result['errors'][] = 'Виберіть магазин.';
                } else {
                    if (addProduct($pdo, $category, $flowerName, $description, $longDescription, $price, $stock, $discountId, $image, $storeId)) {
                        $result['success'] = 'Продукт додано.';
                    } else {
                        $result['errors'][] = 'Помилка при додаванні продукту.';
                    }
                }
            } elseif ($action === 'update_product') {
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
                } else {
                    if (updateProduct($pdo, $productId, $category, $flowerName, $description, $longDescription, $price, $stock, $discountId, $isActive, $image)) {
                        $result['success'] = 'Продукт оновлено.';
                    } else {
                        $result['errors'][] = 'Помилка при оновленні продукту.';
                    }
                }
            } elseif ($action === 'delete_product') {
                $productId = (int)($_POST['product_id'] ?? 0);
                if (deleteProduct($pdo, $productId)) {
                    $result['success'] = 'Продукт видалено з магазину.';
                } else {
                    $result['errors'][] = 'Помилка при видаленні продукту.';
                }
            }

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

//додаткові функції для адміністратора
function getAllUsers(PDO $pdo): array {
    $sql = "SELECT id, name, lastname, email, phone, role FROM users ORDER BY id";
    $stmt = $pdo->prepare($sql);
    $stmt->execute();
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function updateUserRole(PDO $pdo, int $userId, string $role): bool {
    $sql = "UPDATE users SET role = :role WHERE id = :id";
    $stmt = $pdo->prepare($sql);
    return $stmt->execute([':role' => $role, ':id' => $userId]);
}

function deleteUser(PDO $pdo, int $userId): bool {
    $sql = "DELETE FROM users WHERE id = :id";
    $stmt = $pdo->prepare($sql);
    return $stmt->execute([':id' => $userId]);
}

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
            GROUP BY p.id, p.category, p.flower_name, p.description, p.long_description, p.price, p.is_active, p.discount_id, p.image, d.percentage
            ORDER BY p.id";
    $stmt = $pdo->prepare($sql);
    $stmt->execute();
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    return array_filter($rows, function($row) {
        return intval($row['stock']) >= 0;
    });
}

function getAllDiscounts(PDO $pdo): array {
    $sql = "SELECT id, name, percentage, is_active FROM discounts ORDER BY id";
    $stmt = $pdo->prepare($sql);
    $stmt->execute();
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function addProduct(PDO $pdo, string $category, string $flowerName, string $description, string $longDescription, float $price, int $stock, ?int $discountId, string $image = '', int $storeId = 1): bool {
    $sql = "INSERT INTO products (category, flower_name, description, long_description, price, discount_id, image) VALUES (:category, :flower_name, :description, :long_description, :price, :discount_id, :image) RETURNING id";
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
    
    if ($success) {
        $productId = $stmt->fetchColumn();
        // додаємо товар до inventory для конкретного магазину
        if ($stock > 0) {
            $invStmt = $pdo->prepare("INSERT INTO inventory (product_id, location_type, location_id, quantity) VALUES (:product_id, 'STORE', :store_id, :quantity)");
            $invStmt->execute([':product_id' => $productId, ':store_id' => $storeId, ':quantity' => $stock]);
        }
        return true;
    }
    return false;
}

function updateProduct(PDO $pdo, int $id, string $category, string $flowerName, string $description, string $longDescription, float $price, int $stock, ?int $discountId, bool $isActive, string $image = ''): bool {
    $sql = "UPDATE products SET category = :category, flower_name = :flower_name, description = :description, long_description = :long_description, price = :price, discount_id = :discount_id, is_active = :is_active, image = :image WHERE id = :id";
    $stmt = $pdo->prepare($sql);
    $stmt->bindValue(':category', $category, PDO::PARAM_STR);
    $stmt->bindValue(':flower_name', $flowerName, PDO::PARAM_STR);
    $stmt->bindValue(':description', $description, PDO::PARAM_STR);
    $stmt->bindValue(':long_description', $longDescription !== '' ? $longDescription : null, $longDescription !== '' ? PDO::PARAM_STR : PDO::PARAM_NULL);
    $stmt->bindValue(':price', $price, PDO::PARAM_STR);
    $stmt->bindValue(':discount_id', $discountId, $discountId === null ? PDO::PARAM_NULL : PDO::PARAM_INT);
    $stmt->bindValue(':is_active', $isActive, PDO::PARAM_BOOL);
    $stmt->bindValue(':image', $image !== '' ? $image : null, $image !== '' ? PDO::PARAM_STR : PDO::PARAM_NULL);
    $stmt->bindValue(':id', $id, PDO::PARAM_INT);
    $success = $stmt->execute();
    
    if ($success) {
        // Оновлюємо stock у inventory для STORE
        // Спочатку отримуємо поточну quantity
        $checkStmt = $pdo->prepare("SELECT id, quantity FROM inventory WHERE product_id = :id AND location_type = 'STORE' LIMIT 1");
        $checkStmt->execute([':id' => $id]);
        $inv = $checkStmt->fetch(PDO::FETCH_ASSOC);
        
        if ($inv) {
            // Оновлюємо існуючий запис
            $updateStmt = $pdo->prepare("UPDATE inventory SET quantity = :quantity WHERE id = :inv_id");
            $updateStmt->execute([':quantity' => $stock, ':inv_id' => $inv['id']]);
        } else if ($stock > 0) {
            // Створюємо новий запис, якщо його не було
            $insertStmt = $pdo->prepare("INSERT INTO inventory (product_id, location_type, location_id, quantity) VALUES (:product_id, 'STORE', 1, :quantity)");
            $insertStmt->execute([':product_id' => $id, ':quantity' => $stock]);
        }
    }
    
    return $success;
}

function deleteProduct(PDO $pdo, int $id): bool {
    // Якщо товар ТІЛЬКИ у Store то видаляємо його повністю з бази але не видаляємо замовлення бо там історія
    // Якщо товар у Store та у Greenhouse то видаляємо тільки з Store inventory
    
    try {
        $pdo->beginTransaction();
        
        // Перевіряємо, чи є товар у GREENHOUSE
        $checkStmt = $pdo->prepare("SELECT COUNT(*) as count FROM inventory WHERE product_id = :id AND location_type = 'GREENHOUSE' AND quantity > 0");
        $checkStmt->execute([':id' => $id]);
        $result = $checkStmt->fetch(PDO::FETCH_ASSOC);
        $hasInGreenhouse = intval($result['count']) > 0;
        
        if ($hasInGreenhouse) {
            // Якщо є у GREENHOUSE то видаляємо тільки з STORE
            $deleteStmt = $pdo->prepare("DELETE FROM inventory WHERE product_id = :id AND location_type = 'STORE'");
            $deleteStmt->execute([':id' => $id]);
        } else {
            // Якщо немає у GREENHOUSE то видаляємо товар повністю але не історію замовлень
            // Видаляємо із inventory STORE та інші локації
            $deleteInventory = $pdo->prepare("DELETE FROM inventory WHERE product_id = :id");
            $deleteInventory->execute([':id' => $id]);
            // Видаляємо сам товар
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

//Отримати всі теплиці з інформацією про постачальника
function getAllGreenhouses(PDO $pdo): array {
    $sql = "SELECT g.id, g.name, g.address, g.phone, g.is_active, u.name as supplier_name, u.lastname as supplier_lastname
            FROM greenhouses g
            LEFT JOIN users u ON g.supplier_id = u.id
            WHERE g.is_active = TRUE
            ORDER BY g.name";
    $stmt = $pdo->prepare($sql);
    $stmt->execute();
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

//Отримати товари в теплиці 
function getGreenhouseInventoryByLocation(PDO $pdo, int $greenhouseId): array {
    $sql = "SELECT i.id as inventory_id, i.product_id, p.flower_name, p.category, p.price, i.quantity
            FROM inventory i
            JOIN products p ON i.product_id = p.id
            WHERE i.location_type = 'GREENHOUSE' AND i.location_id = :location_id AND i.quantity > 0
            ORDER BY p.flower_name";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([':location_id' => $greenhouseId]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

//Створити заказ товарів у теплицю greenhouse_orders і greenhouse_order_items
//Товари будуть додані в STORE тільки після того як постачальник доставить їх статус DELIVERED
//Повертає: orderId при успіху, або null та помилки можна отримати через вийняток
function createGreenhouseOrder(PDO $pdo, int $adminId, int $greenhouseId, int $storeId, array $items): ?int {
    
    if (empty($items)) {
        throw new Exception("Не вибрано товарів для замовлення.");
    }
    try {
        $pdo->beginTransaction();

        // Перевірити що теплиця існує
        $ghStmt = $pdo->prepare("SELECT id FROM greenhouses WHERE id = :id LIMIT 1");
        $ghStmt->execute([':id' => $greenhouseId]);
        if (!$ghStmt->fetch()) {
            throw new Exception("Обрана теплиця не існує.");
        }

        // Перевірити що магазин існує
        $storeStmt = $pdo->prepare("SELECT id FROM stores WHERE id = :id AND is_active = true LIMIT 1");
        $storeStmt->execute([':id' => $storeId]);
        if (!$storeStmt->fetch()) {
            throw new Exception("Обраний магазин не існує або неактивний.");
        }

        // Отримати ціни товарів і перевірити наявність
        $productIds = array_column($items, 'product_id');
        $placeholders = implode(',', array_fill(0, count($productIds), '?'));
        $sql = "SELECT id, price FROM products WHERE id IN ($placeholders)";
        $stmt = $pdo->prepare($sql);
        $stmt->execute($productIds);
        $products = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);

        // Перевірити остатки товарів в теплиці
        foreach ($items as $item) {
            $productId = (int)$item['product_id'];
            $qty = (int)$item['quantity'];
            
            if ($qty <= 0) continue;
            if (!isset($products[$productId])) {
                throw new Exception("Товар з ID {$productId} не існує.");
            }

            // Перевірити наявність товару в теплиці
            $invStmt = $pdo->prepare("SELECT quantity FROM inventory 
                                     WHERE product_id = :product_id 
                                     AND location_type = 'GREENHOUSE' 
                                     AND location_id = :gh_id");
            $invStmt->execute([':product_id' => $productId, ':gh_id' => $greenhouseId]);
            $inventory = $invStmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$inventory || $inventory['quantity'] < $qty) {
                throw new Exception("Недостатньо товару у теплиці. Доступно: " . ($inventory['quantity'] ?? 0) . " шт.");
            }
        }

        // Створити greenhouse_orders запис з store_id
        $orderStmt = $pdo->prepare("INSERT INTO greenhouse_orders (greenhouse_id, admin_id, store_id) VALUES (:gh_id, :admin_id, :store_id) RETURNING id");
        $orderStmt->execute([':gh_id' => $greenhouseId, ':admin_id' => $adminId, ':store_id' => $storeId]);
        $orderId = $orderStmt->fetchColumn();

        if (!$orderId) {
            throw new Exception("Failed to create greenhouse order");
        }

        // Додати items до заказу (НЕ додаємо в STORE - чекаємо доставки)
        $itemStmt = $pdo->prepare("INSERT INTO greenhouse_order_items (greenhouse_order_id, product_id, quantity, price_per_unit) VALUES (:order_id, :product_id, :qty, :price)");
        
        foreach ($items as $item) {
            $productId = (int)$item['product_id'];
            $qty = (int)$item['quantity'];
            
            if ($qty <= 0 || !isset($products[$productId])) {
                continue;
            }

            // Додати item до заказу
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

//Отримати всі замовлення теплиці, створені адміністратором
function getAdminGreenhouseOrders(PDO $pdo, int $adminId): array {
    $sql = "SELECT go.id, go.status, go.created_at, go.confirmed_at, go.delivered_at,
                   g.id as greenhouse_id, g.name as greenhouse_name, g.address as greenhouse_address, g.phone as greenhouse_phone,
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

    // Отримати items для кожного замовлення
    foreach ($orders as &$order) {
        $itemStmt = $pdo->prepare("SELECT goi.id, goi.product_id, goi.quantity, goi.quantity_delivered, goi.price_per_unit, p.flower_name
                                   FROM greenhouse_order_items goi
                                   JOIN products p ON goi.product_id = p.id
                                   WHERE goi.greenhouse_order_id = :order_id");
        $itemStmt->execute([':order_id' => $order['id']]);
        $order['items'] = $itemStmt->fetchAll(PDO::FETCH_ASSOC);
    }

    return $orders;
}

//Отримати всі теплиці з інформацією про поточного постачальника (включно деактивовані)
function getAllGreenhousesWithSuppliers(PDO $pdo): array {
    $sql = "SELECT g.id, g.name, g.address, g.phone, g.is_active, g.supplier_id,
                   u.id as supplier_id, u.name as supplier_name, u.lastname as supplier_lastname
            FROM greenhouses g
            LEFT JOIN users u ON g.supplier_id = u.id
            ORDER BY g.name";
    $stmt = $pdo->prepare($sql);
    $stmt->execute();
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

//Отримати всіх постачальників (користувачів з роллю SUPPLIER)
function getAllSuppliers(PDO $pdo): array {
    $sql = "SELECT id, name, lastname, email, phone FROM users WHERE role = 'SUPPLIER' ORDER BY lastname, name";
    $stmt = $pdo->prepare($sql);
    $stmt->execute();
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

 //Прив'язати постачальника до теплиці
function assignSupplierToGreenhouse(PDO $pdo, int $greenhouseId, ?int $supplierId): bool {
    $sql = "UPDATE greenhouses SET supplier_id = :supplier_id WHERE id = :greenhouse_id";
    $stmt = $pdo->prepare($sql);
    return $stmt->execute([':supplier_id' => $supplierId, ':greenhouse_id' => $greenhouseId]);
}

//Додати нову теплицю
function addGreenhouse(PDO $pdo, string $name, string $address, string $phone): bool {
    $sql = "INSERT INTO greenhouses (name, address, phone, is_active) VALUES (:name, :address, :phone, true)";
    $stmt = $pdo->prepare($sql);
    return $stmt->execute([':name' => $name, ':address' => $address, ':phone' => $phone]);
}

//Додати новий магазин
function addStore(PDO $pdo, string $name, string $address, string $phone): bool {
    $sql = "INSERT INTO stores (name, address, phone, is_active) VALUES (:name, :address, :phone, true)";
    $stmt = $pdo->prepare($sql);
    return $stmt->execute([':name' => $name, ':address' => $address, ':phone' => $phone]);
}

//Отримати всі магазини (включно деактивовані)
function getAllStores(PDO $pdo): array {
    $sql = "SELECT id, name, address, phone, is_active FROM stores ORDER BY name";
    $stmt = $pdo->prepare($sql);
    $stmt->execute();
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

//Видалити теплицю (повне видалення)
function deleteGreenhouse(PDO $pdo, int $greenhouseId): bool {
    try {
        $pdo->beginTransaction();
        
        // Видаляємо товари з inventory для цієї теплиці
        $pdo->prepare("DELETE FROM inventory WHERE location_type = 'GREENHOUSE' AND location_id = :id")
            ->execute([':id' => $greenhouseId]);
        
        // Видаляємо саму теплицю
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

//Деактивувати теплицю (is_active = FALSE)
function deactivateGreenhouse(PDO $pdo, int $greenhouseId): bool {
    $sql = "UPDATE greenhouses SET is_active = FALSE WHERE id = :id";
    $stmt = $pdo->prepare($sql);
    return $stmt->execute([':id' => $greenhouseId]);
}

//Активувати теплицю (is_active = TRUE)
function activateGreenhouse(PDO $pdo, int $greenhouseId): bool {
    $sql = "UPDATE greenhouses SET is_active = TRUE WHERE id = :id";
    $stmt = $pdo->prepare($sql);
    return $stmt->execute([':id' => $greenhouseId]);
}

//Видалити магазин (повне видалення)
function deleteStore(PDO $pdo, int $storeId): bool {
    try {
        $pdo->beginTransaction();
        
        // Видаляємо товари з inventory для цього магазину
        $pdo->prepare("DELETE FROM inventory WHERE location_type = 'STORE' AND location_id = :id")
            ->execute([':id' => $storeId]);
        
        // Видаляємо сам магазин
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

//Деактивувати магазин (is_active = FALSE)
function deactivateStore(PDO $pdo, int $storeId): bool {
    $sql = "UPDATE stores SET is_active = FALSE WHERE id = :id";
    $stmt = $pdo->prepare($sql);
    return $stmt->execute([':id' => $storeId]);
}

//Активувати магазин (is_active = TRUE)
function activateStore(PDO $pdo, int $storeId): bool {
    $sql = "UPDATE stores SET is_active = TRUE WHERE id = :id";
    $stmt = $pdo->prepare($sql);
    return $stmt->execute([':id' => $storeId]);
}
?>
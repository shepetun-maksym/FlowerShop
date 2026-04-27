<?php
require_once __DIR__ . "/../config/db.php";
$pdo = getPDO();

$date_from = $_GET['date_from'] ?? '';
$date_to = $_GET['date_to'] ?? '';
$product_id = $_GET['product_id'] ?? '';

$products = $pdo->query("SELECT id, flower_name FROM products WHERE is_active = true")->fetchAll();

$query = "
SELECT 
    p.flower_name,
    SUM(oi.quantity) as total_quantity,
    SUM(oi.quantity * oi.price_at_purchase) as total_sum
FROM order_items oi
JOIN orders o ON oi.order_id = o.id
LEFT JOIN products p ON oi.product_id = p.id
WHERE 1=1
";

$params = [];

if ($date_from) {
    $query .= " AND o.created_at >= :date_from";
    $params[':date_from'] = $date_from;
}

if ($date_to) {
    $query .= " AND o.created_at <= :date_to";
    $params[':date_to'] = $date_to;
}

if ($product_id) {
    $query .= " AND p.id = :product_id";
    $params[':product_id'] = $product_id;
}

$query .= " GROUP BY p.flower_name ORDER BY total_quantity DESC";

$stmt = $pdo->prepare($query);
$stmt->execute($params);
$stats = $stmt->fetchAll();

$query2 = "
SELECT 
    DATE(o.created_at) as date,
    SUM(oi.quantity * oi.price_at_purchase) as total
FROM order_items oi
JOIN orders o ON oi.order_id = o.id
WHERE 1=1
";

$params2 = [];

if ($date_from) {
    $query2 .= " AND o.created_at >= :date_from";
    $params2[':date_from'] = $date_from;
}

if ($date_to) {
    $query2 .= " AND o.created_at <= :date_to";
    $params2[':date_to'] = $date_to;
}

$query2 .= " GROUP BY DATE(o.created_at) ORDER BY date";

$stmt2 = $pdo->prepare($query2);
$stmt2->execute($params2);
$sales = $stmt2->fetchAll();

// Витрати по дням (закупки у теплиці)
$query3 = "
SELECT 
    DATE(go.created_at) as date,
    SUM(goi.quantity * goi.price_per_unit) as total
FROM greenhouse_order_items goi
JOIN greenhouse_orders go ON goi.greenhouse_order_id = go.id
WHERE go.status = 'DELIVERED'
";

$params3 = [];

if ($date_from) {
    $query3 .= " AND go.created_at >= :date_from";
    $params3[':date_from'] = $date_from;
}

if ($date_to) {
    $query3 .= " AND go.created_at <= :date_to";
    $params3[':date_to'] = $date_to;
}

$query3 .= " GROUP BY DATE(go.created_at) ORDER BY date";

$stmt3 = $pdo->prepare($query3);
$stmt3->execute($params3);
$expenses = $stmt3->fetchAll();

// Об'єднати доходи та витрати по дням
$dailyData = [];
foreach ($sales as $sale) {
    $dailyData[$sale['date']] = ['date' => $sale['date'], 'income' => (float)$sale['total'], 'expense' => 0];
}
foreach ($expenses as $expense) {
    if (!isset($dailyData[$expense['date']])) {
        $dailyData[$expense['date']] = ['date' => $expense['date'], 'income' => 0, 'expense' => 0];
    }
    $dailyData[$expense['date']]['expense'] = (float)$expense['total'];
}
ksort($dailyData);
$dailyData = array_values($dailyData);

$topClients = $pdo->query("
SELECT 
    u.name,
    u.lastname,
    COUNT(o.id) as orders_count
FROM users u
JOIN orders o ON u.id = o.user_id
GROUP BY u.id
ORDER BY orders_count DESC
LIMIT 5
")->fetchAll();

// Доходи від продажів клієнтам
$incomeQuery = "
SELECT COALESCE(SUM(oi.quantity * oi.price_at_purchase), 0) as total_income
FROM order_items oi
JOIN orders o ON oi.order_id = o.id
WHERE o.status = 'COMPLETED'
";
$incomeParams = [];
if ($date_from) {
    $incomeQuery .= " AND o.created_at >= :date_from";
    $incomeParams[':date_from'] = $date_from;
}
if ($date_to) {
    $incomeQuery .= " AND o.created_at <= :date_to";
    $incomeParams[':date_to'] = $date_to;
}
$incomeStmt = $pdo->prepare($incomeQuery);
$incomeStmt->execute($incomeParams);
$incomeData = $incomeStmt->fetch();
$totalIncome = (float)$incomeData['total_income'];

// Витрати на закупівлі у постачальників
$expenseQuery = "
SELECT COALESCE(SUM(goi.quantity * goi.price_per_unit), 0) as total_expense
FROM greenhouse_order_items goi
JOIN greenhouse_orders go ON goi.greenhouse_order_id = go.id
WHERE go.status = 'DELIVERED'
";
$expenseParams = [];
if ($date_from) {
    $expenseQuery .= " AND go.created_at >= :date_from";
    $expenseParams[':date_from'] = $date_from;
}
if ($date_to) {
    $expenseQuery .= " AND go.created_at <= :date_to";
    $expenseParams[':date_to'] = $date_to;
}
$expenseStmt = $pdo->prepare($expenseQuery);
$expenseStmt->execute($expenseParams);
$expenseData = $expenseStmt->fetch();
$totalExpense = (float)$expenseData['total_expense'];

$profit = $totalIncome - $totalExpense;

// Кількість замовлень від клієнтів
$ordersCountQuery = "
SELECT COUNT(DISTINCT o.id) as orders_count
FROM orders o
WHERE o.status = 'COMPLETED'
";
$ordersCountParams = [];
if ($date_from) {
    $ordersCountQuery .= " AND o.created_at >= :date_from";
    $ordersCountParams[':date_from'] = $date_from;
}
if ($date_to) {
    $ordersCountQuery .= " AND o.created_at <= :date_to";
    $ordersCountParams[':date_to'] = $date_to;
}
$ordersCountStmt = $pdo->prepare($ordersCountQuery);
$ordersCountStmt->execute($ordersCountParams);
$ordersCountData = $ordersCountStmt->fetch();
$ordersCount = (int)$ordersCountData['orders_count'];

// Кількість закупівель у постачальників
$purchaseCountQuery = "
SELECT COUNT(DISTINCT go.id) as purchases_count
FROM greenhouse_orders go
WHERE go.status = 'DELIVERED'
";
$purchaseCountParams = [];
if ($date_from) {
    $purchaseCountQuery .= " AND go.created_at >= :date_from";
    $purchaseCountParams[':date_from'] = $date_from;
}
if ($date_to) {
    $purchaseCountQuery .= " AND go.created_at <= :date_to";
    $purchaseCountParams[':date_to'] = $date_to;
}
$purchaseCountStmt = $pdo->prepare($purchaseCountQuery);
$purchaseCountStmt->execute($purchaseCountParams);
$purchaseCountData = $purchaseCountStmt->fetch();
$purchasesCount = (int)$purchaseCountData['purchases_count'];
?>
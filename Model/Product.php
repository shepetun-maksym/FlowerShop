<?php

function getCategoryLabel(string $category): string {
    $labels = [
        'SEED' => 'Саджанець',
        'YOUNG' => 'Молода',
        'BLOOMING' => 'Квітучий'
    ];
    return $labels[$category] ?? htmlspecialchars($category);
}

function getAllFlowers(PDO $pdo, int $limit = 100): array
{
    $sql = "SELECT 
                p.id,
                p.flower_name AS name,
                p.description,
                p.long_description,
                p.category,
                p.price,
                p.image,
                d.percentage AS discount_pct,
                d.name AS discount_name,
                COALESCE((SELECT SUM(quantity) FROM inventory WHERE product_id = p.id AND location_type = 'STORE'), 0) AS total_stock
            FROM products p
            LEFT JOIN discounts d ON p.discount_id = d.id AND d.is_active = TRUE
            WHERE p.is_active = TRUE
            AND EXISTS (SELECT 1 FROM inventory i WHERE i.product_id = p.id AND i.location_type = 'STORE')
            ORDER BY p.id DESC
            LIMIT :limit";

    $stmt = $pdo->prepare($sql);
    $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
    $stmt->execute();
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $out = [];
    foreach ($rows as $r) {
        $totalStock = isset($r['total_stock']) ? (int)$r['total_stock'] : 0;

        $base = isset($r['price']) ? (float)$r['price'] : 0.0;
        $pct = isset($r['discount_pct']) && $r['discount_pct'] !== null ? (int)$r['discount_pct'] : 0;

        if ($pct > 0) {
            $price_current = round($base * (1 - $pct / 100), 2);
            $price_old = round($base, 2);
        } else {
            $price_current = round($base, 2);
            $price_old = null;
        }

        $out[] = [
            'id' => $r['id'],
            'name' => $r['name'],
            'description' => $r['description'],
            'long_description' => $r['long_description'] ?? null,
            'category' => $r['category'] ?? null,
            'price_current' => $price_current,
            'price_old' => $price_old,
            'discount' => $pct,
            'discount_name' => $r['discount_name'] ?? null,
            'stock' => $totalStock,
            'image' => $r['image'] ?? null
        ];
    }

    return $out;
}


function getProductById(PDO $pdo, int $id): ?array
{
    $sql = "SELECT 
                p.id,
                p.flower_name AS name,
                p.description,
                p.long_description,
                p.category,
                p.price,
                p.image,
                d.percentage AS discount_pct,
                d.name AS discount_name,
                COALESCE((SELECT SUM(quantity) FROM inventory WHERE product_id = p.id AND location_type = 'STORE'), 0) AS total_stock
            FROM products p
            LEFT JOIN discounts d ON p.discount_id = d.id AND d.is_active = TRUE
            WHERE p.is_active = TRUE AND p.id = :id
            LIMIT 1";

    $stmt = $pdo->prepare($sql);
    $stmt->bindValue(':id', $id, PDO::PARAM_INT);
    $stmt->execute();
    $r = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$r) return null;

    $totalStock = isset($r['total_stock']) ? (int)$r['total_stock'] : 0;

    $base = isset($r['price']) ? (float)$r['price'] : 0.0;
    $pct = isset($r['discount_pct']) && $r['discount_pct'] !== null ? (int)$r['discount_pct'] : 0;

    if ($pct > 0) {
        $price_current = round($base * (1 - $pct / 100), 2);
        $price_old = round($base, 2);
    } else {
        $price_current = round($base, 2);
        $price_old = null;
    }

    return [
        'id' => $r['id'],
        'name' => $r['name'],
        'description' => $r['description'],
        'long_description' => $r['long_description'] ?? null,
        'category' => $r['category'] ?? null,
        'price_current' => $price_current,
        'price_old' => $price_old,
        'discount' => $pct,
        'discount_name' => $r['discount_name'] ?? null,
        'stock' => $totalStock,
        'image' => $r['image'] ?? null
    ];
}

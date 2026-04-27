<?php
// підготовка даних про товар для сторінок перегляду (списку та окремого товару)
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../Model/Product.php';

function loadProductList(int $limit = 32): array
{
    try {
        $pdo = getPDO();
        return getAllFlowers($pdo, $limit);
    } catch (Exception $e) {
        error_log('loadProductList error: ' . $e->getMessage());
        return [];
    }
}

function loadProductById(int $id): ?array
{
    try {
        $pdo = getPDO();
        return getProductById($pdo, $id);
    } catch (Exception $e) {
        return null;
    }
}

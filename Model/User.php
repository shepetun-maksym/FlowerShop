<?php
function ensurePasswordColumn(PDO $pdo) {
    $pdo->exec("ALTER TABLE users ADD COLUMN IF NOT EXISTS password_hash TEXT");
}

function createUser(PDO $pdo, string $name, string $lastname, string $email, ?string $phone, string $passwordHash) {
    ensurePasswordColumn($pdo);
    $sql = "INSERT INTO users (name, lastname, email, phone, password_hash) VALUES (:name,:lastname,:email,:phone,:ph) RETURNING id";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([':name'=>$name, ':lastname'=>$lastname, ':email'=>$email, ':phone'=>$phone, ':ph'=>$passwordHash]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    return $row ? (int)$row['id'] : null;
}

function findUserByEmail(PDO $pdo, string $email): ?array {
    $sql = "SELECT * FROM users WHERE email = :email LIMIT 1";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([':email'=>$email]);
    $r = $stmt->fetch(PDO::FETCH_ASSOC);
    return $r ?: null;
}

function findUserById(PDO $pdo, int $id): ?array {
    $sql = "SELECT * FROM users WHERE id = :id LIMIT 1";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([':id'=>$id]);
    $r = $stmt->fetch(PDO::FETCH_ASSOC);
    return $r ?: null;
}

function emailBelongsToOtherUser(PDO $pdo, string $email, int $userId): bool {
    $sql = "SELECT id FROM users WHERE email = :email LIMIT 1";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([':email' => $email]);
    $r = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$r) return false;
    return ((int)$r['id'] !== $userId);
}

function updateUser(PDO $pdo, int $id, string $name, string $lastname, string $email, ?string $phone, ?string $passwordHash = null): bool {
    ensurePasswordColumn($pdo);
    // динамічно формувати SQLзапит щоб уникнути перезапису пароля
    $fields = ['name' => $name, 'lastname' => $lastname, 'email' => $email, 'phone' => $phone];
    $sqlParts = [];
    foreach (['name','lastname','email','phone'] as $f) {
        $sqlParts[] = "$f = :$f";
    }
    if ($passwordHash !== null) {
        $sqlParts[] = "password_hash = :password_hash";
        $fields['password_hash'] = $passwordHash;
    }
    $sql = "UPDATE users SET " . implode(', ', $sqlParts) . " WHERE id = :id";
    $fields['id'] = $id;
    $stmt = $pdo->prepare($sql);
    return $stmt->execute($fields);
}

// Отримати усіх клієнтів
function getAllClients(PDO $pdo): array {
    $sql = "SELECT id, name, lastname, email, phone FROM users WHERE role = 'CLIENT' ORDER BY lastname, name";
    return $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);
}

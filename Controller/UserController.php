<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../Model/User.php';

function profileController(): array {
    if (session_status() !== PHP_SESSION_ACTIVE) session_start();
    $result = ['errors' => [], 'success' => false, 'user' => null, 'orders' => []];
    if (empty($_SESSION['user_id'])) {
        header('Location: login.php'); exit;
    }
    try {
        $pdo = getPDO();
        $userId = (int)$_SESSION['user_id'];
        $user = findUserById($pdo, $userId);
        if (!$user) {
            $result['errors'][] = 'Користувача не знайдено.';
            return $result;
        }
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            // Скасування замовлення
            if (!empty($_POST['cancel_order_id'])) {
                require_once __DIR__ . '/../Model/Order.php';
                $orderId = (int)$_POST['cancel_order_id'];
                if (cancelUserOrder($pdo, $orderId, $userId)) {
                    $result['success'] = 'Замовлення скасовано.';
                } else {
                    $result['errors'][] = 'Не вдалося скасувати замовлення.';
                }
            } else {
                $name = trim($_POST['name'] ?? '');
                $lastname = trim($_POST['lastname'] ?? '');
                $email = trim($_POST['email'] ?? '');
                $phone = trim($_POST['phone'] ?? '');
                $password = $_POST['password'] ?? '';
                $password_confirm = $_POST['password_confirm'] ?? '';
                $current_password = $_POST['current_password'] ?? '';

                if ($name === '') $result['errors'][] = 'Вкажіть ім\'я.';
                if ($lastname === '') $result['errors'][] = 'Вкажіть прізвище.';
                if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $result['errors'][] = 'Неправильний email.';
                if ($password !== '' && $password !== $password_confirm) $result['errors'][] = 'Паролі не співпадають.';
                if ($password !== '' && strlen($password) < 6) $result['errors'][] = 'Пароль має бути щонайменше 6 символів.';
                if (emailBelongsToOtherUser($pdo, $email, $userId)) $result['errors'][] = 'Email вже використовується іншим користувачем.';

                // якщо користувач подає запит на зміну пароля, вимагайте підтвердження поточного пароля
                if ($password !== '') {
                    if (!empty($user['password_hash'])) {
                        if ($current_password === '') {
                            $result['errors'][] = 'Вкажіть поточний пароль для підтвердження змін.';
                        } elseif (!password_verify($current_password, $user['password_hash'])) {
                            $result['errors'][] = 'Поточний пароль невірний.';
                        }
                    }
                    // якщо для користувача ще не існує запису password_hash дозволити встановити пароль без поточного
                }

                if (!$result['errors']) {
                    $passwordHash = $password !== '' ? password_hash($password, PASSWORD_DEFAULT) : null;
                    $ok = updateUser($pdo, $userId, $name, $lastname, $email, $phone === '' ? null : $phone, $passwordHash);
                    if ($ok) {
                        $result['success'] = true;
                        $user = findUserById($pdo, $userId);
                    } else {
                        $result['errors'][] = 'Помилка при збереженні. Спробуйте пізніше.';
                    }
                }
            }
        }
        $result['user'] = $user;
        // завантаження замовлень користувача
        require_once __DIR__ . '/../Model/Order.php';
        $result['orders'] = getUserOrders($pdo, $userId);
    } catch (Exception $e) {
        $result['errors'][] = 'Системна помилка: ' . $e->getMessage();
    }
    return $result;
}

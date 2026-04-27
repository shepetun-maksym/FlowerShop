<!doctype html>
<html lang="uk">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Реєстрація</title>
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="../assets/css/header.css">
  <link rel="stylesheet" href="../assets/css/menu.css">
  <link rel="stylesheet" href="../assets/css/footer.css">
</head>
<body>
    
<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../Model/User.php';
if (session_status() !== PHP_SESSION_ACTIVE) session_start();

$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? '');
    $lastname = trim($_POST['lastname'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm = $_POST['confirm'] ?? '';

    if (!$name || !$lastname || !$email || !$password) $errors[] = 'Всі поля, позначені *, повинні бути заповнені.';
    if ($password !== $confirm) $errors[] = 'Паролі не співпадають.';
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'Неправильний email.';

    if (!$errors) {
        try {
            $pdo = getPDO();
            if (findUserByEmail($pdo, $email)) {
                $errors[] = 'Користувач з таким email вже існує.';
            } else {
                $hash = password_hash($password, PASSWORD_DEFAULT);
                $id = createUser($pdo, $name, $lastname, $email, $phone ?: null, $hash);
                if ($id) {
                    $_SESSION['user_id'] = $id;
                    header('Location: menu.php'); exit;
                } else {
                    $errors[] = 'Помилка при створенні користувача.';
                }
            }
        } catch (Exception $e) {
            $errors[] = 'Системна помилка.';
        }
    }
}
?>
<!doctype html>
<html lang="uk">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Реєстрація</title>
  <link rel="stylesheet" href="../assets/css/header.css">
  <link rel="stylesheet" href="../assets/css/menu.css">
</head>
<body>
<main class="container" style="padding:40px 18px">
  <h1>Реєстрація</h1>
  <?php if ($errors): ?><div class="errors"><ul><?php foreach ($errors as $e) echo '<li>'.htmlspecialchars($e).'</li>'; ?></ul></div><?php endif; ?>
    <div class="auth-card">
        <form method="post" action="register.php" class="auth-form">
            <div class="form-row"><label>Ім'я*<input name="name" value="<?php echo htmlspecialchars($_POST['name'] ?? ''); ?>"></label></div>
            <div class="form-row"><label>Прізвище*<input name="lastname" value="<?php echo htmlspecialchars($_POST['lastname'] ?? ''); ?>"></label></div>
            <div class="form-row"><label>Email*<input name="email" value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>"></label></div>
            <div class="form-row"><label>Телефон<input name="phone" value="<?php echo htmlspecialchars($_POST['phone'] ?? ''); ?>"></label></div>
            <div class="form-row"><label>Пароль*<input type="password" name="password"></label></div>
            <div class="form-row"><label>Підтвердьте пароль*<input type="password" name="confirm"></label></div>
            <div class="form-row"><button class="btn btn-primary" type="submit">Реєстрація</button>
                <a class="btn btn-ghost" href="login.php" style="margin-left:12px">Увійти</a></div>
        </form>
    </div>
</main>
<?php include __DIR__ . '/footer.php'; ?>
</body>
</html>

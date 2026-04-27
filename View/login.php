<!doctype html>
<html lang="uk">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Авторизація</title>
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
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'Неправильний email.';
    if (!$password) $errors[] = 'Введіть пароль.';
    if (!$errors) {
        try {
            $pdo = getPDO();
            $user = findUserByEmail($pdo, $email);
            if (!$user || empty($user['password_hash']) || !password_verify($password, $user['password_hash'])) {
                $errors[] = 'Неправильний email або пароль.';
            } else {
                $_SESSION['user_id'] = (int)$user['id'];
                header('Location: menu.php'); exit;
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
  <title>Вхід</title>
  <link rel="stylesheet" href="../assets/css/header.css">
  <link rel="stylesheet" href="../assets/css/menu.css">
</head>
<body>
<main class="container" style="padding:40px 18px">
  <h1>Вхід</h1>
  <?php if ($errors): ?><div class="errors"><ul><?php foreach ($errors as $e) echo '<li>'.htmlspecialchars($e).'</li>'; ?></ul></div><?php endif; ?>
  <div class="auth-card">
    <form method="post" action="login.php" class="auth-form">
      <div class="form-row"><label>Email<input name="email" value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>"></label></div>
      <div class="form-row"><label>Пароль<input type="password" name="password"></label></div>
      <div class="form-row"><button class="btn btn-primary" type="submit">Увійти</button>
        <a class="btn btn-ghost" href="register.php" style="margin-left:8px">Реєстрація</a></div>
    </form>
  </div>
</main>
<?php include __DIR__ . '/footer.php'; ?>
</body>
</html>

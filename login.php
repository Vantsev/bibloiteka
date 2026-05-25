<?php
$pageTitle = "Авторизация - BookHaven";
require_once "includes/auth.php";
require_once "includes/user_auth.php";

if (is_authorized()) { header("Location: cabinet.php"); exit; }

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $res = login_user($conn, $_POST);
    if ($res['success']) {
        if (in_array($res['role'], ['admin', 'superadmin'])) {
            header("Location: admin.php");
        } else {
            header("Location: cabinet.php");
        }
        exit;
    }
    $error = $res['message'];
}

require_once "includes/header.php";
?>
<section class="card" style="max-width: 400px; margin: 0 auto; text-align: center;">
    <h2 style="color: var(--accent); margin-bottom: 20px;">Вход в библиотеку</h2>
    <?php if (isset($error)): ?>
        <div class="notice error"><?php echo htmlspecialchars($error); ?></div>
    <?php endif; ?>
    <form method="POST" style="text-align: left;">
        <label>Логин</label>
        <input type="text" name="login" required>
        
        <label>Пароль</label>
        <input type="password" name="password" required>
        
        <button type="submit" class="button" style="margin-top: 15px; width: 100%;">Войти</button>
        <p style="margin-top: 15px; text-align: center;"><a href="register.php" style="color: var(--accent);">Нет аккаунта? Зарегистрируйтесь</a></p>
    </form>
</section>
<?php require_once "includes/footer.php"; ?>
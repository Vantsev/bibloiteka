<?php
$pageTitle = "Вход в панель управления - BookHaven";
require_once "includes/auth.php";
require_once "includes/user_auth.php";

$adminToken = 'bkhvn_s3cr3t_panel_2024';

if (!isset($_GET['token']) || $_GET['token'] !== $adminToken) {
    http_response_code(404);
    echo "Страница не найдена.";
    exit;
}

if (isset($_SESSION['admin_auth']) && $_SESSION['admin_auth'] === true) {
    header("Location: admin.php");
    exit;
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (empty($_POST['login']) || empty($_POST['password'])) {
        $error = 'Укажите логин и пароль.';
    } else {
        $login = trim($_POST['login']);
        $stmt = $conn->prepare("SELECT id, login, password, role FROM users WHERE login = ? AND role = 'superadmin'");
        $stmt->bind_param('s', $login);
        $stmt->execute();
        $res = $stmt->get_result();
        if ($row = $res->fetch_assoc()) {
            if (password_verify($_POST['password'], $row['password'])) {
                $_SESSION['user_id'] = $row['id'];
                $_SESSION['user_login'] = $row['login'];
                $_SESSION['user_role'] = $row['role'];
                $_SESSION['admin_auth'] = true;
                $stmt->close();
                header("Location: admin.php");
                exit;
            }
        }
        $stmt->close();
        $error = 'Неверные данные или недостаточно прав.';
    }
}

require_once "includes/header.php";
?>
<section class="card" style="max-width: 400px; margin: 0 auto; text-align: center;">
    <h2 style="color: var(--accent); margin-bottom: 20px;">Панель управления</h2>
    <?php if ($error): ?>
        <div class="notice error"><?php echo htmlspecialchars($error); ?></div>
    <?php endif; ?>
    <form method="POST" style="text-align: left;">
        <label>Логин администратора</label>
        <input type="text" name="login" required>

        <label>Пароль</label>
        <input type="password" name="password" required>

        <button type="submit" class="button" style="margin-top: 15px; width: 100%;">Войти в панель</button>
    </form>
</section>
<?php require_once "includes/footer.php"; ?>

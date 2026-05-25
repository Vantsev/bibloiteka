<?php
$pageTitle = "Регистрация - BookHaven";
require_once "includes/auth.php";
require_once "includes/user_auth.php";

// ── Google reCAPTCHA v2 ───────────────────────────────────────────────────────
// Замени на свои ключи с https://www.google.com/recaptcha/admin
// (домен localhost уже прописан в тестовых ключах ниже)
define('RECAPTCHA_SITE_KEY',   '6LeIxAcTAAAAAJcZVRqyHh71UMIEGNQ_MXjiZKhI'); // тестовый
define('RECAPTCHA_SECRET_KEY', '6LeIxAcTAAAAAGG-vFI1TnRWxMZNFuojJ4WifJWe'); // тестовый
// ─────────────────────────────────────────────────────────────────────────────

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $res  = register_user($conn, $_POST, $_FILES);
    $msg  = $res['message'];
    $succ = $res['success'];
}

require_once "includes/header.php";
?>

<!-- Подключаем скрипт reCAPTCHA -->
<script src="https://www.google.com/recaptcha/api.js" async defer></script>

<section class="card" style="max-width:520px;margin:0 auto;">
    <h2 style="color:var(--accent);margin-bottom:20px;">Регистрация</h2>

    <?php if (isset($msg)): ?>
        <div class="notice <?php echo $succ ? 'success' : 'error'; ?>">
            <?php echo htmlspecialchars($msg); ?>
        </div>
        <?php if ($succ): ?>
            <p><a href="login.php" class="button">Перейти ко входу</a></p>
        <?php endif; ?>
    <?php endif; ?>

    <?php if (!isset($succ) || !$succ): ?>
    <form method="POST" enctype="multipart/form-data">

        <label>Логин * <small style="color:#888;">(только A–Z, 0–9, _ -)</small></label>
        <input type="text" name="login"
               value="<?php echo htmlspecialchars($_POST['login'] ?? ''); ?>" required>

        <label>Пароль * <small style="color:#888;">(минимум 6 символов)</small></label>
        <input type="password" name="password" required>

        <label>Дата регистрации *</label>
        <input type="date" name="reg_date"
               value="<?php echo htmlspecialchars($_POST['reg_date'] ?? date('Y-m-d')); ?>" required>

        <label>Email *</label>
        <input type="email" name="email"
               value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>" required>

        <label>Аватар <small style="color:#888;">(JPG/PNG/GIF/WEBP, до 2 МБ)</small></label>
        <input type="file" name="photo" accept="image/*">

        <!-- Google reCAPTCHA v2 виджет -->
        <div style="margin:16px 0;">
            <div class="g-recaptcha"
                 data-sitekey="<?php echo RECAPTCHA_SITE_KEY; ?>"></div>
        </div>

        <button type="submit" class="button" style="margin-top:10px;width:100%;">
            Зарегистрироваться
        </button>
    </form>
    <?php endif; ?>
</section>
<?php require_once "includes/footer.php"; ?>

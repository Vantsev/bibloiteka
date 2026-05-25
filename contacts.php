<?php
$pageTitle = "Контакты - BookHaven";
require_once "includes/auth.php";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $to = "admin@bookhaven.example.com";
    $subject = "Новое сообщение от читателя " . htmlspecialchars($_POST['name']);
    $message = "Email: " . htmlspecialchars($_POST['email']) . "\n\n" . htmlspecialchars($_POST['message']);
    
    // mail($to, $subject, $message);
    $success = "Письмо успешно отправлено! (Имитация отправки для лабораторной)";
}

require_once "includes/header.php";
?>
<section class="card" style="max-width: 600px; margin: 0 auto;">
    <h2 style="color: var(--accent);">Свяжитесь с нами</h2>
    <?php if (isset($success)): ?><div class="notice success"><?php echo $success; ?></div><?php endif; ?>
    
    <p>Адрес: ул. Литературная, д. 45, Москва<br>
    Телефон: +7 (495) 123-45-67<br>
    Email: support@bookhaven.study</p>

    <form method="POST" style="margin-top: 30px;">
        <label>Имя</label>
        <input type="text" name="name" required>
        
        <label>Email</label>
        <input type="email" name="email" required>
        
        <label>Текст сообщения (жалоба, предложение по ассортименту)</label>
        <textarea name="message" rows="5" required></textarea>
        
        <button type="submit" class="button" style="width: 100%;">Отправить</button>
    </form>
</section>
<?php require_once "includes/footer.php"; ?>
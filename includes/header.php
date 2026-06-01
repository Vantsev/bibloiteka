<?php
$pageTitle = $pageTitle ?? 'Library';
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
$userLogin = $_SESSION['user_login'] ?? '';
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($pageTitle); ?></title>
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
<header class="site-header">
    <div class="container header-row">
        <a href="index.php" class="brand">📚 BookHaven</a>
        <nav class="main-nav">
            <a href="index.php">О нас</a>
            <a href="gallery.php">Галерея</a>
            <a href="products.php">Книги</a>
            <a href="cart.php">Корзина</a>
            <a href="guestbook.php">Отзывы</a>
            <a href="contacts.php">Контакты</a>
            <?php if (!empty($userLogin)): ?>
                <a href="cabinet.php">Кабинет</a>
                <a href="logout.php">Выход (<?php echo htmlspecialchars($userLogin); ?>)</a>
            <?php else: ?>
                <a href="register.php">Регистрация</a>
                <a href="login.php">Вход</a>
            <?php endif; ?>
        </nav>
    </div>
</header>
<main class="container main-area">
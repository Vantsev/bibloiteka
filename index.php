<?php
$pageTitle = "Главная - BookHaven";
require_once "includes/auth.php";
require_once "includes/header.php";
?>
<section class="card">
    <div style="text-align: center; padding: 40px 0;">
        <h1 style="color: var(--accent); margin-bottom: 20px;">Добро пожаловать в BookHaven</h1>
        <p style="font-size: 18px; max-width: 600px; margin: 0 auto; line-height: 1.6;">
            Ваша надежная виртуальная библиотека и книжный магазин. Мы предлагаем доступ к мировой 
            классике, фэнтези и бестселлерам. Изучайте наш каталог, регистрируйтесь и оформляйте 
            бронирования моментально!
        </p>
    </div>
</section>

<section class="grid" style="margin-top: 30px;">
    <div class="card">
        <h3>📚 Широкий ассортимент</h3>
        <p>Тысячи книг в электронном и бумажном форматах. Удобный поиск по жанрам и авторам.</p>
    </div>
    <div class="card">
        <h3>🔒 Личный Кабинет</h3>
        <p>Полная история ваших приобретений, управление данными и эксклюзивные предложения после регистрации.</p>
    </div>
    <div class="card">
        <h3>🛒 Корзина (М:М)</h3>
        <p>Удобная корзина для множественных заказов. Мы реализовали надежную систему бронирования книг.</p>
    </div>
</section>
<?php require_once "includes/footer.php"; ?>
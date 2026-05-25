<?php
$pageTitle = "Личный кабинет - BookHaven";
require_once "includes/auth.php";
require_auth();

$uid = $_SESSION['user_id'];
$stmt = $conn->prepare("SELECT login, email, reg_date, photo FROM users WHERE id = ?");
$stmt->bind_param("i", $uid);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();
$stmt->close();

$orders = $conn->query("
    SELECT o.id, o.order_date, o.total_price, 
           GROUP_CONCAT(CONCAT(p.title, ' (', oi.quantity, ')') SEPARATOR ', ') as items
    FROM orders o
    JOIN order_items oi ON o.id = oi.order_id
    JOIN products p ON oi.product_id = p.id
    WHERE o.user_id = $uid
    GROUP BY o.id
    ORDER BY o.order_date DESC
");

require_once "includes/header.php";
?>
<section class="card" style="display: flex; gap: 20px; align-items: flex-start; max-width: 800px; margin: 0 auto 30px;">
    <?php if(!empty($user['photo'])): ?>
        <img src="<?php echo htmlspecialchars($user['photo']); ?>" alt="Аватар" style="width: 150px; height: 150px; border-radius: 50%; object-fit: cover; border: 3px solid var(--accent);">
    <?php else: ?>
        <div style="width: 150px; height: 150px; border-radius: 50%; background: #ddd; display: flex; align-items: center; justify-content: center; font-size: 50px; color: #fff;">🧑</div>
    <?php endif; ?>
    <div>
        <h2 style="color: var(--accent); margin: 0 0 10px;">Профиль читателя</h2>
        <p><b>Логин:</b> <?php echo htmlspecialchars($user['login']); ?></p>
        <p><b>Email:</b> <?php echo htmlspecialchars($user['email']); ?></p>
        <p><b>Дата регистрации:</b> <?php echo htmlspecialchars($user['reg_date']); ?></p>
        <a href="logout.php" class="button" style="background: #666; margin-top: 10px;">Выйти</a>
    </div>
</section>

<section class="card" style="max-width: 800px; margin: 0 auto;">
    <h3 style="color: var(--accent);">История заказов (М:М связь)</h3>
    <?php if($orders->num_rows > 0): ?>
        <table>
            <tr><th>Заказ №</th><th>Дата</th><th>Книги</th><th>Сумма</th></tr>
            <?php while($o = $orders->fetch_assoc()): ?>
                <tr>
                    <td>#<?php echo $o['id']; ?></td>
                    <td><?php echo date('d.m.Y H:i', strtotime($o['order_date'])); ?></td>
                    <td><?php echo htmlspecialchars($o['items']); ?></td>
                    <td><b><?php echo number_format($o['total_price'], 2, '.', ' '); ?> ₽</b></td>
                </tr>
            <?php endwhile; ?>
        </table>
    <?php else: ?>
        <p>Вы еще не делали заказов.</p>
    <?php endif; ?>
</section>
<?php require_once "includes/footer.php"; ?>
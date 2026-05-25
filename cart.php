<?php
$pageTitle = "Корзина - BookHaven";
require_once "includes/auth.php";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['update'])) {
        foreach ($_POST['qty'] as $id => $qty) {
            $q = intval($qty);
            if ($q <= 0) unset($_SESSION['cart'][$id]);
            else $_SESSION['cart'][$id] = $q;
        }
    } elseif (isset($_POST['checkout'])) {
        require_auth();
        if (!empty($_SESSION['cart'])) {
            $conn->begin_transaction();
            try {
                $total = 0;
                $items = [];
                foreach ($_SESSION['cart'] as $id => $qty) {
                    $res = $conn->query("SELECT price FROM products WHERE id = " . intval($id));
                    if ($row = $res->fetch_assoc()) {
                        $p = floatval($row['price']);
                        $total += $p * $qty;
                        $items[] = ['id'=>$id, 'qty'=>$qty, 'price'=>$p];
                    }
                }
                
                $uid = $_SESSION['user_id'];
                $stmt = $conn->prepare("INSERT INTO orders (user_id, total_price) VALUES (?, ?)");
                $stmt->bind_param("id", $uid, $total);
                $stmt->execute();
                $oid = $stmt->insert_id;
                $stmt->close();
                
                $stmt = $conn->prepare("INSERT INTO order_items (order_id, product_id, quantity, price_at_purchase) VALUES (?, ?, ?, ?)");
                foreach ($items as $itm) {
                    $stmt->bind_param("iiid", $oid, $itm['id'], $itm['qty'], $itm['price']);
                    $stmt->execute();
                }
                $stmt->close();
                $conn->commit();
                
                $_SESSION['cart'] = [];
                $success = "Заказ #$oid успешно оформлен!";
            } catch (Exception $e) {
                $conn->rollback();
                $error = "Ошибка при оформлении заказа: " . $e->getMessage();
            }
        }
    }
}

$cart = $_SESSION['cart'] ?? [];
$cartItems = [];
$totalCart = 0;

if (!empty($cart)) {
    $ids = implode(',', array_map('intval', array_keys($cart)));
    $res = $conn->query("SELECT id, title, author, price FROM products WHERE id IN ($ids)");
    while ($row = $res->fetch_assoc()) {
        $row['qty'] = $cart[$row['id']];
        $row['subtotal'] = $row['qty'] * $row['price'];
        $totalCart += $row['subtotal'];
        $cartItems[] = $row;
    }
}

require_once "includes/header.php";
?>
<section class="card" style="max-width: 800px; margin: 0 auto;">
    <h2>Корзина бронирования</h2>
    <?php if (isset($success)): ?><div class="notice success"><?php echo $success; ?></div><?php endif; ?>
    <?php if (isset($error)): ?><div class="notice error"><?php echo $error; ?></div><?php endif; ?>
    
    <?php if (!empty($cartItems)): ?>
        <form method="POST">
            <table>
                <tr><th>Книга</th><th>Цена</th><th>Кол-во</th><th>Итого</th></tr>
                <?php foreach($cartItems as $item): ?>
                    <tr>
                        <td><b><?php echo htmlspecialchars($item['title']); ?></b><br><i><?php echo htmlspecialchars($item['author']); ?></i></td>
                        <td><?php echo number_format($item['price'], 2); ?> ₽</td>
                        <td><input type="number" name="qty[<?php echo $item['id']; ?>]" value="<?php echo $item['qty']; ?>" min="0" style="width: 60px;"></td>
                        <td><b><?php echo number_format($item['subtotal'], 2); ?> ₽</b></td>
                    </tr>
                <?php endforeach; ?>
            </table>
            <div style="text-align: right; margin-top: 15px; font-size: 20px; font-weight: bold; color: var(--accent);">К оплате: <?php echo number_format($totalCart, 2, '.', ' '); ?> ₽</div>
            <div style="display: flex; gap: 10px; justify-content: flex-end; margin-top: 20px;">
                <button type="submit" name="update" class="button" style="background: #666;">Обновить</button>
                <?php if (is_authorized()): ?>
                    <button type="submit" name="checkout" class="button">Оформить заказ</button>
                <?php else: ?>
                    <a href="login.php" class="button" style="background: var(--accent-dark);">Войти для оформления</a>
                <?php endif; ?>
            </div>
        </form>
    <?php else: ?>
        <p>Ваша корзина пуста. <a href="products.php" style="color: var(--accent); font-weight: bold;">Перейти к фонду</a></p>
    <?php endif; ?>
</section>
<?php require_once "includes/footer.php"; ?>
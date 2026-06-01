<?php
$pageTitle = "Корзина - BookHaven";
require_once "includes/auth.php";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['remove'])) {
        $removeId = intval($_POST['remove']);
        unset($_SESSION['cart'][$removeId]);
    } elseif (isset($_POST['update'])) {
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
                $success = $oid;
            } catch (Exception $e) {
                $conn->rollback();
                $error = "Ошибка при оформлении заказа.";
            }
        }
    }
}

$cart = $_SESSION['cart'] ?? [];
$cartItems = [];
$totalCart = 0;
$totalQty = 0;

if (!empty($cart)) {
    $ids = implode(',', array_map('intval', array_keys($cart)));
    $res = $conn->query("SELECT id, title, author, price FROM products WHERE id IN ($ids)");
    while ($row = $res->fetch_assoc()) {
        $row['qty'] = $cart[$row['id']];
        $row['subtotal'] = $row['qty'] * $row['price'];
        $totalCart += $row['subtotal'];
        $totalQty += $row['qty'];
        $cartItems[] = $row;
    }
}

require_once "includes/header.php";
?>

<style>
.cart-wrap { max-width: 800px; margin: 0 auto; }
.cart-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; }
.cart-header h2 { margin: 0; color: var(--accent); }
.cart-count { background: #f5f0ea; padding: 4px 12px; border-radius: 12px; font-size: 13px; color: var(--muted); }

.cart-success { background: #fff; border: 1px solid #4caf50; border-radius: 12px; padding: 30px; text-align: center; margin-bottom: 20px; }
.cart-success .icon { font-size: 48px; margin-bottom: 12px; }
.cart-success h3 { color: #2e7d32; margin: 0 0 8px; }
.cart-success p { color: var(--muted); margin: 0 0 16px; }

.cart-item { display: flex; align-items: center; gap: 16px; background: #fff; border: 1px solid var(--border); border-radius: 10px; padding: 16px; margin-bottom: 12px; transition: box-shadow 0.2s; }
.cart-item:hover { box-shadow: 0 2px 8px rgba(0,0,0,0.06); }
.cart-item-cover { width: 50px; height: 70px; border-radius: 4px; display: flex; align-items: center; justify-content: center; flex-shrink: 0; padding: 6px; text-align: center; }
.cart-item-cover span { font-size: 8px; font-weight: bold; color: rgba(255,255,255,0.9); line-height: 1.2; }
.cart-item-info { flex-grow: 1; }
.cart-item-info .title { font-weight: bold; color: var(--text); font-size: 15px; margin-bottom: 2px; }
.cart-item-info .author { color: var(--muted); font-size: 13px; }
.cart-item-price { text-align: right; min-width: 80px; }
.cart-item-price .unit { color: var(--muted); font-size: 12px; }
.cart-item-price .subtotal { font-weight: bold; font-size: 16px; color: var(--text); }
.cart-item-qty { display: flex; align-items: center; gap: 4px; }
.cart-item-qty input { width: 50px; text-align: center; margin: 0; padding: 6px; font-size: 14px; }
.cart-item-remove { background: none; border: none; color: #ccc; font-size: 20px; cursor: pointer; padding: 4px 8px; transition: color 0.2s; }
.cart-item-remove:hover { color: #e74c3c; }

.cart-summary { background: #fff; border: 1px solid var(--border); border-radius: 12px; padding: 20px; margin-top: 20px; }
.cart-summary-row { display: flex; justify-content: space-between; align-items: center; padding: 8px 0; }
.cart-summary-row.total { border-top: 2px solid var(--border); margin-top: 8px; padding-top: 16px; }
.cart-summary-row.total .val { font-size: 22px; color: var(--accent); font-weight: bold; }
.cart-actions { display: flex; gap: 10px; justify-content: flex-end; margin-top: 16px; }
.cart-actions .button { padding: 12px 24px; font-size: 14px; }
.btn-outline { background: transparent; border: 2px solid var(--border); color: var(--text) !important; }
.btn-outline:hover { border-color: var(--accent); color: var(--accent) !important; background: transparent; }

.cart-empty { background: #fff; border: 1px solid var(--border); border-radius: 12px; padding: 50px; text-align: center; }
.cart-empty .icon { font-size: 60px; margin-bottom: 16px; }
.cart-empty h3 { color: var(--text); margin: 0 0 8px; }
.cart-empty p { color: var(--muted); margin: 0 0 20px; }
</style>

<section class="cart-wrap">
    <div class="cart-header">
        <h2>Корзина</h2>
        <?php if (!empty($cartItems)): ?>
            <span class="cart-count"><?php echo $totalQty; ?> <?php echo $totalQty % 10 == 1 && $totalQty % 100 != 11 ? 'книга' : (in_array($totalQty % 10, [2,3,4]) && !in_array($totalQty % 100, [12,13,14]) ? 'книги' : 'книг'); ?></span>
        <?php endif; ?>
    </div>

    <?php if (isset($success)): ?>
        <div class="cart-success">
            <div class="icon">✅</div>
            <h3>Заказ #<?php echo $success; ?> оформлен!</h3>
            <p>Спасибо за покупку. Заказ появится в вашем личном кабинете.</p>
            <div style="display: flex; gap: 10px; justify-content: center;">
                <a href="cabinet.php" class="button" style="padding: 10px 20px;">Мои заказы</a>
                <a href="products.php" class="button btn-outline" style="padding: 10px 20px;">Продолжить покупки</a>
            </div>
        </div>
    <?php endif; ?>

    <?php if (isset($error)): ?>
        <div class="notice error" style="border-radius: 8px; margin-bottom: 16px;"><?php echo $error; ?></div>
    <?php endif; ?>

    <?php if (!empty($cartItems)): ?>
        <form method="POST">
            <?php
            $colors = ['#8c3218','#2c5f2d','#1b4965','#6b2d5b','#4a3728'];
            foreach($cartItems as $item):
                $color = $colors[$item['id'] % count($colors)];
            ?>
                <div class="cart-item">
                    <div class="cart-item-cover" style="background: <?php echo $color; ?>; border-radius: 4px;">
                        <span><?php echo htmlspecialchars(mb_strimwidth($item['title'], 0, 20, '...')); ?></span>
                    </div>
                    <div class="cart-item-info">
                        <div class="title"><?php echo htmlspecialchars($item['title']); ?></div>
                        <div class="author"><?php echo htmlspecialchars($item['author']); ?></div>
                    </div>
                    <div class="cart-item-qty">
                        <input type="number" name="qty[<?php echo $item['id']; ?>]" value="<?php echo $item['qty']; ?>" min="1">
                    </div>
                    <div class="cart-item-price">
                        <div class="unit"><?php echo number_format($item['price'], 0, '', ' '); ?> ₽ / шт</div>
                        <div class="subtotal"><?php echo number_format($item['subtotal'], 0, '', ' '); ?> ₽</div>
                    </div>
                    <button type="submit" name="remove" value="<?php echo $item['id']; ?>" class="cart-item-remove" title="Удалить">×</button>
                </div>
            <?php endforeach; ?>

            <div class="cart-summary">
                <div class="cart-summary-row">
                    <span style="color: var(--muted);">Товаров: <?php echo $totalQty; ?></span>
                    <span style="color: var(--muted);"><?php echo number_format($totalCart, 0, '', ' '); ?> ₽</span>
                </div>
                <div class="cart-summary-row total">
                    <span style="font-weight: bold; font-size: 16px;">Итого к оплате</span>
                    <span class="val"><?php echo number_format($totalCart, 0, '', ' '); ?> ₽</span>
                </div>
                <div class="cart-actions">
                    <button type="submit" name="update" class="button btn-outline">Обновить</button>
                    <?php if (is_authorized()): ?>
                        <button type="submit" name="checkout" class="button">Оформить заказ</button>
                    <?php else: ?>
                        <a href="login.php" class="button">Войти для оформления</a>
                    <?php endif; ?>
                </div>
            </div>
        </form>
    <?php elseif (!isset($success)): ?>
        <div class="cart-empty">
            <div class="icon">🛒</div>
            <h3>Корзина пуста</h3>
            <p>Добавьте книги из каталога, чтобы оформить заказ</p>
            <a href="products.php" class="button">Перейти в каталог</a>
        </div>
    <?php endif; ?>
</section>
<?php require_once "includes/footer.php"; ?>

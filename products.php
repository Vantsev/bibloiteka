<?php
$pageTitle = "Книги - BookHaven";
require_once "includes/auth.php";

$q   = trim($_GET['search'] ?? '');
$cat = trim($_GET['category'] ?? '');
$addedId = intval($_GET['added'] ?? 0);

// Название добавленной книги для уведомления
$addedTitle = '';
if ($addedId > 0) {
    $s = $conn->prepare("SELECT title FROM products WHERE id = ?");
    $s->bind_param("i", $addedId);
    $s->execute();
    $r = $s->get_result()->fetch_assoc();
    $addedTitle = $r['title'] ?? '';
    $s->close();
}

$sql    = "SELECT id, title, author, category, description, isbn, price FROM products WHERE 1=1";
$params = []; $types = "";
if ($q !== '') {
    $sql .= " AND (title LIKE ? OR author LIKE ?)";
    $like = "%$q%"; $params[] = $like; $params[] = $like; $types .= "ss";
}
if ($cat !== '') {
    $sql .= " AND category = ?"; $params[] = $cat; $types .= "s";
}
$stmt = $conn->prepare($sql);
if (!empty($params)) $stmt->bind_param($types, ...$params);
$stmt->execute();
$products = $stmt->get_result();

$cats = $conn->query("SELECT DISTINCT category FROM products");
require_once "includes/header.php";
?>

<?php if ($addedTitle): ?>
<div style="
    position: fixed; top: 20px; right: 20px; z-index: 9999;
    background: #fff;
    border-left: 4px solid #6a2a2a;
    border-radius: 8px;
    box-shadow: 0 4px 20px rgba(0,0,0,0.15);
    padding: 16px 20px;
    max-width: 320px;
    display: flex; flex-direction: column; gap: 10px;
    animation: slideIn 0.3s ease;
" id="cart-toast">
    <div style="display:flex; justify-content:space-between; align-items:flex-start; gap:10px;">
        <div>
            <div style="font-weight:bold; font-size:14px; margin-bottom:3px;">✅ Добавлено в корзину</div>
            <div style="font-size:13px; color:#555;">«<?php echo htmlspecialchars($addedTitle); ?>»</div>
        </div>
        <button onclick="document.getElementById('cart-toast').remove()"
                style="background:none;border:none;cursor:pointer;font-size:18px;color:#999;line-height:1;padding:0;">×</button>
    </div>
    <div style="display:flex; gap:8px;">
        <a href="products.php" style="
            flex:1; text-align:center; padding:7px 0;
            border:1px solid #ccc; border-radius:5px;
            font-size:13px; color:#333; text-decoration:none;
        ">← В магазин</a>
        <a href="cart.php" style="
            flex:1; text-align:center; padding:7px 0;
            background:#6a2a2a; border-radius:5px;
            font-size:13px; color:#fff; text-decoration:none;
        ">Перейти в корзину</a>
    </div>
</div>
<style>
@keyframes slideIn {
    from { opacity: 0; transform: translateX(40px); }
    to   { opacity: 1; transform: translateX(0); }
}
</style>
<script>
    setTimeout(function() {
        var t = document.getElementById('cart-toast');
        if (t) t.remove();
    }, 5000);
</script>
<?php endif; ?>

<section class="card" style="margin: 0 auto 20px;">
    <h2>Библиотечный фонд</h2>
    <form method="GET" style="display: flex; gap: 10px; align-items: flex-end; flex-wrap: wrap;">
        <div style="flex: 1 1 200px;">
            <label>Поиск:</label>
            <input type="text" name="search" value="<?php echo htmlspecialchars($q); ?>" placeholder="Название или автор...">
        </div>
        <div style="flex: 1 1 150px;">
            <label>Категория (Жанр):</label>
            <select name="category">
                <option value="">Все жанры</option>
                <?php while ($c = $cats->fetch_assoc()): ?>
                    <option value="<?php echo htmlspecialchars($c['category']); ?>"
                        <?php echo $cat === $c['category'] ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($c['category']); ?>
                    </option>
                <?php endwhile; ?>
            </select>
        </div>
        <button type="submit" class="button" style="margin-bottom: 12px;">Искать</button>
    </form>
</section>

<section class="grid" style="grid-template-columns: repeat(auto-fit, minmax(260px, 1fr));">
    <?php if ($products->num_rows > 0): ?>
        <?php while ($p = $products->fetch_assoc()):
            $colors = ['#8c3218','#2c5f2d','#1b4965','#6b2d5b','#4a3728'];
            $color = $colors[$p['id'] % count($colors)];
            $shortDesc = mb_strlen($p['description']) > 120 ? mb_substr($p['description'], 0, 120) . '...' : ($p['description'] ?: 'Описание скоро появится...');
        ?>
            <article class="card" style="display: flex; flex-direction: column; padding: 15px;">
                <div style="text-align: center; margin-bottom: 12px; background: #fdfaf6; padding: 15px; border-radius: 4px; display: flex; align-items: center; justify-content: center;">
                    <div style="width: 80px; height: 115px; background: <?php echo $color; ?>; border-radius: 3px; box-shadow: 0 2px 6px rgba(0,0,0,0.2); display: flex; flex-direction: column; justify-content: center; align-items: center; padding: 8px; text-align: center;">
                        <span style="color: rgba(255,255,255,0.9); font-size: 9px; font-weight: bold; line-height: 1.2;"><?php echo htmlspecialchars(mb_strimwidth($p['title'], 0, 40, '...')); ?></span>
                    </div>
                </div>
                <h3 style="color: var(--accent); margin: 0 0 4px; font-size: 15px; line-height: 1.3;">
                    <?php echo htmlspecialchars($p['title']); ?>
                </h3>
                <p style="color: var(--muted); font-size: 13px; margin: 0 0 8px;">
                    <i><?php echo htmlspecialchars($p['author']); ?></i>
                </p>
                <p style="flex-grow: 1; font-size: 12px; line-height: 1.4; margin: 0 0 12px; color: var(--muted);">
                    <?php echo htmlspecialchars($shortDesc); ?>
                </p>
                <div style="display: flex; justify-content: space-between; align-items: center; border-top: 1px solid var(--border); padding-top: 10px; margin-top: auto;">
                    <b style="font-size: 16px; color: var(--text); white-space: nowrap;">
                        <?php echo number_format($p['price'], 0, '', ' '); ?> ₽
                    </b>
                    <a href="add_to_cart.php?id=<?php echo $p['id']; ?>" class="button"
                       style="white-space: nowrap; padding: 8px 14px; font-size: 13px;">В корзину</a>
                </div>
            </article>
        <?php endwhile; ?>
    <?php else: ?>
        <h3 style="text-align: center; width: 100%; color: var(--muted);">Книги не найдены</h3>
    <?php endif; ?>
</section>
<?php require_once "includes/footer.php"; ?>

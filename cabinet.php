<?php
$pageTitle = "Личный кабинет - BookHaven";
require_once "includes/auth.php";
require_auth();

$uid = $_SESSION['user_id'];

// Загружаем профиль
function load_user($conn, $uid) {
    $stmt = $conn->prepare("SELECT login, email, reg_date, photo, role FROM users WHERE id = ?");
    $stmt->bind_param("i", $uid);
    $stmt->execute();
    $u = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    return $u;
}
$user = load_user($conn, $uid);

// ── Обработка редактирования профиля ───────────────────────────────────────────
$editMode    = isset($_GET['edit']);
$emailInvalid = false;   // флаг для вывода пользовательского предупреждения inline
$photoWarn    = false;
$saved        = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_profile'])) {
    $editMode = true;
    $newEmail = trim($_POST['email'] ?? '');

    if (!filter_var($newEmail, FILTER_VALIDATE_EMAIL)) {
        // Невалидный email — пользовательская ошибка. Сам trigger_error()
        // вызовем ниже, в теле формы, чтобы блок обработчика встал на нужное место.
        $emailInvalid = true;
    } else {
        // Email валиден — обрабатываем (опционально) загрузку нового фото
        $photoPath = $user['photo'];
        if (!empty($_FILES['photo']['name']) && $_FILES['photo']['error'] === UPLOAD_ERR_OK) {
            $allowed_ext  = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
            $allowed_mime = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
            $ext  = strtolower(pathinfo($_FILES['photo']['name'], PATHINFO_EXTENSION));
            $mime = mime_content_type($_FILES['photo']['tmp_name']);
            if (in_array($ext, $allowed_ext) && in_array($mime, $allowed_mime) && $_FILES['photo']['size'] <= 2 * 1024 * 1024) {
                if (!is_dir(__DIR__ . '/uploads')) mkdir(__DIR__ . '/uploads', 0755, true);
                $newname = time() . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
                if (move_uploaded_file($_FILES['photo']['tmp_name'], __DIR__ . '/uploads/' . $newname)) {
                    $photoPath = 'uploads/' . $newname;
                }
            } else {
                $photoWarn = true; // выведем предупреждение обработчика inline в форме
            }
        }
        $stmt = $conn->prepare("UPDATE users SET email = ?, photo = ? WHERE id = ?");
        $stmt->bind_param("ssi", $newEmail, $photoPath, $uid);
        $stmt->execute();
        $stmt->close();
        trigger_error("Пользователь «{$user['login']}» обновил профиль.", E_USER_NOTICE);
        $user = load_user($conn, $uid); // перечитываем свежие данные
        $saved = true;
        $editMode = false;
    }
}

$orders = $conn->query("
    SELECT o.id, o.order_date, o.total_price
    FROM orders o
    WHERE o.user_id = $uid
    ORDER BY o.order_date DESC
");

$totalOrders = $conn->query("SELECT COUNT(*) as c FROM orders WHERE user_id = $uid")->fetch_assoc()['c'];
$totalSpent = $conn->query("SELECT COALESCE(SUM(total_price), 0) as s FROM orders WHERE user_id = $uid")->fetch_assoc()['s'];
$totalBooks = $conn->query("SELECT COALESCE(SUM(oi.quantity), 0) as c FROM order_items oi JOIN orders o ON oi.order_id = o.id WHERE o.user_id = $uid")->fetch_assoc()['c'];
$memberDays = (int)((time() - strtotime($user['reg_date'])) / 86400);

require_once "includes/header.php";
?>

<style>
.cabinet-wrap { max-width: 860px; margin: 0 auto; }
.profile-card { display: flex; gap: 24px; align-items: center; padding: 24px; background: #fff; border: 1px solid var(--border); border-radius: 12px; box-shadow: 0 2px 8px rgba(0,0,0,0.04); margin-bottom: 20px; }
.profile-avatar { width: 100px; height: 100px; border-radius: 50%; object-fit: cover; border: 3px solid var(--accent); flex-shrink: 0; }
.profile-avatar-placeholder { width: 100px; height: 100px; border-radius: 50%; background: linear-gradient(135deg, var(--accent), #d4785a); display: flex; align-items: center; justify-content: center; font-size: 36px; color: #fff; flex-shrink: 0; }
.profile-info { flex-grow: 1; }
.profile-info h2 { margin: 0 0 4px; color: var(--text); font-size: 22px; }
.profile-info .role-tag { display: inline-block; padding: 3px 10px; border-radius: 12px; font-size: 11px; font-weight: bold; margin-left: 8px; }
.role-superadmin { background: #fdeaea; color: var(--accent); }
.role-admin { background: #e8f4fd; color: #2980b9; }
.role-user { background: #f0ebe5; color: var(--muted); }
.profile-meta { color: var(--muted); font-size: 13px; margin: 8px 0 0; display: flex; gap: 16px; flex-wrap: wrap; }
.profile-meta span { display: flex; align-items: center; gap: 4px; }
.profile-actions { display: flex; gap: 8px; flex-shrink: 0; }

.stats-row { display: grid; grid-template-columns: repeat(4, 1fr); gap: 12px; margin-bottom: 20px; }
.stat-box { background: #fff; border: 1px solid var(--border); border-radius: 10px; padding: 16px; text-align: center; }
.stat-box .val { font-size: 22px; font-weight: bold; color: var(--accent); }
.stat-box .lbl { font-size: 11px; color: var(--muted); text-transform: uppercase; letter-spacing: 0.5px; margin-top: 4px; }

.orders-section { background: #fff; border: 1px solid var(--border); border-radius: 12px; padding: 20px; box-shadow: 0 2px 8px rgba(0,0,0,0.04); }
.orders-section h3 { margin: 0 0 16px; color: var(--accent); font-size: 18px; }
.order-item { border: 1px solid var(--border); border-radius: 8px; padding: 14px; margin-bottom: 12px; transition: box-shadow 0.2s; }
.order-item:hover { box-shadow: 0 2px 8px rgba(0,0,0,0.06); }
.order-item:last-child { margin-bottom: 0; }
.order-top { display: flex; justify-content: space-between; align-items: center; margin-bottom: 8px; }
.order-id { font-weight: bold; color: var(--text); }
.order-date { color: var(--muted); font-size: 13px; }
.order-books { font-size: 13px; color: var(--text); line-height: 1.5; }
.order-books span { display: inline-block; background: #f5f0ea; padding: 2px 8px; border-radius: 10px; margin: 2px 4px 2px 0; font-size: 12px; }
.order-total { text-align: right; font-weight: bold; font-size: 15px; margin-top: 8px; color: var(--accent); }
.empty-state { text-align: center; padding: 40px; color: var(--muted); }
.empty-state p:first-child { font-size: 40px; margin: 0 0 10px; }
</style>

<section class="cabinet-wrap">
    <div class="profile-card">
        <?php if(!empty($user['photo'])): ?>
            <img src="<?php echo htmlspecialchars($user['photo']); ?>" alt="Аватар" class="profile-avatar">
        <?php else: ?>
            <div class="profile-avatar-placeholder"><?php echo mb_strtoupper(mb_substr($user['login'], 0, 1)); ?></div>
        <?php endif; ?>
        <div class="profile-info">
            <h2>
                <?php echo htmlspecialchars($user['login']); ?>
                <?php if ($user['role'] === 'superadmin'): ?>
                    <span class="role-tag role-superadmin">Главный админ</span>
                <?php elseif ($user['role'] === 'admin'): ?>
                    <span class="role-tag role-admin">Админ</span>
                <?php else: ?>
                    <span class="role-tag role-user">Читатель</span>
                <?php endif; ?>
            </h2>
            <div class="profile-meta">
                <span><?php echo htmlspecialchars($user['email']); ?></span>
                <span>С нами с <?php echo date('d.m.Y', strtotime($user['reg_date'])); ?></span>
            </div>
        </div>
        <div class="profile-actions">
            <a href="cabinet.php?edit=1" class="button" style="padding: 8px 14px; font-size: 13px;">Редактировать профиль</a>
            <a href="logout.php" class="button" style="padding: 8px 14px; font-size: 13px; background: var(--muted);">Выйти</a>
        </div>
    </div>

    <?php if ($saved): ?>
        <div class="notice success" style="border-radius: 10px;">Профиль успешно обновлён.</div>
    <?php endif; ?>

    <?php if ($editMode): ?>
    <div class="orders-section" style="margin-bottom: 20px;">
        <h3>Редактирование профиля</h3>
        <?php
            // Пользовательский обработчик ошибок выводит свои блоки именно здесь:
            if ($emailInvalid) {
                trigger_error('Неверный формат E-mail.', E_USER_WARNING);
            }
            if ($photoWarn) {
                trigger_error('Аватар: разрешены JPG/PNG/GIF/WEBP до 2 МБ.', E_USER_WARNING);
            }
        ?>
        <form method="POST" enctype="multipart/form-data">
            <input type="hidden" name="save_profile" value="1">
            <label>E-mail</label>
            <input type="text" name="email" value="<?php echo htmlspecialchars($emailInvalid ? ($_POST['email'] ?? '') : $user['email']); ?>">
            <label>Новое фото <small style="color:#888;">(опционально, JPG/PNG/GIF/WEBP до 2 МБ)</small></label>
            <input type="file" name="photo" accept="image/*">
            <div style="display: flex; gap: 10px; margin-top: 8px;">
                <button type="submit" class="button">Сохранить изменения</button>
                <a href="cabinet.php" class="button" style="background: var(--muted);">Отмена</a>
            </div>
        </form>
    </div>
    <?php endif; ?>

    <div class="stats-row">
        <div class="stat-box">
            <div class="val"><?php echo $totalOrders; ?></div>
            <div class="lbl">Заказов</div>
        </div>
        <div class="stat-box">
            <div class="val"><?php echo $totalBooks; ?></div>
            <div class="lbl">Книг куплено</div>
        </div>
        <div class="stat-box">
            <div class="val"><?php echo number_format($totalSpent, 0, '', ' '); ?> ₽</div>
            <div class="lbl">Потрачено</div>
        </div>
        <div class="stat-box">
            <div class="val"><?php echo $memberDays; ?></div>
            <div class="lbl">Дней с нами</div>
        </div>
    </div>

    <div class="orders-section">
        <h3>История заказов</h3>
        <?php if($orders->num_rows > 0): ?>
            <?php while($o = $orders->fetch_assoc()):
                $items = $conn->query("SELECT p.title, oi.quantity, oi.price_at_purchase FROM order_items oi JOIN products p ON oi.product_id = p.id WHERE oi.order_id = " . intval($o['id']));
            ?>
                <div class="order-item">
                    <div class="order-top">
                        <span class="order-id">Заказ #<?php echo $o['id']; ?></span>
                        <span class="order-date"><?php echo date('d.m.Y H:i', strtotime($o['order_date'])); ?></span>
                    </div>
                    <div class="order-books">
                        <?php while($item = $items->fetch_assoc()): ?>
                            <span><?php echo htmlspecialchars($item['title']); ?> × <?php echo $item['quantity']; ?></span>
                        <?php endwhile; ?>
                    </div>
                    <div class="order-total"><?php echo number_format($o['total_price'], 0, '', ' '); ?> ₽</div>
                </div>
            <?php endwhile; ?>
        <?php else: ?>
            <div class="empty-state">
                <p>📚</p>
                <p>Вы ещё не сделали ни одного заказа</p>
                <a href="products.php" class="button" style="margin-top: 12px;">Перейти в каталог</a>
            </div>
        <?php endif; ?>
    </div>
</section>
<?php require_once "includes/footer.php"; ?>

<?php
$pageTitle = "Панель управления - BookHaven";
require_once "includes/auth.php";

// Доступ только через отдельный вход в админку (admin_login.php)
if (empty($_SESSION['admin_auth']) || $_SESSION['admin_auth'] !== true || ($_SESSION['user_role'] ?? '') !== 'superadmin') {
    http_response_code(404);
    echo "Страница не найдена.";
    exit;
}

$tab = $_GET['tab'] ?? 'orders';
$msg = '';

// ---------- ОБРАБОТКА: ЗАКАЗЫ ----------
if ($tab === 'orders' && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['order_id'])) {
    $orderId = intval($_POST['order_id']);
    if (isset($_POST['delete_item'])) {
        $delItem = intval($_POST['delete_item']);
        $stmt = $conn->prepare("DELETE FROM order_items WHERE id = ?");
        $stmt->bind_param("i", $delItem); $stmt->execute(); $stmt->close();
        $conn->query("UPDATE orders SET total_price = (SELECT COALESCE(SUM(price_at_purchase * quantity), 0) FROM order_items WHERE order_id = $orderId) WHERE id = $orderId");
        $msg = "Позиция удалена.";
    } elseif (isset($_POST['item_id'])) {
        $itemId = intval($_POST['item_id']);
        $newQty = intval($_POST['quantity'] ?? 1);
        if ($itemId > 0 && $newQty > 0) {
            $stmt = $conn->prepare("UPDATE order_items SET quantity = ? WHERE id = ?");
            $stmt->bind_param("ii", $newQty, $itemId); $stmt->execute(); $stmt->close();
            $conn->query("UPDATE orders SET total_price = (SELECT COALESCE(SUM(price_at_purchase * quantity), 0) FROM order_items WHERE order_id = $orderId) WHERE id = $orderId");
            $msg = "Заказ обновлён.";
        }
    }
}

// ---------- ОБРАБОТКА: КНИГИ ----------
if ($tab === 'books' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    // Добавление
    if (isset($_POST['action']) && $_POST['action'] === 'add') {
        $title = trim($_POST['title'] ?? '');
        $author = trim($_POST['author'] ?? '');
        $category = trim($_POST['category'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $isbn = trim($_POST['isbn'] ?? '');
        $price = floatval($_POST['price'] ?? 0);
        if ($title && $author && $category) {
            $stmt = $conn->prepare("INSERT INTO products (title, author, category, description, isbn, price) VALUES (?, ?, ?, ?, ?, ?)");
            $stmt->bind_param("sssssd", $title, $author, $category, $description, $isbn, $price);
            $stmt->execute(); $stmt->close();
            $msg = "Книга «{$title}» добавлена.";
        } else { $msg = "Заполните название, автора и жанр."; }
    }
    // Редактирование
    elseif (isset($_POST['action']) && $_POST['action'] === 'edit' && isset($_POST['save_id'])) {
        $id = intval($_POST['save_id']);
        $title = trim($_POST['title'] ?? '');
        $author = trim($_POST['author'] ?? '');
        $category = trim($_POST['category'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $isbn = trim($_POST['isbn'] ?? '');
        $price = floatval($_POST['price'] ?? 0);
        if ($title && $author && $category && $id > 0) {
            $stmt = $conn->prepare("UPDATE products SET title=?, author=?, category=?, description=?, isbn=?, price=? WHERE id=?");
            $stmt->bind_param("sssssdi", $title, $author, $category, $description, $isbn, $price, $id);
            $stmt->execute(); $stmt->close();
            $msg = "Книга обновлена.";
        }
    }
    // Массовое обновление цен по выбранным
    elseif (isset($_POST['action']) && $_POST['action'] === 'mass_price') {
        $ids = $_POST['book_ids'] ?? [];
        $mode = $_POST['price_mode'] ?? 'set';
        $value = floatval($_POST['mass_value'] ?? 0);
        $ids = array_filter(array_map('intval', (array)$ids));
        if (!empty($ids) && $value !== 0.0 || (!empty($ids) && $mode === 'set')) {
            $in = implode(',', $ids);
            if ($mode === 'set') {
                $stmt = $conn->prepare("UPDATE products SET price = ? WHERE id IN ($in)");
                $stmt->bind_param("d", $value); $stmt->execute(); $stmt->close();
            } elseif ($mode === 'percent') {
                $factor = 1 + ($value / 100);
                $stmt = $conn->prepare("UPDATE products SET price = ROUND(price * ?, 2) WHERE id IN ($in)");
                $stmt->bind_param("d", $factor); $stmt->execute(); $stmt->close();
            }
            $msg = "Цены обновлены для " . count($ids) . " книг(и).";
        } else { $msg = "Выберите книги и укажите значение."; }
    }
}
// Удаление книги
if ($tab === 'books' && isset($_GET['delete'])) {
    $delId = intval($_GET['delete']);
    if ($delId > 0) {
        $stmt = $conn->prepare("DELETE FROM products WHERE id = ?");
        $stmt->bind_param("i", $delId); $stmt->execute(); $stmt->close();
        $msg = "Книга удалена.";
    }
}

// ---------- ОБРАБОТКА: ПОЛЬЗОВАТЕЛИ ----------
if ($tab === 'users' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    // Бан / разбан
    if (isset($_POST['action']) && $_POST['action'] === 'toggle_ban') {
        $uid = intval($_POST['user_id']);
        if ($uid !== intval($_SESSION['user_id'])) {
            $stmt = $conn->prepare("UPDATE users SET is_banned = 1 - is_banned WHERE id = ? AND role != 'superadmin'");
            $stmt->bind_param("i", $uid); $stmt->execute(); $stmt->close();
            $msg = "Статус пользователя изменён.";
        }
    }
    // Редактирование данных пользователя
    elseif (isset($_POST['action']) && $_POST['action'] === 'edit_user' && isset($_POST['edit_user_id'])) {
        $uid = intval($_POST['edit_user_id']);
        $newLogin = trim($_POST['login'] ?? '');
        $newEmail = trim($_POST['email'] ?? '');
        if ($uid > 0 && $newLogin !== '' && filter_var($newEmail, FILTER_VALIDATE_EMAIL)) {
            $stmt = $conn->prepare("UPDATE users SET login = ?, email = ? WHERE id = ? AND role != 'superadmin'");
            $stmt->bind_param("ssi", $newLogin, $newEmail, $uid);
            $stmt->execute(); $stmt->close();
            $msg = "Данные пользователя обновлены.";
        } else { $msg = "Проверьте логин и корректность email."; }
    }
}

// ---------- СТАТИСТИКА ----------
$totalBooks   = $conn->query("SELECT COUNT(*) as c FROM products")->fetch_assoc()['c'];
$totalUsers   = $conn->query("SELECT COUNT(*) as c FROM users")->fetch_assoc()['c'];
$totalOrders  = $conn->query("SELECT COUNT(*) as c FROM orders")->fetch_assoc()['c'];
$totalRevenue = $conn->query("SELECT COALESCE(SUM(total_price), 0) as s FROM orders")->fetch_assoc()['s'];

require_once "includes/header.php";
?>

<style>
.admin-wrap { max-width: 1000px; margin: 0 auto; }
.admin-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 24px; padding-bottom: 16px; border-bottom: 2px solid var(--border); }
.admin-header h2 { color: var(--accent); margin: 0; font-size: 24px; }
.admin-user { color: var(--muted); font-size: 13px; display: flex; align-items: center; gap: 8px; }
.admin-user a { color: var(--accent); text-decoration: none; }
.admin-user a:hover { text-decoration: underline; }
.admin-badge { display: inline-block; padding: 3px 8px; border-radius: 12px; font-size: 11px; font-weight: bold; background: var(--accent); color: #fff; }

.admin-tabs { display: flex; gap: 4px; margin-bottom: 24px; background: #f5f0ea; border-radius: 8px; padding: 4px; }
.admin-tab { padding: 10px 18px; text-decoration: none; font-weight: bold; font-size: 13px; border-radius: 6px; color: var(--muted); transition: all 0.2s; }
.admin-tab:hover { color: var(--text); background: rgba(255,255,255,0.5); }
.admin-tab.active { background: #fff; color: var(--accent); box-shadow: 0 1px 3px rgba(0,0,0,0.08); }

.stats-grid { display: grid; grid-template-columns: repeat(4, 1fr); gap: 12px; margin-bottom: 24px; }
.stat-card { background: #fff; border: 1px solid var(--border); border-radius: 8px; padding: 16px; text-align: center; }
.stat-value { font-size: 24px; font-weight: bold; color: var(--accent); margin-bottom: 4px; }
.stat-label { font-size: 12px; color: var(--muted); text-transform: uppercase; letter-spacing: 0.5px; }

.admin-card { background: #fff; border: 1px solid var(--border); border-radius: 8px; padding: 20px; margin-bottom: 16px; box-shadow: 0 1px 4px rgba(0,0,0,0.04); }
.admin-card h3 { color: var(--accent); margin: 0 0 16px; font-size: 18px; }

.filter-bar { display: flex; gap: 12px; align-items: flex-end; flex-wrap: wrap; padding: 16px; background: #fdfaf6; border-radius: 8px; margin-bottom: 16px; }
.filter-bar label { font-size: 12px; color: var(--muted); margin-bottom: 2px; display: block; }
.filter-bar input, .filter-bar select { margin: 0; }

.btn-sm { padding: 6px 12px; font-size: 12px; border-radius: 4px; }
.btn-danger { background: #e74c3c; } .btn-danger:hover { background: #c0392b; }
.btn-blue { background: #2980b9; } .btn-blue:hover { background: #1f6fa5; }
.btn-gray { background: var(--muted); }
.btn-green { background: #27ae60; } .btn-green:hover { background: #1f8e4d; }

.user-role-badge { display: inline-block; padding: 3px 10px; border-radius: 12px; font-size: 11px; font-weight: bold; }
.role-superadmin { background: #fdeaea; color: var(--accent); }
.role-user { background: #f0f0f0; color: var(--muted); }
.status-active { color: #27ae60; font-weight: bold; }
.status-banned { color: #e74c3c; font-weight: bold; }

table { font-size: 14px; }
table th { font-size: 12px; text-transform: uppercase; letter-spacing: 0.3px; color: var(--muted); background: #fdfaf6; }

.order-row { display: flex; justify-content: space-between; align-items: center; padding: 14px 16px; border: 1px solid var(--border); border-radius: 8px; margin-bottom: 8px; transition: box-shadow 0.2s; }
.order-row:hover { box-shadow: 0 2px 8px rgba(0,0,0,0.06); }
.order-row .o-client { font-weight: bold; color: var(--accent); }
.order-row .o-meta { color: var(--muted); font-size: 13px; }
.order-details { border: 1px solid var(--border); border-top: none; border-radius: 0 0 8px 8px; padding: 16px; margin-top: -8px; margin-bottom: 8px; background: #fdfaf6; }
.order-details.hidden { display: none; }
.inline-form { display: inline; margin: 0; }
.mass-bar { display: flex; gap: 10px; align-items: flex-end; flex-wrap: wrap; padding: 14px; background: #fdfaf6; border-radius: 8px; margin-bottom: 14px; }
</style>

<section class="admin-wrap">
    <div class="admin-header">
        <h2>Панель управления</h2>
        <div class="admin-user">
            <span class="admin-badge">Super Admin</span>
            <?php echo htmlspecialchars($_SESSION['user_login']); ?>
            <a href="admin_logout.php">Выход</a>
        </div>
    </div>

    <div class="stats-grid">
        <div class="stat-card"><div class="stat-value"><?php echo $totalBooks; ?></div><div class="stat-label">Книг</div></div>
        <div class="stat-card"><div class="stat-value"><?php echo $totalUsers; ?></div><div class="stat-label">Пользователей</div></div>
        <div class="stat-card"><div class="stat-value"><?php echo $totalOrders; ?></div><div class="stat-label">Заказов</div></div>
        <div class="stat-card"><div class="stat-value"><?php echo number_format($totalRevenue, 0, '', ' '); ?> ₽</div><div class="stat-label">Выручка</div></div>
    </div>

    <div class="admin-tabs">
        <a href="admin.php?tab=orders" class="admin-tab <?php echo $tab === 'orders' ? 'active' : ''; ?>">Заказы</a>
        <a href="admin.php?tab=books"  class="admin-tab <?php echo $tab === 'books'  ? 'active' : ''; ?>">Список книг</a>
        <a href="admin.php?tab=users"  class="admin-tab <?php echo $tab === 'users'  ? 'active' : ''; ?>">Пользователи</a>
    </div>

    <?php if ($msg): ?>
        <div class="notice" style="border-radius: 8px; margin-bottom: 16px;"><?php echo htmlspecialchars($msg); ?></div>
    <?php endif; ?>

    <?php /* ====================== ЗАКАЗЫ ====================== */ ?>
    <?php if ($tab === 'orders'): ?>
    <?php
        $filterDate = trim($_GET['date'] ?? '');
        $filterUser = intval($_GET['user_id'] ?? 0);

        $sql = "SELECT o.id, o.order_date, o.total_price, u.login AS client
                FROM orders o JOIN users u ON o.user_id = u.id WHERE 1=1";
        $params = []; $types = "";
        if ($filterDate !== '') { $sql .= " AND DATE(o.order_date) = ?"; $params[] = $filterDate; $types .= "s"; }
        if ($filterUser > 0)    { $sql .= " AND o.user_id = ?";          $params[] = $filterUser; $types .= "i"; }
        $sql .= " ORDER BY o.order_date DESC";

        $stmt = $conn->prepare($sql);
        if (!empty($params)) $stmt->bind_param($types, ...$params);
        $stmt->execute();
        $orders = $stmt->get_result();
        $users = $conn->query("SELECT id, login FROM users ORDER BY login");
    ?>
    <div class="filter-bar">
        <form method="GET" style="display: flex; gap: 12px; align-items: flex-end; flex-wrap: wrap; width: 100%;">
            <input type="hidden" name="tab" value="orders">
            <div><label>Дата</label><input type="date" name="date" value="<?php echo htmlspecialchars($filterDate); ?>" style="width: 160px;"></div>
            <div>
                <label>Пользователь</label>
                <select name="user_id" style="width: 160px;">
                    <option value="0">Все</option>
                    <?php while ($u = $users->fetch_assoc()): ?>
                        <option value="<?php echo $u['id']; ?>" <?php echo $filterUser == $u['id'] ? 'selected' : ''; ?>><?php echo htmlspecialchars($u['login']); ?></option>
                    <?php endwhile; ?>
                </select>
            </div>
            <button type="submit" class="button btn-sm">Показать</button>
            <a href="admin.php?tab=orders" class="button btn-sm btn-gray">Сбросить</a>
        </form>
    </div>

    <?php if ($orders->num_rows > 0): ?>
        <?php while ($o = $orders->fetch_assoc()): ?>
        <div class="order-row">
            <div>
                <span class="o-client"><?php echo htmlspecialchars($o['client']); ?></span>
                <span class="o-meta"> &nbsp;•&nbsp; Заказ #<?php echo $o['id']; ?> &nbsp;•&nbsp; <?php echo date('d.m.Y H:i', strtotime($o['order_date'])); ?></span>
            </div>
            <div style="display: flex; align-items: center; gap: 14px;">
                <strong><?php echo number_format($o['total_price'], 0, '', ' '); ?> ₽</strong>
                <button type="button" class="button btn-sm btn-blue" onclick="toggleOrder(<?php echo $o['id']; ?>)">Подробнее</button>
            </div>
        </div>
        <div class="order-details hidden" id="order-<?php echo $o['id']; ?>">
            <?php $items = $conn->query("SELECT oi.id, oi.quantity, oi.price_at_purchase, oi.order_id, p.title FROM order_items oi JOIN products p ON oi.product_id = p.id WHERE oi.order_id = " . intval($o['id'])); ?>
            <?php if ($items->num_rows > 0): ?>
            <table>
                <tr><th>Товар</th><th>Цена</th><th>Кол-во</th><th>Сумма</th><th>Действие</th></tr>
                <?php while ($item = $items->fetch_assoc()): ?>
                <tr>
                    <td><?php echo htmlspecialchars($item['title']); ?></td>
                    <td style="white-space: nowrap;"><?php echo number_format($item['price_at_purchase'], 0, '', ' '); ?> ₽</td>
                    <td>
                        <form method="POST" class="inline-form" style="display: flex; gap: 6px; align-items: center;">
                            <input type="hidden" name="order_id" value="<?php echo $item['order_id']; ?>">
                            <input type="hidden" name="item_id" value="<?php echo $item['id']; ?>">
                            <input type="number" name="quantity" value="<?php echo $item['quantity']; ?>" min="1" style="width: 60px; margin: 0; padding: 4px 6px;">
                            <button type="submit" class="button btn-sm">OK</button>
                        </form>
                    </td>
                    <td style="white-space: nowrap;"><?php echo number_format($item['price_at_purchase'] * $item['quantity'], 0, '', ' '); ?> ₽</td>
                    <td>
                        <form method="POST" class="inline-form">
                            <input type="hidden" name="order_id" value="<?php echo $item['order_id']; ?>">
                            <input type="hidden" name="delete_item" value="<?php echo $item['id']; ?>">
                            <button type="submit" class="button btn-sm btn-danger" onclick="return confirm('Удалить позицию?');">Удалить</button>
                        </form>
                    </td>
                </tr>
                <?php endwhile; ?>
            </table>
            <?php else: ?>
                <p style="color: var(--muted); margin: 0;">В заказе нет позиций.</p>
            <?php endif; ?>
        </div>
        <?php endwhile; ?>
    <?php else: ?>
        <div class="admin-card" style="text-align: center; color: var(--muted); padding: 40px;">
            <p style="font-size: 40px; margin: 0 0 10px;">📋</p>
            <p style="margin: 0;">Заказов не найдено</p>
        </div>
    <?php endif; ?>

    <?php /* ====================== СПИСОК КНИГ ====================== */ ?>
    <?php elseif ($tab === 'books'): ?>
    <?php
        $editId = intval($_GET['edit_id'] ?? 0);
        $fTitle = trim($_GET['f_title'] ?? '');
        $fAuthor = trim($_GET['f_author'] ?? '');
        $fCategory = trim($_GET['f_category'] ?? '');

        $sql = "SELECT id, title, author, category, description, isbn, price FROM products WHERE 1=1";
        $params = []; $types = "";
        if ($fTitle !== '')    { $sql .= " AND title LIKE ?";    $params[] = "%$fTitle%";    $types .= "s"; }
        if ($fAuthor !== '')   { $sql .= " AND author LIKE ?";   $params[] = "%$fAuthor%";   $types .= "s"; }
        if ($fCategory !== '') { $sql .= " AND category LIKE ?"; $params[] = "%$fCategory%"; $types .= "s"; }
        $sql .= " ORDER BY title";
        $stmt = $conn->prepare($sql);
        if (!empty($params)) $stmt->bind_param($types, ...$params);
        $stmt->execute();
        $products = $stmt->get_result();

        // данные для формы редактирования
        $book = null;
        if ($editId > 0) {
            $st = $conn->prepare("SELECT id, title, author, category, description, isbn, price FROM products WHERE id = ?");
            $st->bind_param("i", $editId); $st->execute();
            $book = $st->get_result()->fetch_assoc(); $st->close();
        }
    ?>

    <!-- Добавить книгу -->
    <div class="admin-card">
        <h3>Добавить книгу</h3>
        <form method="POST" style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px;">
            <input type="hidden" name="action" value="add">
            <div><label>Название</label><input type="text" name="title" required></div>
            <div><label>Автор</label><input type="text" name="author" required></div>
            <div><label>Категория (жанр)</label><input type="text" name="category" required></div>
            <div><label>ISBN</label><input type="text" name="isbn" placeholder="9785171529017"></div>
            <div style="grid-column: 1 / -1;"><label>Описание</label><textarea name="description" rows="2"></textarea></div>
            <div><label>Цена (руб.)</label><input type="number" name="price" step="0.01" min="0" required></div>
            <div style="display: flex; align-items: flex-end;"><button type="submit" class="button">Добавить книгу</button></div>
        </form>
    </div>

    <?php if ($book): ?>
    <!-- Форма редактирования -->
    <div class="admin-card" style="border-left: 4px solid var(--accent);">
        <h3>Редактировать: <?php echo htmlspecialchars($book['title']); ?></h3>
        <form method="POST" style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px;">
            <input type="hidden" name="action" value="edit">
            <input type="hidden" name="save_id" value="<?php echo $book['id']; ?>">
            <div><label>Название</label><input type="text" name="title" value="<?php echo htmlspecialchars($book['title']); ?>" required></div>
            <div><label>Автор</label><input type="text" name="author" value="<?php echo htmlspecialchars($book['author']); ?>" required></div>
            <div><label>Категория</label><input type="text" name="category" value="<?php echo htmlspecialchars($book['category']); ?>" required></div>
            <div><label>ISBN</label><input type="text" name="isbn" value="<?php echo htmlspecialchars($book['isbn'] ?? ''); ?>"></div>
            <div style="grid-column: 1 / -1;"><label>Описание</label><textarea name="description" rows="2"><?php echo htmlspecialchars($book['description']); ?></textarea></div>
            <div><label>Цена (руб.)</label><input type="number" name="price" step="0.01" min="0" value="<?php echo $book['price']; ?>" required></div>
            <div style="display: flex; align-items: flex-end; gap: 10px;">
                <button type="submit" class="button">Сохранить</button>
                <a href="admin.php?tab=books" class="button btn-gray">Отмена</a>
            </div>
        </form>
    </div>
    <?php endif; ?>

    <!-- Список книг + фильтры + массовое обновление цен -->
    <div class="admin-card">
        <h3>Все книги</h3>

        <div class="filter-bar">
            <form method="GET" style="display: flex; gap: 12px; align-items: flex-end; flex-wrap: wrap; width: 100%;">
                <input type="hidden" name="tab" value="books">
                <div><label>Название</label><input type="text" name="f_title" value="<?php echo htmlspecialchars($fTitle); ?>" style="width: 180px;"></div>
                <div><label>Автор</label><input type="text" name="f_author" value="<?php echo htmlspecialchars($fAuthor); ?>" style="width: 150px;"></div>
                <div><label>Жанр</label><input type="text" name="f_category" value="<?php echo htmlspecialchars($fCategory); ?>" style="width: 130px;"></div>
                <button type="submit" class="button btn-sm">Фильтр</button>
                <a href="admin.php?tab=books" class="button btn-sm btn-gray">Сбросить</a>
            </form>
        </div>

        <form method="POST" id="massForm">
            <input type="hidden" name="action" value="mass_price">
            <div class="mass-bar">
                <div>
                    <label>Массовое изменение цены</label>
                    <select name="price_mode" style="width: 220px; margin: 0;">
                        <option value="set">Установить цену (₽)</option>
                        <option value="percent">Изменить на (%)</option>
                    </select>
                </div>
                <div><label>Значение</label><input type="number" name="mass_value" step="0.01" style="width: 130px; margin: 0;" placeholder="напр. 500 или -10"></div>
                <button type="submit" class="button btn-green btn-sm" onclick="return confirm('Применить к выбранным книгам?');">Применить к выбранным</button>
                <span style="color: var(--muted); font-size: 12px;">Отметьте книги флажками ниже</span>
            </div>

            <table>
                <tr>
                    <th style="width: 30px;"><input type="checkbox" id="checkAll" onclick="toggleAll(this)" style="margin: 0; width: auto;"></th>
                    <th>ID</th><th>Название</th><th>Автор</th><th>Жанр</th><th>ISBN</th><th>Цена</th><th>Действия</th>
                </tr>
                <?php while ($p = $products->fetch_assoc()): ?>
                <tr>
                    <td><input type="checkbox" name="book_ids[]" value="<?php echo $p['id']; ?>" class="bookChk" style="margin: 0; width: auto;"></td>
                    <td><?php echo $p['id']; ?></td>
                    <td style="font-weight: bold;"><?php echo htmlspecialchars($p['title']); ?></td>
                    <td><?php echo htmlspecialchars($p['author']); ?></td>
                    <td><span style="background: #f0ebe5; padding: 2px 8px; border-radius: 10px; font-size: 11px;"><?php echo htmlspecialchars($p['category']); ?></span></td>
                    <td style="font-size: 12px; color: var(--muted);"><?php echo htmlspecialchars($p['isbn'] ?? ''); ?></td>
                    <td style="white-space: nowrap;"><?php echo number_format($p['price'], 0, '', ' '); ?> ₽</td>
                    <td style="white-space: nowrap;">
                        <a href="admin.php?tab=books&edit_id=<?php echo $p['id']; ?>" class="button btn-sm">Ред.</a>
                        <a href="admin.php?tab=books&delete=<?php echo $p['id']; ?>" class="button btn-sm btn-danger" onclick="return confirm('Удалить книгу?');">Удалить</a>
                    </td>
                </tr>
                <?php endwhile; ?>
            </table>
        </form>
    </div>

    <?php /* ====================== ПОЛЬЗОВАТЕЛИ ====================== */ ?>
    <?php elseif ($tab === 'users'): ?>
    <?php
        $editUserId = intval($_GET['edit_user'] ?? 0);
        $allUsers = $conn->query("SELECT id, login, email, role, reg_date, is_banned FROM users ORDER BY role DESC, login");
        $editUser = null;
        if ($editUserId > 0) {
            $st = $conn->prepare("SELECT id, login, email, role FROM users WHERE id = ?");
            $st->bind_param("i", $editUserId); $st->execute();
            $editUser = $st->get_result()->fetch_assoc(); $st->close();
        }
    ?>

    <?php if ($editUser && $editUser['role'] !== 'superadmin'): ?>
    <div class="admin-card" style="border-left: 4px solid var(--accent);">
        <h3>Редактировать пользователя: <?php echo htmlspecialchars($editUser['login']); ?></h3>
        <form method="POST" style="display: grid; grid-template-columns: 1fr 1fr auto; gap: 12px; align-items: flex-end;">
            <input type="hidden" name="action" value="edit_user">
            <input type="hidden" name="edit_user_id" value="<?php echo $editUser['id']; ?>">
            <div><label>Логин</label><input type="text" name="login" value="<?php echo htmlspecialchars($editUser['login']); ?>" required></div>
            <div><label>Email</label><input type="email" name="email" value="<?php echo htmlspecialchars($editUser['email']); ?>" required></div>
            <div style="display: flex; gap: 10px;">
                <button type="submit" class="button">Сохранить</button>
                <a href="admin.php?tab=users" class="button btn-gray">Отмена</a>
            </div>
        </form>
    </div>
    <?php endif; ?>

    <div class="admin-card">
        <h3>Управление пользователями</h3>
        <table>
            <tr><th>ID</th><th>Логин</th><th>Email</th><th>Роль</th><th>Регистрация</th><th>Статус</th><th>Действия</th></tr>
            <?php while ($u = $allUsers->fetch_assoc()): ?>
            <tr>
                <td><?php echo $u['id']; ?></td>
                <td style="font-weight: bold;"><?php echo htmlspecialchars($u['login']); ?></td>
                <td><?php echo htmlspecialchars($u['email']); ?></td>
                <td>
                    <?php if ($u['role'] === 'superadmin'): ?>
                        <span class="user-role-badge role-superadmin">Главный админ</span>
                    <?php else: ?>
                        <span class="user-role-badge role-user">Пользователь</span>
                    <?php endif; ?>
                </td>
                <td><?php echo date('d.m.Y', strtotime($u['reg_date'])); ?></td>
                <td>
                    <?php if (!empty($u['is_banned'])): ?>
                        <span class="status-banned">Заблокирован</span>
                    <?php else: ?>
                        <span class="status-active">Активен</span>
                    <?php endif; ?>
                </td>
                <td style="white-space: nowrap;">
                    <?php if ($u['role'] !== 'superadmin'): ?>
                        <a href="admin.php?tab=users&edit_user=<?php echo $u['id']; ?>" class="button btn-sm">Ред.</a>
                        <form method="POST" class="inline-form">
                            <input type="hidden" name="action" value="toggle_ban">
                            <input type="hidden" name="user_id" value="<?php echo $u['id']; ?>">
                            <?php if (!empty($u['is_banned'])): ?>
                                <button type="submit" class="button btn-sm btn-green">Разбанить</button>
                            <?php else: ?>
                                <button type="submit" class="button btn-sm btn-danger" onclick="return confirm('Заблокировать пользователя?');">Забанить</button>
                            <?php endif; ?>
                        </form>
                    <?php else: ?>
                        <span style="color: var(--muted);">—</span>
                    <?php endif; ?>
                </td>
            </tr>
            <?php endwhile; ?>
        </table>
    </div>
    <?php endif; ?>
</section>

<script>
function toggleOrder(id) {
    var el = document.getElementById('order-' + id);
    if (el) el.classList.toggle('hidden');
}
function toggleAll(src) {
    document.querySelectorAll('.bookChk').forEach(function(c){ c.checked = src.checked; });
}
</script>

<?php require_once "includes/footer.php"; ?>

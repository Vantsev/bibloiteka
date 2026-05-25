<?php
$pageTitle = "Админ-панель - BookHaven";
require_once "includes/auth.php";

$role = $_SESSION['user_role'] ?? 'user';
if (!in_array($role, ['admin', 'superadmin'])) {
    header("Location: login.php"); exit;
}

$tab = $_GET['tab'] ?? 'orders';
$msg = '';

// Назначение/снятие админа (только superadmin)
if ($tab === 'users' && $role === 'superadmin' && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['toggle_user_id'])) {
    $toggleId = intval($_POST['toggle_user_id']);
    $newRole = trim($_POST['new_role'] ?? 'user');
    if (in_array($newRole, ['user', 'admin']) && $toggleId !== intval($_SESSION['user_id'])) {
        $stmt = $conn->prepare("UPDATE users SET role = ? WHERE id = ? AND role != 'superadmin'");
        $stmt->bind_param("si", $newRole, $toggleId);
        $stmt->execute();
        $stmt->close();
        $msg = "Роль пользователя обновлена.";
    }
}

// Добавление книги
if ($tab === 'add' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = trim($_POST['title'] ?? '');
    $author = trim($_POST['author'] ?? '');
    $category = trim($_POST['category'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $isbn = trim($_POST['isbn'] ?? '');
    $price = floatval($_POST['price'] ?? 0);
    if ($title && $author && $category) {
        $stmt = $conn->prepare("INSERT INTO products (title, author, category, description, isbn, price) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("sssssd", $title, $author, $category, $description, $isbn, $price);
        $stmt->execute();
        $stmt->close();
        $msg = "Книга «{$title}» добавлена.";
    }
}

// Редактирование книги
if ($tab === 'edit' && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_id'])) {
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
        $stmt->execute();
        $stmt->close();
        $msg = "Книга обновлена.";
    }
}

// Удаление книги
if ($tab === 'edit' && isset($_GET['delete'])) {
    $delId = intval($_GET['delete']);
    if ($delId > 0) {
        $stmt = $conn->prepare("DELETE FROM products WHERE id = ?");
        $stmt->bind_param("i", $delId);
        $stmt->execute();
        $stmt->close();
        $msg = "Книга удалена.";
    }
}

// Обновление заказа
if ($tab === 'update' && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['order_id'])) {
    $orderId = intval($_POST['order_id']);
    $itemId = intval($_POST['item_id'] ?? 0);
    if (isset($_POST['delete_item'])) {
        $delItem = intval($_POST['delete_item']);
        $conn->query("DELETE FROM order_items WHERE id = $delItem");
        $conn->query("UPDATE orders SET total_price = (SELECT COALESCE(SUM(price_at_purchase * quantity), 0) FROM order_items WHERE order_id = $orderId) WHERE id = $orderId");
        $msg = "Позиция удалена.";
    } elseif ($itemId > 0) {
        $newQty = intval($_POST['quantity'] ?? 1);
        if ($newQty > 0) {
            $stmt = $conn->prepare("UPDATE order_items SET quantity = ? WHERE id = ?");
            $stmt->bind_param("ii", $newQty, $itemId);
            $stmt->execute();
            $stmt->close();
            $conn->query("UPDATE orders SET total_price = (SELECT COALESCE(SUM(price_at_purchase * quantity), 0) FROM order_items WHERE order_id = $orderId) WHERE id = $orderId");
            $msg = "Заказ обновлён.";
        }
    }
}

// Статистика для дашборда
$totalBooks = $conn->query("SELECT COUNT(*) as c FROM products")->fetch_assoc()['c'];
$totalUsers = $conn->query("SELECT COUNT(*) as c FROM users")->fetch_assoc()['c'];
$totalOrders = $conn->query("SELECT COUNT(*) as c FROM orders")->fetch_assoc()['c'];
$totalRevenue = $conn->query("SELECT COALESCE(SUM(total_price), 0) as s FROM orders")->fetch_assoc()['s'];

require_once "includes/header.php";
?>

<style>
.admin-wrap { max-width: 960px; margin: 0 auto; }
.admin-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 24px; padding-bottom: 16px; border-bottom: 2px solid var(--border); }
.admin-header h2 { color: var(--accent); margin: 0; font-size: 24px; }
.admin-user { color: var(--muted); font-size: 13px; display: flex; align-items: center; gap: 8px; }
.admin-user a { color: var(--accent); text-decoration: none; }
.admin-user a:hover { text-decoration: underline; }
.admin-badge { display: inline-block; padding: 3px 8px; border-radius: 12px; font-size: 11px; font-weight: bold; }
.badge-super { background: var(--accent); color: #fff; }
.badge-admin { background: #2980b9; color: #fff; }

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

.order-card { border: 1px solid var(--border); border-radius: 8px; padding: 16px; margin-bottom: 12px; transition: box-shadow 0.2s; }
.order-card:hover { box-shadow: 0 2px 8px rgba(0,0,0,0.06); }
.order-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 12px; padding-bottom: 8px; border-bottom: 1px solid var(--border); }
.order-client { font-weight: bold; color: var(--accent); font-size: 15px; }
.order-date { color: var(--muted); font-size: 13px; }
.order-total { text-align: right; margin-top: 10px; font-weight: bold; font-size: 15px; color: var(--text); }

.btn-sm { padding: 6px 12px; font-size: 12px; border-radius: 4px; }
.btn-danger { background: #e74c3c; }
.btn-danger:hover { background: #c0392b; }
.btn-blue { background: #2980b9; }
.btn-blue:hover { background: #1f6fa5; }
.btn-gray { background: var(--muted); }

.user-role-badge { display: inline-block; padding: 3px 10px; border-radius: 12px; font-size: 11px; font-weight: bold; }
.role-superadmin { background: #fdeaea; color: var(--accent); }
.role-admin { background: #e8f4fd; color: #2980b9; }
.role-user { background: #f0f0f0; color: var(--muted); }

table { font-size: 14px; }
table th { font-size: 12px; text-transform: uppercase; letter-spacing: 0.3px; color: var(--muted); background: #fdfaf6; }
</style>

<section class="admin-wrap">
    <div class="admin-header">
        <h2>Админ-панель</h2>
        <div class="admin-user">
            <span class="admin-badge <?php echo $role === 'superadmin' ? 'badge-super' : 'badge-admin'; ?>">
                <?php echo $role === 'superadmin' ? 'Super Admin' : 'Admin'; ?>
            </span>
            <?php echo htmlspecialchars($_SESSION['user_login']); ?>
            <a href="cabinet.php">Кабинет</a>
            <a href="logout.php">Выход</a>
        </div>
    </div>

    <div class="stats-grid">
        <div class="stat-card">
            <div class="stat-value"><?php echo $totalBooks; ?></div>
            <div class="stat-label">Книг</div>
        </div>
        <div class="stat-card">
            <div class="stat-value"><?php echo $totalUsers; ?></div>
            <div class="stat-label">Пользователей</div>
        </div>
        <div class="stat-card">
            <div class="stat-value"><?php echo $totalOrders; ?></div>
            <div class="stat-label">Заказов</div>
        </div>
        <div class="stat-card">
            <div class="stat-value"><?php echo number_format($totalRevenue, 0, '', ' '); ?> ₽</div>
            <div class="stat-label">Выручка</div>
        </div>
    </div>

    <div class="admin-tabs">
        <a href="admin.php?tab=orders" class="admin-tab <?php echo $tab === 'orders' ? 'active' : ''; ?>">Заказы</a>
        <a href="admin.php?tab=add" class="admin-tab <?php echo $tab === 'add' ? 'active' : ''; ?>">Добавление</a>
        <a href="admin.php?tab=edit" class="admin-tab <?php echo $tab === 'edit' ? 'active' : ''; ?>">Редактирование</a>
        <a href="admin.php?tab=update" class="admin-tab <?php echo $tab === 'update' ? 'active' : ''; ?>">Обновление</a>
        <?php if ($role === 'superadmin'): ?>
        <a href="admin.php?tab=users" class="admin-tab <?php echo $tab === 'users' ? 'active' : ''; ?>">Пользователи</a>
        <?php endif; ?>
    </div>

    <?php if ($msg): ?>
        <div class="notice" style="border-radius: 8px; margin-bottom: 16px;"><?php echo htmlspecialchars($msg); ?></div>
    <?php endif; ?>

    <?php if ($tab === 'orders'): ?>
    <?php
        $filterDate = trim($_GET['date'] ?? '');
        $filterUser = intval($_GET['user_id'] ?? 0);

        $sql = "SELECT o.id, o.order_date, o.total_price, u.login AS client
                FROM orders o
                JOIN users u ON o.user_id = u.id
                WHERE 1=1";
        $params = []; $types = "";

        if ($filterDate !== '') {
            $sql .= " AND DATE(o.order_date) = ?";
            $params[] = $filterDate; $types .= "s";
        }
        if ($filterUser > 0) {
            $sql .= " AND o.user_id = ?";
            $params[] = $filterUser; $types .= "i";
        }
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
            <div>
                <label>Дата</label>
                <input type="date" name="date" value="<?php echo htmlspecialchars($filterDate); ?>" style="width: 160px;">
            </div>
            <div>
                <label>Пользователь</label>
                <select name="user_id" style="width: 160px;">
                    <option value="0">Все</option>
                    <?php while ($u = $users->fetch_assoc()): ?>
                        <option value="<?php echo $u['id']; ?>" <?php echo $filterUser == $u['id'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($u['login']); ?>
                        </option>
                    <?php endwhile; ?>
                </select>
            </div>
            <button type="submit" class="button btn-sm">Показать</button>
            <a href="admin.php?tab=orders" class="button btn-sm btn-gray">Сбросить</a>
        </form>
    </div>

    <?php if ($orders->num_rows > 0): ?>
        <?php while ($o = $orders->fetch_assoc()):
            $items = $conn->query("SELECT oi.quantity, oi.price_at_purchase, p.title FROM order_items oi JOIN products p ON oi.product_id = p.id WHERE oi.order_id = " . intval($o['id']));
        ?>
        <div class="order-card">
            <div class="order-header">
                <span class="order-client"><?php echo htmlspecialchars($o['client']); ?></span>
                <span class="order-date"><?php echo date('d.m.Y H:i', strtotime($o['order_date'])); ?></span>
            </div>
            <table>
                <tr><th>Товар</th><th>Цена</th><th>Кол-во</th><th>Сумма</th></tr>
                <?php while ($item = $items->fetch_assoc()): ?>
                <tr>
                    <td><?php echo htmlspecialchars($item['title']); ?></td>
                    <td><?php echo number_format($item['price_at_purchase'], 0, '', ' '); ?> ₽</td>
                    <td><?php echo $item['quantity']; ?></td>
                    <td><?php echo number_format($item['price_at_purchase'] * $item['quantity'], 0, '', ' '); ?> ₽</td>
                </tr>
                <?php endwhile; ?>
            </table>
            <div class="order-total">Итого: <?php echo number_format($o['total_price'], 0, '', ' '); ?> ₽</div>
        </div>
        <?php endwhile; ?>
    <?php else: ?>
        <div class="admin-card" style="text-align: center; color: var(--muted); padding: 40px;">
            <p style="font-size: 40px; margin: 0 0 10px;">📋</p>
            <p style="margin: 0;">Заказов не найдено</p>
        </div>
    <?php endif; ?>

    <?php elseif ($tab === 'add'): ?>
    <div class="admin-card">
        <h3>Добавить книгу</h3>
        <form method="POST" style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px;">
            <div>
                <label>Название</label>
                <input type="text" name="title" required>
            </div>
            <div>
                <label>Автор</label>
                <input type="text" name="author" required>
            </div>
            <div>
                <label>Категория (жанр)</label>
                <input type="text" name="category" required>
            </div>
            <div>
                <label>ISBN</label>
                <input type="text" name="isbn" placeholder="9785171529017">
            </div>
            <div style="grid-column: 1 / -1;">
                <label>Описание</label>
                <textarea name="description" rows="3"></textarea>
            </div>
            <div>
                <label>Цена (руб.)</label>
                <input type="number" name="price" step="0.01" min="0" required>
            </div>
            <div style="display: flex; align-items: flex-end;">
                <button type="submit" class="button">Добавить книгу</button>
            </div>
        </form>
    </div>

    <div class="admin-card">
        <h3>Автозаполнение</h3>
        <p style="font-size: 13px; color: var(--muted); margin: 0 0 12px;">Загрузить краткие описания из русской Википедии для книг без описания.</p>
        <a href="fetch_descriptions.php" class="button btn-blue">Загрузить описания из Wikipedia</a>
    </div>

    <?php elseif ($tab === 'edit'): ?>
    <?php
        $editId = intval($_GET['edit_id'] ?? 0);
        $products = $conn->query("SELECT id, title, author, category, description, isbn, price FROM products ORDER BY title");
    ?>
    <?php if ($editId > 0):
        $stmt = $conn->prepare("SELECT id, title, author, category, description, isbn, price FROM products WHERE id = ?");
        $stmt->bind_param("i", $editId);
        $stmt->execute();
        $book = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        if ($book):
    ?>
    <div class="admin-card" style="border-left: 4px solid var(--accent);">
        <h3>Редактировать: <?php echo htmlspecialchars($book['title']); ?></h3>
        <form method="POST" style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px;">
            <input type="hidden" name="save_id" value="<?php echo $book['id']; ?>">
            <div>
                <label>Название</label>
                <input type="text" name="title" value="<?php echo htmlspecialchars($book['title']); ?>" required>
            </div>
            <div>
                <label>Автор</label>
                <input type="text" name="author" value="<?php echo htmlspecialchars($book['author']); ?>" required>
            </div>
            <div>
                <label>Категория</label>
                <input type="text" name="category" value="<?php echo htmlspecialchars($book['category']); ?>" required>
            </div>
            <div>
                <label>ISBN</label>
                <input type="text" name="isbn" value="<?php echo htmlspecialchars($book['isbn'] ?? ''); ?>">
            </div>
            <div style="grid-column: 1 / -1;">
                <label>Описание</label>
                <textarea name="description" rows="3"><?php echo htmlspecialchars($book['description']); ?></textarea>
            </div>
            <div>
                <label>Цена (руб.)</label>
                <input type="number" name="price" step="0.01" min="0" value="<?php echo $book['price']; ?>" required>
            </div>
            <div style="display: flex; align-items: flex-end; gap: 10px;">
                <button type="submit" class="button">Сохранить</button>
                <a href="admin.php?tab=edit" class="button btn-gray">Отмена</a>
            </div>
        </form>
    </div>
    <?php endif; endif; ?>

    <div class="admin-card">
        <h3>Все книги</h3>
        <table>
            <tr><th>ID</th><th>Название</th><th>Автор</th><th>Жанр</th><th>Цена</th><th>Действия</th></tr>
            <?php while ($p = $products->fetch_assoc()): ?>
            <tr>
                <td><?php echo $p['id']; ?></td>
                <td style="font-weight: bold;"><?php echo htmlspecialchars($p['title']); ?></td>
                <td><?php echo htmlspecialchars($p['author']); ?></td>
                <td><span style="background: #f0ebe5; padding: 2px 8px; border-radius: 10px; font-size: 11px;"><?php echo htmlspecialchars($p['category']); ?></span></td>
                <td style="white-space: nowrap;"><?php echo number_format($p['price'], 0, '', ' '); ?> ₽</td>
                <td style="white-space: nowrap;">
                    <a href="admin.php?tab=edit&edit_id=<?php echo $p['id']; ?>" class="button btn-sm" style="text-decoration: none;">Ред.</a>
                    <a href="admin.php?tab=edit&delete=<?php echo $p['id']; ?>" class="button btn-sm btn-danger" style="text-decoration: none;" onclick="return confirm('Удалить книгу?');">Удалить</a>
                </td>
            </tr>
            <?php endwhile; ?>
        </table>
    </div>

    <?php elseif ($tab === 'update'): ?>
    <?php
        $allOrders = $conn->query("SELECT o.id, o.order_date, o.total_price, u.login FROM orders o JOIN users u ON o.user_id = u.id ORDER BY o.order_date DESC");
    ?>
    <div class="admin-card">
        <h3>Обновление позиций заказов</h3>
        <?php if ($allOrders->num_rows > 0): ?>
            <?php while ($o = $allOrders->fetch_assoc()):
                $items = $conn->query("SELECT oi.id, oi.quantity, oi.price_at_purchase, oi.order_id, p.title FROM order_items oi JOIN products p ON oi.product_id = p.id WHERE oi.order_id = " . intval($o['id']));
            ?>
            <div class="order-card">
                <div class="order-header">
                    <span class="order-client"><?php echo htmlspecialchars($o['login']); ?></span>
                    <span class="order-date"><?php echo date('d.m.Y', strtotime($o['order_date'])); ?> &bull; <?php echo number_format($o['total_price'], 0, '', ' '); ?> ₽</span>
                </div>
                <table>
                    <tr><th>Товар</th><th>Цена</th><th>Кол-во</th><th>Действие</th></tr>
                    <?php while ($item = $items->fetch_assoc()): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($item['title']); ?></td>
                        <td style="white-space: nowrap;"><?php echo number_format($item['price_at_purchase'], 0, '', ' '); ?> ₽</td>
                        <td>
                            <form method="POST" style="display: flex; gap: 6px; align-items: center; margin: 0;">
                                <input type="hidden" name="order_id" value="<?php echo $item['order_id']; ?>">
                                <input type="hidden" name="item_id" value="<?php echo $item['id']; ?>">
                                <input type="number" name="quantity" value="<?php echo $item['quantity']; ?>" min="1" style="width: 55px; margin: 0; padding: 4px 6px;">
                        </td>
                        <td style="white-space: nowrap;">
                                <button type="submit" class="button btn-sm">OK</button>
                            </form>
                            <form method="POST" style="display: inline; margin: 0;">
                                <input type="hidden" name="order_id" value="<?php echo $item['order_id']; ?>">
                                <input type="hidden" name="delete_item" value="<?php echo $item['id']; ?>">
                                <button type="submit" class="button btn-sm btn-danger" onclick="return confirm('Удалить?');">X</button>
                            </form>
                        </td>
                    </tr>
                    <?php endwhile; ?>
                </table>
            </div>
            <?php endwhile; ?>
        <?php else: ?>
            <div style="text-align: center; color: var(--muted); padding: 30px;">
                <p style="font-size: 40px; margin: 0 0 10px;">📦</p>
                <p style="margin: 0;">Заказов пока нет</p>
            </div>
        <?php endif; ?>
    </div>

    <?php elseif ($tab === 'users' && $role === 'superadmin'): ?>
    <?php
        $allUsers = $conn->query("SELECT id, login, email, role, reg_date FROM users ORDER BY role DESC, login");
    ?>
    <div class="admin-card">
        <h3>Управление пользователями</h3>
        <table>
            <tr><th>ID</th><th>Логин</th><th>Email</th><th>Роль</th><th>Регистрация</th><th>Действие</th></tr>
            <?php while ($u = $allUsers->fetch_assoc()): ?>
            <tr>
                <td><?php echo $u['id']; ?></td>
                <td style="font-weight: bold;"><?php echo htmlspecialchars($u['login']); ?></td>
                <td><?php echo htmlspecialchars($u['email']); ?></td>
                <td>
                    <?php if ($u['role'] === 'superadmin'): ?>
                        <span class="user-role-badge role-superadmin">Главный админ</span>
                    <?php elseif ($u['role'] === 'admin'): ?>
                        <span class="user-role-badge role-admin">Админ</span>
                    <?php else: ?>
                        <span class="user-role-badge role-user">Пользователь</span>
                    <?php endif; ?>
                </td>
                <td><?php echo date('d.m.Y', strtotime($u['reg_date'])); ?></td>
                <td>
                    <?php if ($u['role'] !== 'superadmin'): ?>
                        <form method="POST" style="display: inline; margin: 0;">
                            <input type="hidden" name="toggle_user_id" value="<?php echo $u['id']; ?>">
                            <?php if ($u['role'] === 'admin'): ?>
                                <input type="hidden" name="new_role" value="user">
                                <button type="submit" class="button btn-sm btn-danger" onclick="return confirm('Снять роль админа?');">Снять</button>
                            <?php else: ?>
                                <input type="hidden" name="new_role" value="admin">
                                <button type="submit" class="button btn-sm btn-blue">Назначить</button>
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
<?php require_once "includes/footer.php"; ?>

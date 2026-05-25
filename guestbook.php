<?php
$pageTitle = "Отзывы - BookHaven";
require_once "includes/auth.php";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $author = trim($_POST['author'] ?? '');
    $msg = trim($_POST['message'] ?? '');
    $bookId = intval($_POST['book_id'] ?? 0);
    if ($author && $msg) {
        if ($bookId > 0) {
            $stmt = $conn->prepare("INSERT INTO guestbook (author, book_id, message) VALUES (?, ?, ?)");
            $stmt->bind_param("sis", $author, $bookId, $msg);
        } else {
            $stmt = $conn->prepare("INSERT INTO guestbook (author, message) VALUES (?, ?)");
            $stmt->bind_param("ss", $author, $msg);
        }
        $stmt->execute();
        $stmt->close();
        header("Location: guestbook.php"); exit;
    }
}

$filter = trim($_GET['filter'] ?? '');
$filterBook = intval($_GET['book_id'] ?? 0);

$sql = "SELECT g.author, g.message, g.created_at, g.book_id, p.title AS book_title
        FROM guestbook g
        LEFT JOIN products p ON g.book_id = p.id";
$where = [];
$params = [];
$types = "";

if ($filter === 'registered') {
    $where[] = "g.author IN (SELECT login FROM users)";
} elseif ($filter === 'guests') {
    $where[] = "g.author NOT IN (SELECT login FROM users)";
} elseif ($filter === 'book') {
    if ($filterBook > 0) {
        $where[] = "g.book_id = ?";
        $params[] = $filterBook;
        $types .= "i";
    } else {
        $where[] = "g.book_id IS NOT NULL";
    }
} elseif ($filter === 'library') {
    $where[] = "g.book_id IS NULL";
}

if (!empty($where)) {
    $sql .= " WHERE " . implode(" AND ", $where);
}
$sql .= " ORDER BY g.id DESC LIMIT 50";

$stmt = $conn->prepare($sql);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$entries = $stmt->get_result();

$books = $conn->query("SELECT id, title FROM products ORDER BY title");

require_once "includes/header.php";
?>
<section class="card" style="max-width: 700px; margin: 0 auto 30px;">
    <h2 style="color: var(--accent);">Оставьте отзыв</h2>
    <form method="POST">
        <label>Ваше имя</label>
        <input type="text" name="author" value="<?php echo htmlspecialchars($_SESSION['user_login'] ?? ''); ?>" required>

        <label>Книга (необязательно)</label>
        <select name="book_id">
            <option value="0">— Общий отзыв о библиотеке —</option>
            <?php
            $booksList = $conn->query("SELECT id, title FROM products ORDER BY title");
            while ($b = $booksList->fetch_assoc()): ?>
                <option value="<?php echo $b['id']; ?>"><?php echo htmlspecialchars($b['title']); ?></option>
            <?php endwhile; ?>
        </select>

        <label>Сообщение</label>
        <textarea name="message" rows="4" required></textarea>

        <button type="submit" class="button">Оставить отзыв</button>
    </form>
</section>

<section style="max-width: 700px; margin: 0 auto 20px;">
    <div style="display: flex; gap: 8px; flex-wrap: wrap; align-items: center; margin-bottom: 15px;">
        <span style="font-size: 14px; color: var(--muted); margin-right: 5px;">Фильтр:</span>
        <a href="guestbook.php" style="padding: 6px 14px; border-radius: 20px; font-size: 13px; text-decoration: none; <?php echo $filter === '' ? 'background: var(--accent); color: #fff;' : 'background: #f0ebe5; color: var(--text);'; ?>">Все</a>
        <a href="guestbook.php?filter=registered" style="padding: 6px 14px; border-radius: 20px; font-size: 13px; text-decoration: none; <?php echo $filter === 'registered' ? 'background: var(--accent); color: #fff;' : 'background: #f0ebe5; color: var(--text);'; ?>">Зарегистрированные</a>
        <a href="guestbook.php?filter=guests" style="padding: 6px 14px; border-radius: 20px; font-size: 13px; text-decoration: none; <?php echo $filter === 'guests' ? 'background: var(--accent); color: #fff;' : 'background: #f0ebe5; color: var(--text);'; ?>">Гости</a>
        <a href="guestbook.php?filter=library" style="padding: 6px 14px; border-radius: 20px; font-size: 13px; text-decoration: none; <?php echo $filter === 'library' ? 'background: var(--accent); color: #fff;' : 'background: #f0ebe5; color: var(--text);'; ?>">О библиотеке</a>
        <a href="guestbook.php?filter=book" style="padding: 6px 14px; border-radius: 20px; font-size: 13px; text-decoration: none; <?php echo $filter === 'book' ? 'background: var(--accent); color: #fff;' : 'background: #f0ebe5; color: var(--text);'; ?>">По книге</a>
    </div>

    <?php if ($filter === 'book'): ?>
    <form method="GET" style="display: flex; gap: 10px; align-items: center; margin-bottom: 15px;">
        <input type="hidden" name="filter" value="book">
        <select name="book_id" style="flex: 1;">
            <option value="0">Все книги</option>
            <?php
            $books->data_seek(0);
            while ($b = $books->fetch_assoc()): ?>
                <option value="<?php echo $b['id']; ?>" <?php echo ($filterBook == $b['id']) ? 'selected' : ''; ?>>
                    <?php echo htmlspecialchars($b['title']); ?>
                </option>
            <?php endwhile; ?>
        </select>
        <button type="submit" class="button" style="padding: 8px 16px;">Показать</button>
    </form>
    <?php endif; ?>

    <h3>Отзывы читателей</h3>
    <?php if($entries->num_rows > 0): ?>
        <?php while($e = $entries->fetch_assoc()): ?>
            <div style="background: #fff; padding: 15px; border-left: 4px solid var(--accent); margin-bottom: 15px; box-shadow: 0 2px 5px rgba(0,0,0,0.05); border-radius: 4px;">
                <p style="margin: 0 0 5px; color: var(--muted); font-size: 13px;">
                    <b><?php echo htmlspecialchars($e['author']); ?></b> &bull; <?php echo date('d.m.Y H:i', strtotime($e['created_at'])); ?>
                    <?php if (!empty($e['book_title'])): ?>
                        &bull; 📖 <a href="guestbook.php?filter=book&book_id=<?php echo $e['book_id']; ?>" style="color: var(--accent); text-decoration: none;"><?php echo htmlspecialchars($e['book_title']); ?></a>
                    <?php else: ?>
                        &bull; О библиотеке
                    <?php endif; ?>
                </p>
                <p style="margin: 0; line-height: 1.5;"><?php echo nl2br(htmlspecialchars($e['message'])); ?></p>
            </div>
        <?php endwhile; ?>
    <?php else: ?>
        <p style="text-align: center; padding: 30px; color: var(--muted);">Отзывов не найдено.</p>
    <?php endif; ?>
</section>
<?php require_once "includes/footer.php"; ?>

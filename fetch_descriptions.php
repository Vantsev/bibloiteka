<?php
require_once "includes/auth.php";

if (!isset($_SESSION['user_role']) || !in_array($_SESSION['user_role'], ['admin', 'superadmin'])) {
    die("Доступ запрещён");
}

$results = [];
$products = $conn->query("SELECT id, title, author, description FROM products");

while ($p = $products->fetch_assoc()) {
    if (!empty($p['description']) && strlen($p['description']) > 50) {
        $results[] = ['title' => $p['title'], 'status' => 'skip', 'msg' => 'Описание уже есть'];
        continue;
    }

    $searchTitle = $p['title'];
    $url = "https://ru.wikipedia.org/w/api.php?" . http_build_query([
        'action' => 'query',
        'list' => 'search',
        'srsearch' => $searchTitle . ' роман',
        'format' => 'json',
        'utf8' => 1,
        'srlimit' => 1
    ]);

    $ctx = stream_context_create(['http' => ['timeout' => 10, 'header' => "User-Agent: BookHavenBot/1.0\r\n"]]);
    $json = @file_get_contents($url, false, $ctx);
    if (!$json) {
        $results[] = ['title' => $p['title'], 'status' => 'error', 'msg' => 'Не удалось найти в Wikipedia'];
        continue;
    }

    $data = json_decode($json, true);
    $pages = $data['query']['search'] ?? [];
    if (empty($pages)) {
        $results[] = ['title' => $p['title'], 'status' => 'error', 'msg' => 'Статья не найдена'];
        continue;
    }

    $pageTitle = $pages[0]['title'];

    $extractUrl = "https://ru.wikipedia.org/w/api.php?" . http_build_query([
        'action' => 'query',
        'titles' => $pageTitle,
        'prop' => 'extracts',
        'exintro' => true,
        'explaintext' => true,
        'format' => 'json',
        'utf8' => 1
    ]);

    $extractJson = @file_get_contents($extractUrl, false, $ctx);
    if (!$extractJson) {
        $results[] = ['title' => $p['title'], 'status' => 'error', 'msg' => 'Не удалось получить текст'];
        continue;
    }

    $extractData = json_decode($extractJson, true);
    $pagesData = $extractData['query']['pages'] ?? [];
    $pageContent = reset($pagesData);
    $extract = $pageContent['extract'] ?? '';

    if (empty($extract)) {
        $results[] = ['title' => $p['title'], 'status' => 'error', 'msg' => 'Пустой текст статьи'];
        continue;
    }

    // Берём первые 2-3 предложения (до 500 символов)
    $sentences = preg_split('/(?<=[.!?])\s+/', $extract, 5);
    $short = '';
    foreach ($sentences as $s) {
        if (strlen($short) + strlen($s) > 500) break;
        $short .= $s . ' ';
    }
    $short = trim($short);

    if (empty($short)) {
        $short = mb_substr($extract, 0, 500) . '...';
    }

    $stmt = $conn->prepare("UPDATE products SET description = ? WHERE id = ?");
    $stmt->bind_param("si", $short, $p['id']);
    $stmt->execute();
    $stmt->close();

    $results[] = ['title' => $p['title'], 'status' => 'ok', 'msg' => mb_substr($short, 0, 80) . '...'];
}

$pageTitle = "Загрузка описаний - BookHaven";
require_once "includes/header.php";
?>
<section class="card" style="max-width: 700px; margin: 0 auto;">
    <h2 style="color: var(--accent);">Загрузка описаний из Wikipedia</h2>
    <table>
        <tr><th>Книга</th><th>Статус</th><th>Результат</th></tr>
        <?php foreach ($results as $r): ?>
        <tr>
            <td><?php echo htmlspecialchars($r['title']); ?></td>
            <td>
                <?php if ($r['status'] === 'ok'): ?>
                    <span style="color: green;">OK</span>
                <?php elseif ($r['status'] === 'skip'): ?>
                    <span style="color: var(--muted);">Пропуск</span>
                <?php else: ?>
                    <span style="color: red;">Ошибка</span>
                <?php endif; ?>
            </td>
            <td style="font-size: 12px;"><?php echo htmlspecialchars($r['msg']); ?></td>
        </tr>
        <?php endforeach; ?>
    </table>
    <p style="margin-top: 15px;"><a href="admin.php" class="button">Назад в админку</a></p>
</section>
<?php require_once "includes/footer.php"; ?>

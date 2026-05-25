<?php
$pageTitle = "Галерея - BookHaven";
require_once "includes/auth.php";
require_once "includes/header.php";

$products = $conn->query("SELECT id, title, author, category, description, isbn FROM products ORDER BY title");
?>
<section class="card" style="text-align: center; margin-bottom: 20px;">
    <h2 style="color: var(--accent);">Галерея книг</h2>
    <p>Обложки и краткое содержание наших изданий</p>
</section>

<section class="grid" style="grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));">
    <?php if($products->num_rows > 0): ?>
        <?php while($p = $products->fetch_assoc()):
            $imgFile = null;
            $imgDir = __DIR__ . '/assets/img/';
            foreach (['jpg','jpeg','png','gif','webp'] as $ext) {
                if (file_exists($imgDir . $p['id'] . '.' . $ext)) {
                    $imgFile = 'assets/img/' . $p['id'] . '.' . $ext;
                    break;
                }
            }
            $colors = ['#8c3218','#2c5f2d','#1b4965','#6b2d5b','#4a3728'];
            $color = $colors[$p['id'] % count($colors)];
            $shortDesc = $p['description'] ? mb_strimwidth($p['description'], 0, 200, '...') : 'Описание скоро появится...';
        ?>
            <article class="card" style="display: flex; flex-direction: row; padding: 0; overflow: hidden;">
                <div style="background: #fdfaf6; display: flex; align-items: center; justify-content: center; min-width: 120px; padding: 15px; flex-shrink: 0;">
                    <?php if ($imgFile): ?>
                        <img src="<?php echo $imgFile; ?>" alt="<?php echo htmlspecialchars($p['title']); ?>" style="max-height: 160px; max-width: 100px; object-fit: contain; border-radius: 4px; box-shadow: 0 2px 6px rgba(0,0,0,0.15);">
                    <?php else: ?>
                        <div style="width: 90px; height: 130px; background: <?php echo $color; ?>; border-radius: 4px; box-shadow: 0 2px 6px rgba(0,0,0,0.2); display: flex; flex-direction: column; justify-content: center; align-items: center; padding: 10px; text-align: center;">
                            <span style="color: rgba(255,255,255,0.9); font-size: 11px; font-weight: bold; line-height: 1.3;"><?php echo htmlspecialchars(mb_strimwidth($p['title'], 0, 35, '...')); ?></span>
                            <span style="color: rgba(255,255,255,0.6); font-size: 9px; margin-top: 6px;"><?php echo htmlspecialchars($p['author']); ?></span>
                        </div>
                    <?php endif; ?>
                </div>
                <div style="padding: 15px; display: flex; flex-direction: column; justify-content: center;">
                    <h3 style="color: var(--accent); margin: 0 0 4px; font-size: 16px; line-height: 1.3;">
                        <?php echo htmlspecialchars($p['title']); ?>
                    </h3>
                    <p style="color: var(--muted); font-size: 12px; margin: 0 0 8px;">
                        <?php echo htmlspecialchars($p['author']); ?> &bull; <?php echo htmlspecialchars($p['category']); ?>
                    </p>
                    <p style="font-size: 13px; line-height: 1.5; color: var(--text); margin: 0;">
                        <?php echo htmlspecialchars($shortDesc); ?>
                    </p>
                </div>
            </article>
        <?php endwhile; ?>
    <?php else: ?>
        <p style="grid-column: 1/-1; text-align: center; padding: 50px; font-size: 18px; color: var(--muted); border: 2px dashed var(--border);">
            Книги пока не добавлены в каталог.
        </p>
    <?php endif; ?>
</section>
<?php require_once "includes/footer.php"; ?>

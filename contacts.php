<?php
$pageTitle = "Контакты - BookHaven";
require_once "includes/auth.php";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $topic   = trim($_POST['subject'] ?? 'Другое');
    $to      = "admin@bookhaven.example.com";
    $subject = "[{$topic}] Сообщение от читателя " . htmlspecialchars($_POST['name']);
    $message = "Тема: " . htmlspecialchars($topic) . "\n"
             . "Email: " . htmlspecialchars($_POST['email']) . "\n\n"
             . htmlspecialchars($_POST['message']);

    // mail($to, $subject, $message);
    $success = "Сообщение по теме «" . htmlspecialchars($topic) . "» отправлено! Мы ответим в ближайшее время.";
}

require_once "includes/header.php";
?>

<style>
.contacts-wrap { max-width: 900px; margin: 0 auto; }
.contacts-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; }
.contact-card { background: #fff; border: 1px solid var(--border); border-radius: 12px; padding: 24px; box-shadow: 0 2px 8px rgba(0,0,0,0.04); }
.contact-card h2 { color: var(--accent); margin: 0 0 16px; font-size: 20px; }
.contact-card h3 { color: var(--accent); margin: 0 0 16px; font-size: 18px; }

.info-list { list-style: none; padding: 0; margin: 0; }
.info-list li { display: flex; align-items: flex-start; gap: 12px; padding: 12px 0; border-bottom: 1px solid var(--border); }
.info-list li:last-child { border-bottom: none; }
.info-icon { width: 36px; height: 36px; background: #f5f0ea; border-radius: 8px; display: flex; align-items: center; justify-content: center; font-size: 18px; flex-shrink: 0; }
.info-text { font-size: 14px; line-height: 1.5; }
.info-text strong { display: block; color: var(--text); margin-bottom: 2px; }
.info-text span { color: var(--muted); }

.schedule-table { width: 100%; margin-top: 12px; font-size: 13px; }
.schedule-table td { padding: 6px 0; border-bottom: 1px solid var(--border); }
.schedule-table td:last-child { text-align: right; color: var(--muted); }
.schedule-table tr:last-child td { border-bottom: none; }
.schedule-table .today { font-weight: bold; color: var(--accent); }

.contact-form label { font-size: 13px; }
.contact-form input, .contact-form textarea { margin-bottom: 14px; }
.contact-form .button { width: 100%; padding: 12px; font-size: 15px; }

.map-placeholder { margin-top: 20px; background: #f5f0ea; border-radius: 8px; height: 180px; display: flex; align-items: center; justify-content: center; color: var(--muted); font-size: 14px; border: 1px dashed var(--border); }
</style>

<section class="contacts-wrap">
    <div class="contacts-grid">
        <div>
            <div class="contact-card" style="margin-bottom: 20px;">
                <h2>Контакты</h2>
                <ul class="info-list">
                    <li>
                        <div class="info-icon">📍</div>
                        <div class="info-text">
                            <strong>Адрес</strong>
                            <span>ул. Литературная, д. 45, Санкт-Петербург</span>
                        </div>
                    </li>
                    <li>
                        <div class="info-icon">📞</div>
                        <div class="info-text">
                            <strong>Телефон</strong>
                            <span>+7 (812) 123-45-67</span>
                        </div>
                    </li>
                    <li>
                        <div class="info-icon">✉️</div>
                        <div class="info-text">
                            <strong>Email</strong>
                            <span>support@bookhaven.study</span>
                        </div>
                    </li>
                    <li>
                        <div class="info-icon">💬</div>
                        <div class="info-text">
                            <strong>Telegram</strong>
                            <span>@bookhaven_support</span>
                        </div>
                    </li>
                </ul>
            </div>

            <div class="contact-card">
                <h3>Часы работы</h3>
                <table class="schedule-table">
                    <?php
                    $days = ['Понедельник','Вторник','Среда','Четверг','Пятница','Суббота','Воскресенье'];
                    $hours = ['9:00 – 21:00','9:00 – 21:00','9:00 – 21:00','9:00 – 21:00','9:00 – 21:00','10:00 – 18:00','Выходной'];
                    $today = date('N') - 1;
                    for ($i = 0; $i < 7; $i++):
                    ?>
                    <tr>
                        <td class="<?php echo $i === $today ? 'today' : ''; ?>"><?php echo $days[$i]; ?><?php echo $i === $today ? ' (сегодня)' : ''; ?></td>
                        <td class="<?php echo $i === $today ? 'today' : ''; ?>"><?php echo $hours[$i]; ?></td>
                    </tr>
                    <?php endfor; ?>
                </table>
            </div>
        </div>

        <div>
            <div class="contact-card">
                <h2>Написать нам</h2>
                <?php if (isset($success)): ?>
                    <div class="notice" style="border-radius: 8px;"><?php echo $success; ?></div>
                <?php endif; ?>
                <form method="POST" class="contact-form">
                    <label>Ваше имя</label>
                    <input type="text" name="name" value="<?php echo htmlspecialchars($_SESSION['user_login'] ?? ''); ?>" required>

                    <label>Email для ответа</label>
                    <input type="email" name="email" required>

                    <label>Тема</label>
                    <select name="subject">
                        <option <?php echo (($_POST['subject'] ?? '') === 'Вопрос по заказу') ? 'selected' : ''; ?>>Вопрос по заказу</option>
                        <option>Предложение по ассортименту</option>
                        <option>Жалоба</option>
                        <option>Сотрудничество</option>
                        <option>Другое</option>
                    </select>

                    <label>Сообщение</label>
                    <textarea name="message" rows="5" placeholder="Опишите ваш вопрос..." required></textarea>

                    <button type="submit" class="button">Отправить сообщение</button>
                </form>
            </div>

            <div class="map-placeholder" style="margin-top: 20px;">
                🗺️ Карта (Яндекс.Карты / Google Maps)
            </div>
        </div>
    </div>
</section>
<?php require_once "includes/footer.php"; ?>

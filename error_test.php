<?php
$pageTitle = "Тестирование обработчика ошибок - BookHaven";
require_once "includes/auth.php"; // подключает error_handler.php и регистрирует userErrorHandler()

$test = $_GET['test'] ?? '';

require_once "includes/header.php";
?>

<style>
.et-wrap { max-width: 820px; margin: 0 auto; }
.et-wrap h2 { color: var(--accent); }
.et-cases { width: 100%; border-collapse: collapse; margin: 16px 0; }
.et-cases th, .et-cases td { border: 1px solid var(--border); padding: 10px; text-align: left; font-size: 14px; vertical-align: top; }
.et-cases th { background: #fdfaf6; }
.et-run { display: flex; gap: 8px; flex-wrap: wrap; margin: 16px 0; }
.et-out { border: 2px dashed var(--accent); border-radius: 8px; padding: 16px; margin: 16px 0; background: #fffdf9; }
.et-out h3 { margin: 0 0 12px; color: var(--accent); font-size: 16px; }
.et-tag { display: inline-block; font-family: monospace; background: #2c2118; color: #f9f6f0; padding: 2px 8px; border-radius: 4px; font-size: 13px; }
.et-xml { background: #2c2118; color: #e8e2d5; padding: 14px; border-radius: 8px; font-size: 12px; overflow-x: auto; white-space: pre; font-family: monospace; }
</style>

<section class="et-wrap">
    <h2>Тестирование пользовательского обработчика ошибок</h2>
    <p style="color: var(--muted);">
        Обработчик <span class="et-tag">userErrorHandler()</span> из <span class="et-tag">includes/error_handler.php</span>
        зарегистрирован через <span class="et-tag">set_error_handler()</span> и перехватывает уровни
        <span class="et-tag">E_USER_NOTICE</span>, <span class="et-tag">E_USER_WARNING</span>,
        <span class="et-tag">E_USER_ERROR</span>. Каждая ошибка записывается в XML-лог; вывод на экран зависит от уровня.
    </p>

    <table class="et-cases">
        <tr><th>№</th><th>Тестовый пример (вызов)</th><th>Ожидаемый результат</th></tr>
        <tr>
            <td>1</td>
            <td><span class="et-tag">trigger_error("...", E_USER_NOTICE)</span></td>
            <td>Тихое уведомление: <b>только запись в XML-лог</b>, на экран ничего не выводится, скрипт продолжает работу.</td>
        </tr>
        <tr>
            <td>2</td>
            <td><span class="et-tag">trigger_error("...", E_USER_WARNING)</span></td>
            <td>Жёлтый блок <b>«Предупреждение»</b> + запись в лог. Скрипт продолжает работу.</td>
        </tr>
        <tr>
            <td>3</td>
            <td><span class="et-tag">trigger_error("...", E_USER_ERROR)</span></td>
            <td>Красный блок <b>«Критическая ошибка»</b> + запись в лог. Скрипт <b>останавливается</b> (<span class="et-tag">exit</span>).</td>
        </tr>
    </table>

    <div class="et-run">
        <a href="error_test.php?test=notice"  class="button">Тест 1: E_USER_NOTICE</a>
        <a href="error_test.php?test=warning" class="button" style="background:#e6a817;">Тест 2: E_USER_WARNING</a>
        <a href="error_test.php?test=error"   class="button btn-danger" style="background:#e74c3c;">Тест 3: E_USER_ERROR</a>
        <a href="error_test.php" class="button" style="background:var(--muted);">Сбросить</a>
    </div>

    <?php if ($test === 'notice'): ?>
        <div class="et-out">
            <h3>Результат теста 1 — E_USER_NOTICE</h3>
            <?php
                // ВЫЗОВ ТЕСТОВОГО ПРИМЕРА:
                trigger_error('ТЕСТ: пользовательское уведомление (демонстрация E_USER_NOTICE).', E_USER_NOTICE);
            ?>
            <p>Обработчик отработал «тихо»: на экран ничего не выведено, но в файл
               <span class="et-tag">logs/errors.xml</span> добавлена новая запись (см. лог ниже).</p>
        </div>

    <?php elseif ($test === 'warning'): ?>
        <div class="et-out">
            <h3>Результат теста 2 — E_USER_WARNING</h3>
            <p>Ниже — вывод, сформированный <b>самим обработчиком</b>:</p>
            <?php
                // ВЫЗОВ ТЕСТОВОГО ПРИМЕРА:
                trigger_error('ТЕСТ: пользовательское предупреждение (демонстрация E_USER_WARNING).', E_USER_WARNING);
            ?>
            <p style="margin-top:12px;">Скрипт <b>продолжил выполнение</b> после предупреждения — этот текст отрисовался, и запись добавлена в лог.</p>
        </div>

    <?php elseif ($test === 'error'): ?>
        <div class="et-out">
            <h3>Результат теста 3 — E_USER_ERROR</h3>
            <p>Ниже — вывод, сформированный <b>самим обработчиком</b>, после чего скрипт остановится:</p>
            <?php
                // ВЫЗОВ ТЕСТОВОГО ПРИМЕРА:
                // @ — гасит deprecation-уведомление PHP 8.4+ о передаче E_USER_ERROR в trigger_error();
                // сам пользовательский обработчик при этом всё равно вызывается.
                @trigger_error('ТЕСТ: критическая пользовательская ошибка (демонстрация E_USER_ERROR).', E_USER_ERROR);
                // Этот код НЕ выполнится — обработчик вызвал exit():
                echo '<p style="color:green;">Этот текст НЕ должен появиться (скрипт уже остановлен).</p>';
            ?>
        </div>
    <?php endif; ?>

    <?php if ($test !== 'error'): // при E_USER_ERROR скрипт уже завершён ?>
    <div class="et-out" style="border-style: solid;">
        <h3>Текущее содержимое logs/errors.xml</h3>
        <?php
            $logFile = __DIR__ . '/logs/errors.xml';
            if (is_file($logFile)) {
                $raw = file_get_contents($logFile);
                // Показываем последние записи (хвост файла), чтобы свежий тест был виден
                echo '<div class="et-xml">' . htmlspecialchars($raw) . '</div>';
            } else {
                echo '<p style="color:var(--muted);">Файл лога ещё не создан — запустите любой тест.</p>';
            }
        ?>
    </div>
    <?php endif; ?>
</section>

<?php
// Футер не подключаем после E_USER_ERROR, т.к. там был exit() — страница уже завершена.
if ($test !== 'error') {
    require_once "includes/footer.php";
}
?>

<?php


define('ERROR_LOG_FILE', __DIR__ . '/../logs/errors.xml');

function userErrorHandler(int $errno, string $errmsg, string $filename, int $linenum): bool
{
    // Типы ошибок PHP → человекочитаемое название
    $errortype = [
        E_ERROR             => 'Ошибка',
        E_WARNING           => 'Предупреждение',
        E_PARSE             => 'Ошибка разбора исходного кода',
        E_NOTICE            => 'Уведомление',
        E_CORE_ERROR        => 'Ошибка ядра',
        E_CORE_WARNING      => 'Предупреждение ядра',
        E_COMPILE_ERROR     => 'Ошибка на этапе компиляции',
        E_COMPILE_WARNING   => 'Предупреждение на этапе компиляции',
        E_USER_ERROR        => 'Пользовательская ошибка',
        E_USER_WARNING      => 'Пользовательское предупреждение',
        E_USER_NOTICE       => 'Пользовательское уведомление',
        E_RECOVERABLE_ERROR => 'Отлавливаемая фатальная ошибка',
    ];

    // Набор ошибок, для которых сохраняем лог
    $user_errors = [E_USER_ERROR, E_USER_WARNING, E_USER_NOTICE];

    if (in_array($errno, $user_errors)) {
        $dt  = date('Y-m-d H:i:s (T)');
        $type = $errortype[$errno] ?? 'Неизвестно';

        // Формируем XML-запись
        $xml_entry = "<errorentry>\n"
            . "\t<datetime>"    . htmlspecialchars($dt,       ENT_XML1) . "</datetime>\n"
            . "\t<errornum>"    . $errno                                . "</errornum>\n"
            . "\t<errortype>"   . htmlspecialchars($type,     ENT_XML1) . "</errortype>\n"
            . "\t<errormsg>"    . htmlspecialchars($errmsg,   ENT_XML1) . "</errormsg>\n"
            . "\t<scriptname>"  . htmlspecialchars($filename, ENT_XML1) . "</scriptname>\n"
            . "\t<scriptlinenum>" . $linenum                            . "</scriptlinenum>\n"
            . "</errorentry>\n\n";

        // Создаём папку logs, если не существует
        $logDir = dirname(ERROR_LOG_FILE);
        if (!is_dir($logDir)) {
            mkdir($logDir, 0755, true);
        }

        // Инициализируем файл XML-корнем при первой записи
        if (!file_exists(ERROR_LOG_FILE)) {
            file_put_contents(ERROR_LOG_FILE, "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n<errors>\n");
        }

        // Добавляем запись перед закрывающим тегом (или просто append)
        file_put_contents(ERROR_LOG_FILE, $xml_entry, FILE_APPEND);
    }

    // E_USER_ERROR — фатальная: показываем сообщение и останавливаем скрипт
    if ($errno === E_USER_ERROR) {
        echo "<div class='notice error'>"
           . "<strong>Критическая ошибка:</strong> "
           . htmlspecialchars($errmsg)
           . "</div>";
        exit(1);
    }

    // E_USER_WARNING — показываем предупреждение, но не останавливаем
    if ($errno === E_USER_WARNING) {
        echo "<div class='notice error' style='border-left-color:#e6a817;'>"
           . "<strong>Предупреждение:</strong> "
           . htmlspecialchars($errmsg)
           . "</div>";
    }

    // E_USER_NOTICE — тихое уведомление (только в лог)
    // Возвращаем true, чтобы стандартный обработчик PHP не вмешивался
    return true;
}

// Регистрируем обработчик для пользовательских уровней ошибок
set_error_handler('userErrorHandler', E_USER_ERROR | E_USER_WARNING | E_USER_NOTICE);

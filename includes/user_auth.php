<?php
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/error_handler.php';

/**
 * Проверка Google reCAPTCHA v2 на сервере.
 * Возвращает true если капча пройдена, false иначе.
 */
function verify_recaptcha(string $token): bool
{
    if (empty($token)) return false;

    $secret = '6LeIxAcTAAAAAGG-vFI1TnRWxMZNFuojJ4WifJWe'; // тестовый — замени на свой

    $ch = curl_init('https://www.google.com/recaptcha/api/siteverify');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => http_build_query([
            'secret'   => $secret,
            'response' => $token,
            'remoteip' => $_SERVER['REMOTE_ADDR'] ?? '',
        ]),
    ]);
    $response = curl_exec($ch);

    if (!$response) return false;

    $data = json_decode($response, true);
    return !empty($data['success']);
}

/**
 * Регистрация пользователя.
 */
function register_user($conn, $data, $file): array
{
    /* 1. Проверка reCAPTCHA */
    $token = $data['g-recaptcha-response'] ?? '';
    if (!verify_recaptcha($token)) {
        trigger_error('Проверка «Я не робот» не пройдена. Попробуйте снова.', E_USER_WARNING);
        return ['success' => false, 'message' => 'Проверка «Я не робот» не пройдена.'];
    }

    /* 2. Проверка обязательных полей */
    $required = ['login' => 'Логин', 'password' => 'Пароль', 'reg_date' => 'Дата', 'email' => 'Email'];
    foreach ($required as $field => $label) {
        if (empty($data[$field])) {
            trigger_error("Поле «{$label}» обязательно для заполнения.", E_USER_WARNING);
            return ['success' => false, 'message' => "Поле «{$label}» обязательно."];
        }
    }

    /* 3. Валидация логина */
    $login = trim($data['login']);
    if (strlen($login) < 3 || strlen($login) > 50) {
        trigger_error('Логин должен содержать от 3 до 50 символов.', E_USER_WARNING);
        return ['success' => false, 'message' => 'Логин: от 3 до 50 символов.'];
    }
    if (!preg_match('/^[a-zA-Z0-9_\-]+$/', $login)) {
        trigger_error('Логин содержит недопустимые символы.', E_USER_WARNING);
        return ['success' => false, 'message' => 'Логин: только буквы A-Z, цифры, _ и -.'];
    }

    /* 4. Валидация пароля */
    $password = $data['password'];
    if (strlen($password) < 6) {
        trigger_error('Пароль должен содержать не менее 6 символов.', E_USER_WARNING);
        return ['success' => false, 'message' => 'Пароль: минимум 6 символов.'];
    }

    /* 5. Валидация Email */
    $email = trim($data['email']);
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        trigger_error("Некорректный Email: «{$email}».", E_USER_WARNING);
        return ['success' => false, 'message' => "Некорректный Email: «{$email}»."];
    }

    /* 6. Валидация даты */
    $reg_date = $data['reg_date'];
    $d = DateTime::createFromFormat('Y-m-d', $reg_date);
    if (!$d || $d->format('Y-m-d') !== $reg_date) {
        trigger_error('Некорректный формат даты регистрации.', E_USER_WARNING);
        return ['success' => false, 'message' => 'Некорректный формат даты.'];
    }

    /* 7. Проверка уникальности */
    $stmt = $conn->prepare("SELECT id FROM users WHERE login = ? OR email = ?");
    if (!$stmt) {
        trigger_error('Ошибка подготовки запроса: ' . $conn->error, E_USER_ERROR);
        return ['success' => false, 'message' => 'Внутренняя ошибка сервера.'];
    }
    if (!$stmt->bind_param('ss', $login, $email)) {
        trigger_error('Ошибка связывания параметров: ' . $stmt->error, E_USER_ERROR);
        $stmt->close();
        return ['success' => false, 'message' => 'Внутренняя ошибка сервера.'];
    }
    if (!$stmt->execute()) {
        trigger_error('Ошибка выполнения запроса: ' . $stmt->error, E_USER_ERROR);
        $stmt->close();
        return ['success' => false, 'message' => 'Внутренняя ошибка сервера.'];
    }
    if ($stmt->get_result()->num_rows > 0) {
        $stmt->close();
        trigger_error("Логин «{$login}» или Email «{$email}» уже заняты.", E_USER_NOTICE);
        return ['success' => false, 'message' => 'Логин или Email уже заняты.'];
    }
    $stmt->close();

    /* 8. Загрузка фото */
    $photoPath = '';
    if (!empty($file['photo']['name']) && $file['photo']['error'] === UPLOAD_ERR_OK) {
        $allowed_ext  = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
        $allowed_mime = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
        $ext  = strtolower(pathinfo($file['photo']['name'], PATHINFO_EXTENSION));
        $mime = mime_content_type($file['photo']['tmp_name']);

        if (!in_array($ext, $allowed_ext) || !in_array($mime, $allowed_mime)) {
            trigger_error('Недопустимый формат аватара.', E_USER_WARNING);
            return ['success' => false, 'message' => 'Аватар: разрешены JPG, PNG, GIF, WEBP.'];
        }
        if ($file['photo']['size'] > 2 * 1024 * 1024) {
            trigger_error('Размер аватара превышает 2 МБ.', E_USER_WARNING);
            return ['success' => false, 'message' => 'Аватар: максимальный размер 2 МБ.'];
        }
        $newname = time() . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
        $dest    = __DIR__ . '/../uploads/' . $newname;
        if (move_uploaded_file($file['photo']['tmp_name'], $dest)) {
            $photoPath = 'uploads/' . $newname;
        } else {
            trigger_error('Не удалось сохранить аватар.', E_USER_WARNING);
        }
    }

    /* 9. Сохранение пользователя */
    $hash = password_hash($password, PASSWORD_DEFAULT);
    $stmt = $conn->prepare(
        "INSERT INTO users (login, password, reg_date, email, photo) VALUES (?, ?, ?, ?, ?)"
    );
    if (!$stmt) {
        trigger_error('Ошибка подготовки запроса: ' . $conn->error, E_USER_ERROR);
        return ['success' => false, 'message' => 'Внутренняя ошибка сервера.'];
    }
    if (!$stmt->bind_param('sssss', $login, $hash, $reg_date, $email, $photoPath)) {
        trigger_error('Ошибка связывания параметров: ' . $stmt->error, E_USER_ERROR);
        $stmt->close();
        return ['success' => false, 'message' => 'Внутренняя ошибка сервера.'];
    }
    if (!$stmt->execute()) {
        trigger_error('Ошибка выполнения запроса: ' . $stmt->error, E_USER_ERROR);
        $stmt->close();
        return ['success' => false, 'message' => 'Внутренняя ошибка сервера.'];
    }
    $stmt->close();

    trigger_error("Новый пользователь «{$login}» зарегистрирован.", E_USER_NOTICE);
    return ['success' => true, 'message' => 'Регистрация успешна! Теперь вы можете войти.'];
}

/**
 * Вход пользователя.
 */
function login_user($conn, $data): array
{
    if (empty($data['login']) || empty($data['password'])) {
        trigger_error('Укажите логин и пароль.', E_USER_WARNING);
        return ['success' => false, 'message' => 'Укажите логин и пароль.'];
    }

    $login = trim($data['login']);
    $stmt = $conn->prepare("SELECT id, login, password, role FROM users WHERE login = ?");
    if (!$stmt) {
        trigger_error('Ошибка подготовки запроса: ' . $conn->error, E_USER_ERROR);
        return ['success' => false, 'message' => 'Внутренняя ошибка сервера.'];
    }
    if (!$stmt->bind_param('s', $login)) {
        trigger_error('Ошибка связывания параметров: ' . $stmt->error, E_USER_ERROR);
        $stmt->close();
        return ['success' => false, 'message' => 'Внутренняя ошибка сервера.'];
    }
    if (!$stmt->execute()) {
        trigger_error('Ошибка выполнения запроса: ' . $stmt->error, E_USER_ERROR);
        $stmt->close();
        return ['success' => false, 'message' => 'Внутренняя ошибка сервера.'];
    }
    $res = $stmt->get_result();
    if ($row = $res->fetch_assoc()) {
        if (password_verify($data['password'], $row['password'])) {
            $_SESSION['user_id']    = $row['id'];
            $_SESSION['user_login'] = $row['login'];
            $_SESSION['user_role']  = $row['role'] ?? 'user';
            $stmt->close();
            return ['success' => true, 'role' => $row['role'] ?? 'user'];
        }
    }
    $stmt->close();
    trigger_error("Неудачная попытка входа: логин «{$login}».", E_USER_NOTICE);
    return ['success' => false, 'message' => 'Неверные логин или пароль.'];
}

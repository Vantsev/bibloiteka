<?php
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/error_handler.php';

function ensure_lab4s_tables($conn) {
    // Пользователи
    $conn->query("
        CREATE TABLE IF NOT EXISTS users (
            id INT AUTO_INCREMENT PRIMARY KEY,
            login VARCHAR(50) NOT NULL UNIQUE,
            password VARCHAR(255) NOT NULL,
            reg_date DATE NOT NULL,
            email VARCHAR(100) NOT NULL,
            photo VARCHAR(255) DEFAULT ''
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ");

    // Книги (Товары)
    $conn->query("
        CREATE TABLE IF NOT EXISTS products (
            id INT AUTO_INCREMENT PRIMARY KEY,
            title VARCHAR(150) NOT NULL,
            author VARCHAR(100) NOT NULL,
            category VARCHAR(50) NOT NULL,
            description TEXT,
            isbn VARCHAR(20) DEFAULT NULL,
            price DECIMAL(10,2) NOT NULL DEFAULT 0.00
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ");

    // Добавляем isbn если таблица уже существует без него
    $colCheck = $conn->query("SHOW COLUMNS FROM products LIKE 'isbn'");
    if ($colCheck && $colCheck->num_rows === 0) {
        $conn->query("ALTER TABLE products ADD COLUMN isbn VARCHAR(20) DEFAULT NULL AFTER description");
    }

    // Заказы (Займы)
    $conn->query("
        CREATE TABLE IF NOT EXISTS orders (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            order_date DATETIME DEFAULT CURRENT_TIMESTAMP,
            total_price DECIMAL(10,2) NOT NULL,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ");

    // Позиции заказов M:M
    $conn->query("
        CREATE TABLE IF NOT EXISTS order_items (
            id INT AUTO_INCREMENT PRIMARY KEY,
            order_id INT NOT NULL,
            product_id INT NOT NULL,
            quantity INT NOT NULL DEFAULT 1,
            price_at_purchase DECIMAL(10,2) NOT NULL,
            FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE,
            FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ");

    // Гостевая книга (Отзывы)
    $conn->query("
        CREATE TABLE IF NOT EXISTS guestbook (
            id INT AUTO_INCREMENT PRIMARY KEY,
            author VARCHAR(50) NOT NULL,
            book_id INT DEFAULT NULL,
            message TEXT NOT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (book_id) REFERENCES products(id) ON DELETE SET NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ");

    // Добавляем book_id если таблица уже существует без него
    $colCheck = $conn->query("SHOW COLUMNS FROM guestbook LIKE 'book_id'");
    if ($colCheck && $colCheck->num_rows === 0) {
        $conn->query("ALTER TABLE guestbook ADD COLUMN book_id INT DEFAULT NULL AFTER author");
        $conn->query("ALTER TABLE guestbook ADD FOREIGN KEY (book_id) REFERENCES products(id) ON DELETE SET NULL");
    }

    // Добавляем роль пользователя если нет
    $colCheck = $conn->query("SHOW COLUMNS FROM users LIKE 'role'");
    if ($colCheck && $colCheck->num_rows === 0) {
        $conn->query("ALTER TABLE users ADD COLUMN role ENUM('user','admin','superadmin') NOT NULL DEFAULT 'user'");
    }

    // Добавляем флаг бана
    $colCheck = $conn->query("SHOW COLUMNS FROM users LIKE 'is_banned'");
    if ($colCheck && $colCheck->num_rows === 0) {
        $conn->query("ALTER TABLE users ADD COLUMN is_banned TINYINT(1) NOT NULL DEFAULT 0");
    }

    // Остаток книги на складе (для существующих строк ставим 10)
    $colCheck = $conn->query("SHOW COLUMNS FROM products LIKE 'stock'");
    if ($colCheck && $colCheck->num_rows === 0) {
        $conn->query("ALTER TABLE products ADD COLUMN stock INT NOT NULL DEFAULT 10");
    }

    // Статус заказа: new (новый) / done (выполнен)
    $colCheck = $conn->query("SHOW COLUMNS FROM orders LIKE 'status'");
    if ($colCheck && $colCheck->num_rows === 0) {
        $conn->query("ALTER TABLE orders ADD COLUMN status ENUM('new','done') NOT NULL DEFAULT 'new'");
    }

    // Понижаем обычных админов до пользователей — теперь админ-доступ только у superadmin
    $conn->query("UPDATE users SET role = 'user' WHERE role = 'admin'");

    // Создаём главного админа если нет ни одного superadmin
    $saCheck = $conn->query("SELECT COUNT(*) as c FROM users WHERE role = 'superadmin'");
    $saRow = $saCheck->fetch_assoc();
    if ($saRow['c'] == 0) {
        $saHash = password_hash('admin123', PASSWORD_DEFAULT);
        $saDate = date('Y-m-d');
        $conn->query("INSERT IGNORE INTO users (login, password, reg_date, email, role) VALUES ('admin', '$saHash', '$saDate', 'admin@bookhaven.local', 'superadmin')");
    }

    // Сид данных
    $res = $conn->query("SELECT COUNT(*) as c FROM products");
    $row = $res->fetch_assoc();
    if ($row['c'] == 0) {
        $stmt = $conn->prepare("INSERT INTO products (title, author, category, description, isbn, price) VALUES (?, ?, ?, ?, ?, ?)");
        $seed = [
            ['Преступление и наказание', 'Ф. Достоевский', 'Классика', '', '9785171529017', 450.00],
            ['Властелин колец', 'Дж. Р. Р. Толкин', 'Фэнтези', '', '9785170988587', 1200.00],
            ['1984', 'Джордж Оруэлл', 'Дистопия', '', '9785170800100', 600.00],
            ['Мастер и Маргарита', 'М. Булгаков', 'Классика', '', '9785170979110', 550.00],
            ['Гарри Поттер и философский камень', 'Дж. К. Роулинг', 'Фэнтези', '', '9785389077843', 800.00]
        ];
        foreach ($seed as $item) {
            $stmt->bind_param("sssssd", $item[0], $item[1], $item[2], $item[3], $item[4], $item[5]);
            $stmt->execute();
        }
        $stmt->close();
    }
}

ensure_lab4s_tables($conn);

function is_authorized() {
    return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
}

function require_auth() {
    if (!is_authorized()) {
        header("Location: login.php");
        exit;
    }
}
?>
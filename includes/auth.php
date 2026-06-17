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

    // Статус заказа: new (новый) / done (выполнен) / cancelled (отменён)
    $colCheck = $conn->query("SHOW COLUMNS FROM orders LIKE 'status'");
    if ($colCheck && $colCheck->num_rows === 0) {
        $conn->query("ALTER TABLE orders ADD COLUMN status ENUM('new','done','cancelled') NOT NULL DEFAULT 'new'");
    } else {
        // Расширяем ENUM значением 'cancelled', если его ещё нет
        $row = $colCheck->fetch_assoc();
        if ($row && strpos($row['Type'], 'cancelled') === false) {
            $conn->query("ALTER TABLE orders MODIFY status ENUM('new','done','cancelled') NOT NULL DEFAULT 'new'");
        }
    }

    // Создаём главного админа если нет ни одного superadmin
    $saCheck = $conn->query("SELECT COUNT(*) as c FROM users WHERE role = 'superadmin'");
    $saRow = $saCheck->fetch_assoc();
    if ($saRow['c'] == 0) {
        $saHash = password_hash('admin123', PASSWORD_DEFAULT);
        $saDate = date('Y-m-d');
        $conn->query("INSERT IGNORE INTO users (login, password, reg_date, email, role) VALUES ('admin', '$saHash', '$saDate', 'admin@bookhaven.local', 'superadmin')");
    }

    // Сид каталога книг (полный фонд библиотеки)
    $res = $conn->query("SELECT COUNT(*) as c FROM products");
    $row = $res->fetch_assoc();
    if ($row['c'] == 0) {
        // [title, author, category, isbn, price, stock, description]
        $seed = [
            ['Преступление и наказание','Ф. М. Достоевский','Классика','9785171123451',520,12,'Психологический роман о бедном студенте Раскольникове, его преступлении и мучительном пути к раскаянию.'],
            ['Война и мир','Л. Н. Толстой','Классика','9785170914586',990,7,'Эпопея о русском обществе в эпоху наполеоновских войн: судьбы, любовь, философия истории.'],
            ['Мастер и Маргарита','М. А. Булгаков','Классика','9785170982356',560,15,'Дьявол посещает Москву 1930-х. Сатира, мистика и вечная история любви Мастера и Маргариты.'],
            ['Анна Каренина','Л. Н. Толстой','Классика','9785171029384',640,9,'Трагическая история любви замужней женщины и блестящего офицера Вронского.'],
            ['Евгений Онегин','А. С. Пушкин','Поэзия','9785446112345',380,20,'Роман в стихах — энциклопедия русской жизни и история несостоявшейся любви Татьяны и Онегина.'],
            ['Отцы и дети','И. С. Тургенев','Классика','9785170801237',410,11,'Конфликт поколений и идей на примере нигилиста Базарова и дворянского общества.'],
            ['Мёртвые души','Н. В. Гоголь','Классика','9785170889912',430,8,'Похождения Чичикова, скупающего «мёртвые души», — сатира на помещичью Россию.'],
            ['Идиот','Ф. М. Достоевский','Классика','9785171234561',540,6,'История князя Мышкина — человека идеальной доброты в мире страстей и расчёта.'],
            ['Великий Гэтсби','Ф. С. Фицджеральд','Зарубежная классика','9785699507891',450,14,'Роскошь и пустота «эпохи джаза»: загадочный миллионер Гэтсби и его мечта о прошлом.'],
            ['Гордость и предубеждение','Джейн Остин','Зарубежная классика','9785389066781',470,10,'Остроумный роман о любви, предрассудках и поиске себя в английском обществе XIX века.'],
            ['1984','Джордж Оруэлл','Дистопия','9785170800100',600,18,'Мир тотального надзора Большого Брата. Классическая антиутопия о свободе и правде.'],
            ['О дивный новый мир','Олдос Хаксли','Дистопия','9785171005566',580,9,'Общество всеобщего «счастья», где люди выращиваются на конвейере и лишены свободы выбора.'],
            ['451 градус по Фаренгейту','Рэй Брэдбери','Дистопия','9785171070038',520,12,'Будущее, где книги под запретом, а пожарные их сжигают. История пробуждения Гая Монтэга.'],
            ['Над пропастью во ржи','Дж. Д. Сэлинджер','Зарубежная классика','9785699123451',490,7,'Несколько дней из жизни подростка Холдена Колфилда, бунтующего против фальши взрослого мира.'],
            ['Старик и море','Эрнест Хемингуэй','Зарубежная классика','9785699908765',350,16,'Притча о старом рыбаке Сантьяго и его поединке с огромной рыбой — о стойкости человека.'],
            ['Властелин колец','Дж. Р. Р. Толкин','Фэнтези','9785170988587',1290,9,'Эпическое путешествие хоббита Фродо, чтобы уничтожить Кольцо Всевластья и спасти Средиземье.'],
            ['Хоббит, или Туда и обратно','Дж. Р. Р. Толкин','Фэнтези','9785170900015',720,13,'Приключения Бильбо Бэггинса, гнома и дракона Смауга — пролог к «Властелину колец».'],
            ['Гарри Поттер и философский камень','Дж. К. Роулинг','Фэнтези','9785389077843',850,20,'Мальчик узнаёт, что он волшебник, и попадает в школу магии Хогвартс. Начало легендарной саги.'],
            ['Игра престолов','Джордж Р. Р. Мартин','Фэнтези','9785171019384',980,6,'Борьба знатных домов за Железный трон. Интриги, война и драконы в мире Семи Королевств.'],
            ['Ведьмак. Последнее желание','Анджей Сапковский','Фэнтези','9785171234578',670,11,'Истории о ведьмаке Геральте — охотнике на чудовищ. Сборник, открывающий знаменитую сагу.'],
            ['Дюна','Фрэнк Герберт','Фантастика','9785171108090',890,8,'Пустынная планета Арракис, спайс и судьба Пола Атрейдеса. Шедевр научной фантастики.'],
            ['Солярис','Станислав Лем','Фантастика','9785170934561',540,10,'Контакт с разумным океаном далёкой планеты ставит под вопрос границы человеческого познания.'],
            ['Марсианин','Энди Вейер','Фантастика','9785170925612',620,9,'Астронавт остаётся один на Марсе и борется за выживание силой инженерии и юмора.'],
            ['Пикник на обочине','А. и Б. Стругацкие','Фантастика','9785170889005',480,12,'Сталкеры проникают в Зону, оставленную пришельцами, рискуя жизнью ради загадочных артефактов.'],
            ['Десять негритят','Агата Кристи','Детектив','9785699882342',430,14,'Десять незнакомцев на острове гибнут один за другим. Эталонный детектив-головоломка.'],
            ['Убийство в «Восточном экспрессе»','Агата Кристи','Детектив','9785699771233',450,10,'Эркюль Пуаро расследует убийство в поезде, застрявшем в снегу. Все пассажиры — под подозрением.'],
            ['Приключения Шерлока Холмса','Артур Конан Дойл','Детектив','9785170990012',760,7,'Сборник рассказов о гениальном сыщике с Бейкер-стрит и его методе дедукции.'],
            ['Девушка с татуировкой дракона','Стиг Ларссон','Детектив','9785389012349',590,8,'Журналист и хакерша Лисбет Саландер распутывают дело об исчезновении 40-летней давности.'],
            ['Sapiens. Краткая история человечества','Юваль Ной Харари','Научпоп','9785906837929',780,11,'Как Homo sapiens прошёл путь от незаметного вида до властелина планеты. Большая история человечества.'],
            ['Краткая история времени','Стивен Хокинг','Научпоп','9785170851234',560,9,'Доступный рассказ о Большом взрыве, чёрных дырах и природе времени от великого физика.'],
            ['Думай медленно… решай быстро','Даниэль Канеман','Психология','9785170923458',690,6,'Нобелевский лауреат о двух системах мышления и о том, как мы на самом деле принимаем решения.'],
            ['Тонкое искусство пофигизма','Марк Мэнсон','Психология','9785001008927',520,13,'Неожиданный взгляд на счастье: меньше гнаться за позитивом, выбирать, что действительно важно.'],
            ['Стихотворения','С. А. Есенин','Поэзия','9785170800223',320,15,'Избранная лирика поэта о русской деревне, любви и быстротечности жизни.'],
            ['Лирика','А. А. Ахматова','Поэзия','9785446104567',340,10,'Сборник стихотворений одной из величайших поэтесс Серебряного века.'],
            ['Сияние','Стивен Кинг','Ужасы','9785170889456',640,8,'Семья остаётся смотрителями отеля на зиму. Зловещее место сводит отца с ума. Классика хоррора.'],
            ['Оно','Стивен Кинг','Ужасы','9785170934002',950,6,'Древнее зло в облике клоуна Пеннивайза терроризирует город. Дружба детей против ужаса.'],
            ['Три товарища','Эрих Мария Ремарк','Зарубежная классика','9785170990029',610,9,'История дружбы и любви на фоне послевоенной Германии. Трогательный роман о человечности.'],
        ];
        $stmt = $conn->prepare("INSERT INTO products (title, author, category, isbn, price, stock, description) VALUES (?, ?, ?, ?, ?, ?, ?)");
        foreach ($seed as $b) {
            $stmt->bind_param("ssssdis", $b[0], $b[1], $b[2], $b[3], $b[4], $b[5], $b[6]);
            $stmt->execute();
        }
        $stmt->close();
    }

    // Сид читателей (если обычных пользователей ещё нет)
    $uCheck = $conn->query("SELECT COUNT(*) as c FROM users WHERE role = 'user'");
    if ($uCheck && $uCheck->fetch_assoc()['c'] == 0) {
        $rHash = password_hash('reader123', PASSWORD_DEFAULT);
        $readers = [
            ['anna_k','anna.kuznetsova@mail.ru','2025-09-12'],
            ['pavel_v','pavel.volkov@gmail.com','2025-10-03'],
            ['maria_s','maria.smirnova@yandex.ru','2025-11-21'],
            ['dmitry_l','d.lebedev@mail.ru','2026-01-08'],
            ['elena_r','elena.romanova@bk.ru','2026-02-17'],
            ['igor_n','igor.novikov@gmail.com','2026-03-05'],
            ['olga_t','olga.titova@yandex.ru','2026-04-19'],
            ['sergey_m','sergey.morozov@mail.ru','2026-05-02'],
        ];
        $stmt = $conn->prepare("INSERT INTO users (login, password, reg_date, email, role, is_banned) VALUES (?, ?, ?, ?, 'user', 0)");
        foreach ($readers as $u) {
            $stmt->bind_param("ssss", $u[0], $rHash, $u[2], $u[1]);
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
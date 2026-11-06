<?php
/*
 * ============================================================
 *  ФАЙЛ: test1.php
 *  ПРОЕКТ: EestiKool — сайт для изучения эстонского языка
 *  ЦЕЛЬ: Демонстрация и объяснение всей архитектуры сайта
 *         (база данных, PHP, HTML, Bootstrap, JavaScript)
 * ============================================================
 */


// ============================================================
//  ШАГ 1 — СЕССИИ (session_start)
// ============================================================
//
//  session_start() — запускает механизм сессий PHP.
//  Сессия — это способ «запомнить» пользователя между страницами.
//  Например, мы запоминаем выбранный язык (ru или et).
//  Данные сессии хранятся на сервере, а в браузере сохраняется
//  только маленький идентификатор (cookie PHPSESSID).
//
session_start();


// ============================================================
//  ШАГ 2 — ВЫБОР ЯЗЫКА (многоязычность)
// ============================================================
//
//  Если в URL передан параметр ?lang=ru или ?lang=et,
//  мы сохраняем его в сессию ($_SESSION['lang']).
//  ?? — оператор "null coalescing": если слева null — берём правое.
//  По умолчанию язык — 'ru' (русский).
//
if (isset($_GET['lang'])) {
    $_SESSION['lang'] = $_GET['lang'];       // сохраняем язык в сессию
}
$current_lang = $_SESSION['lang'] ?? 'ru';  // читаем из сессии или 'ru'


// ============================================================
//  ШАГ 3 — ПОДКЛЮЧЕНИЕ ЯЗЫКОВОГО ФАЙЛА
// ============================================================
//
//  В папке lang/ лежат два файла: ru.php и et.php.
//  Каждый из них задаёт массив $lang['ключ'] => 'перевод'.
//  Мы подключаем нужный файл через include.
//  После include переменная $lang доступна здесь.
//
if ($current_lang == 'et') {
    include 'lang/et.php';   // эстонские переводы
} else {
    include 'lang/ru.php';   // русские переводы
}


// ============================================================
//  ШАГ 4 — ПОДКЛЮЧЕНИЕ К БАЗЕ ДАННЫХ (PDO)
// ============================================================
//
//  PDO (PHP Data Objects) — стандартный способ работы с БД в PHP.
//  Он безопаснее, чем старые функции mysql_query().
//  Мы используем MySQL (через XAMPP / phpMyAdmin).
//
//  Параметры подключения задаются в db.php:
//    $host    = 'localhost'        — сервер БД
//    $db      = 'eesti_keel_db'    — имя базы данных
//    $user    = 'root'             — пользователь
//    $pass    = ''                 — пароль (пустой в XAMPP по умолчанию)
//    $charset = 'utf8mb4'          — кодировка (поддерживает кириллицу и 🇪🇪)
//
//  После require_once переменная $pdo готова к запросам.
//
require_once 'db.php';


// ============================================================
//  ШАГ 5 — СТРУКТУРА БАЗЫ ДАННЫХ
// ============================================================
//
//  В проекте 4 таблицы:
//
//  ┌─────────────────────────────────────────────────────┐
//  │  courses         — список курсов                    │
//  │    id, title_ru, title_et,                          │
//  │    description_ru, description_et, level, price     │
//  │                                                     │
//  │  teachers        — список преподавателей            │
//  │    id, name, bio_ru, bio_et, photo_url, created_at  │
//  │                                                     │
//  │  course_teacher  — связь курс ↔ учитель             │
//  │    course_id (FK → courses.id)                      │
//  │    teacher_id (FK → teachers.id)                    │
//  │    * один курс может иметь несколько учителей       │
//  │    * один учитель может вести несколько курсов      │
//  │    * это называется "many-to-many" (многие-ко-многим)│
//  │                                                     │
//  │  registrations   — заявки студентов                 │
//  │    id, student_name, student_email,                 │
//  │    course_id (FK), teacher_id (FK), created_at      │
//  └─────────────────────────────────────────────────────┘
//
//  FK (Foreign Key) — внешний ключ, ссылка на другую таблицу.


// ============================================================
//  ШАГ 6 — SQL-ЗАПРОСЫ И ПОЛУЧЕНИЕ ДАННЫХ
// ============================================================

// ── 6a. Простой запрос — SELECT всех курсов ─────────────────
//
//  $pdo->query(SQL) — выполняет запрос.
//  ->fetchAll()     — возвращает все строки как массив PHP.
//  PDO::FETCH_ASSOC — каждая строка = ассоциативный массив
//                     (ключи = названия столбцов).
//
$courses = [];
try {
    $courses = $pdo->query("SELECT * FROM courses ORDER BY id")
                   ->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    // PDOException — исключение при ошибке БД.
    // try/catch нужен, чтобы сайт не «упал» если таблицы нет.
    $courses = [];
}

// ── 6b. SELECT учителей ──────────────────────────────────────
$teachers = [];
try {
    $teachers = $pdo->query("SELECT * FROM teachers ORDER BY name")
                    ->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) { $teachers = []; }

// ── 6c. JOIN-запрос — связь курс + учитель ──────────────────
//
//  JOIN объединяет данные из нескольких таблиц.
//  Схема JOIN:
//
//    course_teacher  JOIN  teachers  JOIN  courses
//    (связующая)      (имя учителя)  (название курса)
//
//  Результат: строки вида {course_id, teacher_id, name, title_ru}
//
$assignments = [];
try {
    $assignments = $pdo->query("
        SELECT
            ct.course_id,
            ct.teacher_id,
            t.name        AS teacher_name,
            t.photo_url,
            t.bio_ru,
            t.bio_et,
            c.title_ru    AS course_title_ru,
            c.title_et    AS course_title_et,
            c.level
        FROM course_teacher ct
        JOIN teachers t ON ct.teacher_id = t.id
        JOIN courses  c ON ct.course_id  = c.id
        ORDER BY c.id, t.name
    ")->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) { $assignments = []; }

// ── 6d. Группируем учителей по курсам в PHP-массив ──────────
//
//  Создаём структуру: $teachers_by_course[course_id] = [список учителей]
//  Это позволяет быстро найти учителей для любого курса без лишних запросов.
//
$teachers_by_course = [];
foreach ($assignments as $row) {
    $cid = (int)$row['course_id'];
    $teachers_by_course[$cid][] = $row;   // добавляем в массив по ключу course_id
}

// ── 6e. Запрос заявок с JOIN по курсам И учителям ───────────
//
//  LEFT JOIN — берёт ВСЕ строки из левой таблицы (registrations),
//  даже если связанной записи в правой нет (курс удалён / учитель не выбран).
//  В таком случае поля справа будут NULL.
//
$registrations = [];
try {
    $registrations = $pdo->query("
        SELECT
            r.id,
            r.student_name,
            r.student_email,
            r.created_at,
            c.title_ru     AS course_ru,
            c.title_et     AS course_et,
            t.name         AS teacher_name
        FROM registrations r
        LEFT JOIN courses  c ON r.course_id  = c.id
        LEFT JOIN teachers t ON r.teacher_id = t.id
        ORDER BY r.created_at DESC
        LIMIT 10
    ")->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) { $registrations = []; }

// ── 6f. Статистика (COUNT) ───────────────────────────────────
//
//  COUNT(*) — считает количество строк.
//  fetchColumn() — возвращает одно значение (первую колонку первой строки).
//
$count_courses      = 0;
$count_teachers     = 0;
$count_registrations = 0;
try {
    $count_courses       = (int)$pdo->query("SELECT COUNT(*) FROM courses")->fetchColumn();
    $count_teachers      = (int)$pdo->query("SELECT COUNT(*) FROM teachers")->fetchColumn();
    $count_registrations = (int)$pdo->query("SELECT COUNT(*) FROM registrations")->fetchColumn();
} catch (PDOException $e) {}

// ── 6g. Проверка наличия таблицы teachers ───────────────────
//
//  SHOW TABLES LIKE — возвращает список таблиц, имя которых совпадает с шаблоном.
//  Используем это для диагностики: существуют ли нужные таблицы.
//
$tables_status = [];
$required_tables = ['courses', 'teachers', 'course_teacher', 'registrations', 'admins'];
foreach ($required_tables as $tbl) {
    try {
        $res = $pdo->query("SHOW TABLES LIKE '$tbl'")->fetchColumn();
        $tables_status[$tbl] = ($res !== false);   // true = таблица есть
    } catch (PDOException $e) {
        $tables_status[$tbl] = false;
    }
}

// ── 6h. Пример prepared statement (защита от SQL-инъекций) ──
//
//  prepare() + execute() — безопасный способ передать данные в запрос.
//  Символ ? — это placeholder (заменитель).
//  PDO подставит значение безопасно, экранировав спецсимволы.
//
//  ОПАСНО  (SQL-инъекция возможна):
//    "SELECT * FROM teachers WHERE id = " . $_GET['id']
//
//  БЕЗОПАСНО (prepared statement):
//    prepare("SELECT * FROM teachers WHERE id = ?")
//    execute([$_GET['id']])
//
$example_teacher = null;
try {
    $example_id = 1;   // ищем учителя с id = 1
    $stmt = $pdo->prepare("SELECT * FROM teachers WHERE id = ?");
    $stmt->execute([$example_id]);
    $example_teacher = $stmt->fetch(PDO::FETCH_ASSOC);
} catch (PDOException $e) {}


// ============================================================
//  ШАГ 7 — Вспомогательные PHP-функции
// ============================================================

/*
 * htmlspecialchars() — экранирует HTML-спецсимволы.
 * Превращает: < > " & → &lt; &gt; &quot; &amp;
 * Защищает от XSS-атак (когда пользователь вводит HTML/JS в форму).
 *
 * Пример:
 *   $input = '<script>alert("взлом")</script>';
 *   echo htmlspecialchars($input);
 *   // Выведет текст, а не выполнит скрипт
 */

/*
 * mb_substr() — обрезает строку с учётом многобайтовых символов (UTF-8).
 * Обычный substr() ломает кириллицу и эстонские буквы (ä, ö, ü, õ).
 * Используем mb_substr() для получения первой буквы имени учителя (инициала).
 *
 * Пример:
 *   mb_substr('Анна', 0, 1, 'UTF-8') → 'А'
 *   mb_substr('Ülle', 0, 1, 'UTF-8') → 'Ü'
 */

/*
 * crc32() — контрольная сумма строки, возвращает число.
 * Используем для генерации стабильного цвета аватара по имени учителя:
 *   $colors[abs(crc32($name)) % count($colors)]
 * Одно имя → всегда один цвет.
 */

// Массив цветов для аватаров учителей
$avatar_colors = ['#4361ee','#3a0ca3','#7209b7','#f72585','#4cc9f0','#06d6a0','#ef476f','#ffd166'];

/**
 * avatarColor — возвращает цвет по имени (детерминированно).
 *
 * @param string $name   Имя учителя
 * @param array  $colors Массив цветов
 * @return string        HEX-цвет, например '#4361ee'
 */
function avatarColor(string $name, array $colors): string {
    // crc32 даёт хэш строки, abs() убирает отрицательные числа,
    // % count($colors) ограничивает индекс диапазоном массива
    return $colors[abs(crc32($name)) % count($colors)];
}

/**
 * getInitials — возвращает инициалы из имени (1-2 буквы).
 *
 * @param string $name   'Анна Иванова' → 'АИ'
 * @return string
 */
function getInitials(string $name): string {
    // preg_match_all — разбиваем на слова по пробелам
    preg_match_all('/\S+/', $name, $matches);
    $words = array_slice($matches[0], 0, 2);   // берём максимум 2 слова
    // array_map применяет функцию к каждому слову
    $initials = array_map(fn($w) => mb_strtoupper(mb_substr($w, 0, 1, 'UTF-8'), 'UTF-8'), $words);
    return implode('', $initials);             // склеиваем
}

?>
<!DOCTYPE html>
<html lang="<?php echo $current_lang; ?>">
<head>
    <meta charset="UTF-8">
    <!--
        UTF-8 — универсальная кодировка, поддерживает все языки мира.
        Без неё кириллица и эстонские буквы могут отображаться как ????
    -->
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <!--
        viewport — делает сайт адаптивным (responsive).
        width=device-width — ширина = ширина экрана устройства.
        initial-scale=1.0  — масштаб по умолчанию.
    -->
    <title>test1.php — Разбор кода EestiKool</title>

    <!--
        Bootstrap 5 — CSS-фреймворк от Twitter.
        Подключаем из CDN (сеть доставки контента) — не нужно скачивать.
        Даёт готовые классы: container, row, col-md-*, card, badge, btn, table...
    -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="css/style.css">

    <style>
        /*
         * <style> внутри <head> — встроенный CSS.
         * Здесь мы дописываем стили специально для этой страницы.
         * В проекте общие стили лежат в css/style.css.
         */

        /* Блок с кодом (отображение SQL / PHP примеров) */
        .code-block {
            background: #1e1e2e;
            color: #cdd6f4;
            border-radius: 8px;
            padding: 16px;
            font-family: 'Courier New', monospace;
            font-size: .85rem;
            white-space: pre-wrap;       /* переносы строк сохраняются */
            overflow-x: auto;
        }
        .code-block .kw  { color: #cba6f7; } /* ключевые слова (SELECT, FROM) */
        .code-block .str { color: #a6e3a1; } /* строки */
        .code-block .cmt { color: #6c7086; } /* комментарии */

        /* Секции объяснений */
        .explain-section {
            border-left: 4px solid #0d6efd;
            padding-left: 16px;
            margin-bottom: 24px;
        }
        .step-badge {
            font-size: 1.1rem;
            font-weight: 700;
            background: #0d6efd;
            color: #fff;
            border-radius: 50%;
            width: 32px; height: 32px;
            display: inline-flex; align-items: center; justify-content: center;
            margin-right: 8px;
        }

        /* Аватар учителя */
        .t-avatar {
            width: 44px; height: 44px; border-radius: 50%;
            font-size: 16px; font-weight: 700;
            display: flex; align-items: center; justify-content: center;
            color: #fff; flex-shrink: 0;
        }
        .t-avatar img { width: 44px; height: 44px; border-radius: 50%; object-fit: cover; }

        /* Схема таблиц */
        .db-table-schema {
            border: 2px solid #dee2e6;
            border-radius: 8px; overflow: hidden;
            font-size: .82rem;
        }
        .db-table-schema .table-header {
            background: #343a40; color: #fff;
            padding: 6px 12px; font-weight: 700;
        }
        .db-table-schema .field-pk   { color: #ffd166; }
        .db-table-schema .field-fk   { color: #4cc9f0; }
        .db-table-schema .field-new  { color: #06d6a0; }

        /* Стрелки между схемами */
        .relation-arrow {
            font-size: 1.5rem; color: #adb5bd;
            display: flex; align-items: center; justify-content: center;
        }
    </style>
</head>
<body class="bg-light">

<!-- ============================================================
     НАВИГАЦИЯ
     Компонент Bootstrap: navbar navbar-expand-lg
     ============================================================ -->
<nav class="navbar navbar-expand-lg navbar-dark bg-dark">
    <div class="container">
        <a class="navbar-brand" href="index.php">🇪🇪 EestiKool</a>
        <div class="collapse navbar-collapse">
            <ul class="navbar-nav ms-auto">
                <li class="nav-item"><a class="nav-link" href="index.php">Главная</a></li>
                <li class="nav-item"><a class="nav-link" href="courses.php">Курсы</a></li>
                <li class="nav-item"><a class="nav-link" href="contact.php">Записаться</a></li>
                <li class="nav-item"><a class="nav-link" href="admin.php">Админка</a></li>
                <li class="nav-item ms-3">
                    <!-- Переключатель языка — передаёт ?lang=ru / ?lang=et в URL -->
                    <a href="?lang=ru" class="btn btn-sm btn-outline-light <?php echo ($current_lang=='ru') ? 'active' : ''; ?>">RU</a>
                    <a href="?lang=et" class="btn btn-sm btn-outline-light <?php echo ($current_lang=='et') ? 'active' : ''; ?>">ET</a>
                </li>
            </ul>
        </div>
    </div>
</nav>

<div class="container my-4">

    <!-- Заголовок -->
    <div class="text-center mb-5">
        <h1 class="display-5 fw-bold">📂 test1.php</h1>
        <p class="lead text-muted">
            Полный разбор архитектуры проекта <strong>EestiKool</strong>:
            база данных, PHP, HTML, Bootstrap, JavaScript
        </p>
        <div class="d-flex justify-content-center gap-2 flex-wrap">
            <span class="badge bg-primary fs-6">PHP 8+</span>
            <span class="badge bg-success fs-6">MySQL / PDO</span>
            <span class="badge bg-dark fs-6">Bootstrap 5</span>
            <span class="badge bg-warning text-dark fs-6">JavaScript</span>
            <span class="badge bg-danger fs-6">Sessions</span>
        </div>
    </div>


    <!-- ══════════════════════════════════════════════════════
         БЛОК 1 — СОСТОЯНИЕ БАЗЫ ДАННЫХ
         ══════════════════════════════════════════════════════ -->
    <div class="card shadow-sm mb-4">
        <div class="card-header bg-dark text-white fw-bold">
            <span class="step-badge">1</span>
            🗄 Состояние базы данных <code>eesti_keel_db</code>
        </div>
        <div class="card-body">
            <p class="text-muted small">
                Проверяем наличие всех таблиц командой <code>SHOW TABLES LIKE '...'</code>.
                Таблицы <span class="badge bg-success">teachers</span> и
                <span class="badge bg-success">course_teacher</span> созданы автоматически
                при первом открытии <code>admin.php</code>.
            </p>

            <!-- Плашки статуса таблиц -->
            <div class="d-flex flex-wrap gap-3 mb-4">
                <?php foreach ($tables_status as $tbl => $exists): ?>
                <div class="d-flex align-items-center gap-2 border rounded px-3 py-2 bg-white">
                    <span style="font-size:1.3rem"><?php echo $exists ? '✅' : '❌'; ?></span>
                    <div>
                        <div class="fw-bold font-monospace"><?php echo $tbl; ?></div>
                        <div class="text-muted small"><?php echo $exists ? 'Существует' : 'Не найдена'; ?></div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>

            <!-- Счётчики строк -->
            <div class="row g-3">
                <div class="col-sm-4">
                    <div class="card border-0 bg-primary text-white text-center py-3">
                        <div style="font-size:2rem;font-weight:700"><?php echo $count_courses; ?></div>
                        <div>Курсов в таблице</div>
                    </div>
                </div>
                <div class="col-sm-4">
                    <div class="card border-0 bg-success text-white text-center py-3">
                        <div style="font-size:2rem;font-weight:700"><?php echo $count_teachers; ?></div>
                        <div>Преподавателей</div>
                    </div>
                </div>
                <div class="col-sm-4">
                    <div class="card border-0 bg-warning text-dark text-center py-3">
                        <div style="font-size:2rem;font-weight:700"><?php echo $count_registrations; ?></div>
                        <div>Заявок студентов</div>
                    </div>
                </div>
            </div>
        </div>
    </div>


    <!-- ══════════════════════════════════════════════════════
         БЛОК 2 — СХЕМА БАЗЫ ДАННЫХ
         ══════════════════════════════════════════════════════ -->
    <div class="card shadow-sm mb-4">
        <div class="card-header bg-dark text-white fw-bold">
            <span class="step-badge">2</span>
            📐 Схема базы данных — структура таблиц и связи
        </div>
        <div class="card-body">
            <p class="text-muted small mb-4">
                <span class="badge bg-warning text-dark">🔑 PK</span> — Primary Key (уникальный идентификатор строки, автоинкремент).<br>
                <span class="badge bg-info text-dark">🔗 FK</span> — Foreign Key (внешний ключ — ссылка на другую таблицу).<br>
                <span class="badge bg-success">🆕 NEW</span> — поле, добавленное в этом проекте.
            </p>

            <div class="row align-items-center g-3">

                <!-- Таблица: courses -->
                <div class="col-md-3">
                    <div class="db-table-schema">
                        <div class="table-header">📚 courses</div>
                        <table class="table table-sm table-bordered mb-0 small">
                            <tr><td class="field-pk">🔑 id</td><td>INT AUTO_INCREMENT</td></tr>
                            <tr><td>title_ru</td><td>VARCHAR(255)</td></tr>
                            <tr><td>title_et</td><td>VARCHAR(255)</td></tr>
                            <tr><td>description_ru</td><td>TEXT</td></tr>
                            <tr><td>description_et</td><td>TEXT</td></tr>
                            <tr><td>level</td><td>VARCHAR(10)</td></tr>
                            <tr><td>price</td><td>DECIMAL</td></tr>
                        </table>
                    </div>
                </div>

                <!-- Стрелка -->
                <div class="col-md-1 relation-arrow text-center">↔</div>

                <!-- Таблица: course_teacher -->
                <div class="col-md-2">
                    <div class="db-table-schema">
                        <div class="table-header" style="background:#7209b7">🔗 course_teacher</div>
                        <table class="table table-sm table-bordered mb-0 small">
                            <tr><td class="field-fk">🔗 course_id</td><td>INT</td></tr>
                            <tr><td class="field-fk">🔗 teacher_id</td><td>INT</td></tr>
                            <tr><td colspan="2" class="text-muted">PRIMARY KEY (course_id, teacher_id)</td></tr>
                        </table>
                        <div class="p-2 small text-muted">Many-to-Many:<br>«многие-ко-многим»</div>
                    </div>
                </div>

                <!-- Стрелка -->
                <div class="col-md-1 relation-arrow text-center">↔</div>

                <!-- Таблица: teachers -->
                <div class="col-md-2">
                    <div class="db-table-schema">
                        <div class="table-header" style="background:#06d6a0;color:#000">👨‍🏫 teachers</div>
                        <table class="table table-sm table-bordered mb-0 small">
                            <tr><td class="field-pk">🔑 id</td><td>INT AUTO_INCREMENT</td></tr>
                            <tr><td class="field-new">🆕 name</td><td>VARCHAR(100)</td></tr>
                            <tr><td class="field-new">🆕 bio_ru</td><td>TEXT</td></tr>
                            <tr><td class="field-new">🆕 bio_et</td><td>TEXT</td></tr>
                            <tr><td class="field-new">🆕 photo_url</td><td>VARCHAR(255)</td></tr>
                            <tr><td>created_at</td><td>TIMESTAMP</td></tr>
                        </table>
                    </div>
                </div>

                <!-- Стрелка -->
                <div class="col-md-1 relation-arrow text-center">↓</div>

                <!-- Таблица: registrations -->
                <div class="col-md-2">
                    <div class="db-table-schema">
                        <div class="table-header" style="background:#ef476f">📋 registrations</div>
                        <table class="table table-sm table-bordered mb-0 small">
                            <tr><td class="field-pk">🔑 id</td><td>INT AUTO_INCREMENT</td></tr>
                            <tr><td>student_name</td><td>VARCHAR(100)</td></tr>
                            <tr><td>student_email</td><td>VARCHAR(100)</td></tr>
                            <tr><td class="field-fk">🔗 course_id</td><td>INT</td></tr>
                            <tr><td class="field-fk field-new">🆕🔗 teacher_id</td><td>INT</td></tr>
                            <tr><td>created_at</td><td>TIMESTAMP</td></tr>
                        </table>
                    </div>
                </div>

            </div><!-- /row -->
        </div>
    </div>


    <!-- ══════════════════════════════════════════════════════
         БЛОК 3 — ПРИМЕРЫ SQL-ЗАПРОСОВ
         ══════════════════════════════════════════════════════ -->
    <div class="card shadow-sm mb-4">
        <div class="card-header bg-dark text-white fw-bold">
            <span class="step-badge">3</span>
            🔍 Примеры SQL-запросов
        </div>
        <div class="card-body">

            <!-- SELECT простой -->
            <div class="explain-section">
                <h6 class="fw-bold">📌 SELECT — получить все курсы</h6>
                <div class="code-block">SELECT * FROM courses ORDER BY id;</div>
                <p class="mt-2 small text-muted">
                    <strong>SELECT *</strong> — выбрать все столбцы.<br>
                    <strong>FROM courses</strong> — из таблицы courses.<br>
                    <strong>ORDER BY id</strong> — сортировка по id (по возрастанию).
                </p>
            </div>

            <!-- INSERT -->
            <div class="explain-section">
                <h6 class="fw-bold">📌 INSERT — добавить нового учителя</h6>
                <div class="code-block">INSERT INTO teachers (name, bio_ru, bio_et, photo_url)
VALUES (?, ?, ?, ?);
-- Знаки ? — placeholders (prepared statement).
-- Реальные значения передаём через $stmt->execute([$name, $bio_ru, $bio_et, $photo])
-- Это ЗАЩИТА от SQL-инъекций.</div>
                <p class="mt-2 small text-muted">
                    <strong>Prepared statement</strong> — PDO подставляет значения безопасно,
                    экранируя спецсимволы. Никакой инъекции не пройдёт.
                </p>
            </div>

            <!-- JOIN -->
            <div class="explain-section">
                <h6 class="fw-bold">📌 JOIN — получить учителей для каждого курса</h6>
                <div class="code-block">SELECT
    c.title_ru   AS course_title,
    t.name       AS teacher_name,
    t.photo_url
FROM course_teacher ct          -- связующая таблица
JOIN teachers t ON ct.teacher_id = t.id  -- добавляем данные учителя
JOIN courses  c ON ct.course_id  = c.id  -- добавляем данные курса
ORDER BY c.id, t.name;</div>
                <p class="mt-2 small text-muted">
                    <strong>JOIN</strong> (INNER JOIN) — возвращает только строки, где есть совпадение в обеих таблицах.<br>
                    <strong>LEFT JOIN</strong> — возвращает все строки из левой таблицы, даже если совпадений нет (тогда NULL).
                </p>
            </div>

            <!-- ALTER TABLE -->
            <div class="explain-section">
                <h6 class="fw-bold">📌 ALTER TABLE — добавить столбец в существующую таблицу</h6>
                <div class="code-block">ALTER TABLE registrations ADD COLUMN teacher_id INT DEFAULT NULL;
-- Выполнялся один раз при первом открытии admin.php
-- Если столбец уже существует — MySQL выбросит ошибку,
-- поэтому мы обернули в try/catch в PHP.</div>
            </div>

            <!-- CREATE TABLE IF NOT EXISTS -->
            <div class="explain-section mb-0">
                <h6 class="fw-bold">📌 CREATE TABLE IF NOT EXISTS — безопасное создание таблицы</h6>
                <div class="code-block">CREATE TABLE IF NOT EXISTS teachers (
    id         INT AUTO_INCREMENT PRIMARY KEY,  -- уникальный ID, автоинкремент
    name       VARCHAR(100) NOT NULL,           -- имя (обязательное поле)
    bio_ru     TEXT,                            -- биография на русском
    bio_et     TEXT,                            -- биография на эстонском
    photo_url  VARCHAR(255) DEFAULT NULL,       -- URL фото (необязательно)
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP  -- дата добавления
);
-- IF NOT EXISTS — не выдаст ошибку, если таблица уже есть.</div>
            </div>

        </div>
    </div>


    <!-- ══════════════════════════════════════════════════════
         БЛОК 4 — ДАННЫЕ ИЗ БД: КУРСЫ + УЧИТЕЛЯ
         ══════════════════════════════════════════════════════ -->
    <div class="card shadow-sm mb-4">
        <div class="card-header bg-dark text-white fw-bold">
            <span class="step-badge">4</span>
            📚 Курсы и назначенные преподаватели (из БД)
        </div>
        <div class="card-body">

            <?php if (empty($courses)): ?>
                <!-- Оператор if/else в PHP внутри HTML -->
                <div class="alert alert-warning">
                    ⚠ Таблица <code>courses</code> пуста или не существует.
                    Добавьте данные через phpMyAdmin.
                </div>
            <?php else: ?>

                <p class="text-muted small mb-3">
                    PHP-цикл <strong>foreach</strong> проходит по массиву курсов из БД
                    и для каждого выводит карточку. Учителя подтягиваются из
                    <code>$teachers_by_course[course_id]</code>.
                </p>

                <div class="row g-3">
                <?php foreach ($courses as $c): ?>
                    <!--
                        $c — ассоциативный массив одного курса.
                        Ключи массива = названия столбцов в БД.
                        $c['title_ru'], $c['level'], $c['price'] и т.д.
                    -->
                    <?php
                        // Получаем учителей для этого курса
                        // ?? [] — если ключа нет, вернуть пустой массив
                        $t_list = $teachers_by_course[(int)$c['id']] ?? [];

                        // Выбираем заголовок в зависимости от языка
                        // Тернарный оператор: условие ? если_да : если_нет
                        $title = ($current_lang === 'et') ? $c['title_et'] : $c['title_ru'];
                        $desc  = ($current_lang === 'et') ? $c['description_et'] : $c['description_ru'];
                    ?>
                    <div class="col-md-4">
                        <div class="card h-100 border-0 shadow-sm">
                            <div class="card-body">
                                <span class="badge bg-secondary mb-2">
                                    <?php echo htmlspecialchars($c['level']); ?>
                                </span>
                                <h5><?php echo htmlspecialchars($title); ?></h5>
                                <p class="text-muted small"><?php echo htmlspecialchars($desc); ?></p>
                                <p class="text-success fw-bold"><?php echo htmlspecialchars($c['price']); ?> €</p>

                                <!-- Учителя курса -->
                                <div class="border-top pt-2 mt-2">
                                    <small class="text-muted">👨‍🏫 Преподаватели:</small>
                                    <?php if (empty($t_list)): ?>
                                        <div class="text-muted small fst-italic">не назначены</div>
                                    <?php else: ?>
                                        <?php foreach ($t_list as $t): ?>
                                            <?php
                                                // Генерируем инициалы и цвет аватара
                                                $initials = getInitials($t['teacher_name']);
                                                $color    = avatarColor($t['teacher_name'], $avatar_colors);
                                                $bio      = ($current_lang === 'et') ? $t['bio_et'] : $t['bio_ru'];
                                            ?>
                                            <div class="d-flex align-items-center gap-2 mt-2">
                                                <?php if (!empty($t['photo_url'])): ?>
                                                    <!-- Если фото есть — показываем изображение -->
                                                    <img src="<?php echo htmlspecialchars($t['photo_url']); ?>"
                                                         class="t-avatar" style="width:36px;height:36px;border-radius:50%;object-fit:cover">
                                                <?php else: ?>
                                                    <!-- Иначе — цветной круг с инициалами -->
                                                    <div class="t-avatar" style="background:<?php echo $color; ?>;width:36px;height:36px;font-size:13px">
                                                        <?php echo $initials; ?>
                                                    </div>
                                                <?php endif; ?>
                                                <div>
                                                    <div class="fw-semibold small">
                                                        <?php echo htmlspecialchars($t['teacher_name']); ?>
                                                    </div>
                                                    <?php if ($bio): ?>
                                                    <div class="text-muted" style="font-size:.75rem">
                                                        <?php echo htmlspecialchars($bio); ?>
                                                    </div>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </div>

                            </div><!-- /card-body -->

                            <!-- Кнопка передаёт course_id в URL -->
                            <div class="card-footer bg-transparent border-0 pb-3">
                                <a href="contact.php?course_id=<?php echo (int)$c['id']; ?>"
                                   class="btn btn-outline-primary btn-sm w-100">
                                   Записаться → contact.php?course_id=<?php echo (int)$c['id']; ?>
                                </a>
                            </div>
                        </div>
                    </div>
                <?php endforeach; // конец цикла foreach ?>
                </div>

            <?php endif; ?>
        </div>
    </div>


    <!-- ══════════════════════════════════════════════════════
         БЛОК 5 — СПИСОК ВСЕХ УЧИТЕЛЕЙ
         ══════════════════════════════════════════════════════ -->
    <div class="card shadow-sm mb-4">
        <div class="card-header bg-dark text-white fw-bold">
            <span class="step-badge">5</span>
            👨‍🏫 Все преподаватели в таблице <code>teachers</code>
        </div>
        <div class="card-body">
            <?php if (empty($teachers)): ?>
                <div class="alert alert-info">
                    ℹ Преподавателей пока нет. Добавьте их через
                    <a href="admin.php?tab=teachers">Админ-панель → вкладка «Преподаватели»</a>.
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-hover align-middle">
                        <thead class="table-dark">
                            <tr>
                                <th>id</th>
                                <th>Аватар</th>
                                <th>name</th>
                                <th>bio_ru</th>
                                <th>bio_et</th>
                                <th>photo_url</th>
                                <th>created_at</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($teachers as $t): ?>
                            <?php
                                $initials = getInitials($t['name']);
                                $color    = avatarColor($t['name'], $avatar_colors);
                            ?>
                            <tr>
                                <td><code><?php echo $t['id']; ?></code></td>
                                <td>
                                    <?php if ($t['photo_url']): ?>
                                        <img src="<?php echo htmlspecialchars($t['photo_url']); ?>"
                                             style="width:38px;height:38px;border-radius:50%;object-fit:cover">
                                    <?php else: ?>
                                        <div class="t-avatar" style="background:<?php echo $color; ?>;font-size:14px">
                                            <?php echo $initials; ?>
                                        </div>
                                    <?php endif; ?>
                                </td>
                                <td><strong><?php echo htmlspecialchars($t['name']); ?></strong></td>
                                <td class="small text-muted"><?php echo htmlspecialchars($t['bio_ru'] ?? '—'); ?></td>
                                <td class="small text-muted"><?php echo htmlspecialchars($t['bio_et'] ?? '—'); ?></td>
                                <td class="small">
                                    <?php if ($t['photo_url']): ?>
                                        <a href="<?php echo htmlspecialchars($t['photo_url']); ?>" target="_blank" class="text-truncate d-block" style="max-width:120px">
                                            <?php echo htmlspecialchars($t['photo_url']); ?>
                                        </a>
                                    <?php else: ?>
                                        <span class="text-muted">NULL</span>
                                    <?php endif; ?>
                                </td>
                                <td class="small"><?php echo htmlspecialchars($t['created_at']); ?></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>


    <!-- ══════════════════════════════════════════════════════
         БЛОК 6 — ПОСЛЕДНИЕ ЗАЯВКИ
         ══════════════════════════════════════════════════════ -->
    <div class="card shadow-sm mb-4">
        <div class="card-header bg-dark text-white fw-bold">
            <span class="step-badge">6</span>
            📋 Последние 10 заявок (LEFT JOIN по 3 таблицам)
        </div>
        <div class="card-body">
            <div class="code-block mb-3">SELECT r.id, r.student_name, r.student_email, r.created_at,
       c.title_ru AS course_ru,
       t.name     AS teacher_name
FROM registrations r
LEFT JOIN courses  c ON r.course_id  = c.id
LEFT JOIN teachers t ON r.teacher_id = t.id
ORDER BY r.created_at DESC
LIMIT 10;</div>

            <?php if (empty($registrations)): ?>
                <div class="alert alert-secondary">Заявок пока нет.</div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-hover table-sm align-middle">
                        <thead class="table-dark">
                            <tr>
                                <th>ID</th>
                                <th>Студент</th>
                                <th>Email</th>
                                <th>Курс</th>
                                <th>Преподаватель</th>
                                <th>Дата</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($registrations as $reg): ?>
                            <tr>
                                <td><code><?php echo $reg['id']; ?></code></td>
                                <td><?php echo htmlspecialchars($reg['student_name']); ?></td>
                                <td><a href="mailto:<?php echo htmlspecialchars($reg['student_email']); ?>">
                                    <?php echo htmlspecialchars($reg['student_email']); ?></a></td>
                                <td>
                                    <?php if ($reg['course_ru']): ?>
                                        <span class="badge bg-primary">
                                            <?php echo htmlspecialchars($reg['course_ru']); ?>
                                        </span>
                                    <?php else: ?>
                                        <span class="text-muted">NULL</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($reg['teacher_name']): ?>
                                        <span class="badge bg-success">
                                            👨‍🏫 <?php echo htmlspecialchars($reg['teacher_name']); ?>
                                        </span>
                                    <?php else: ?>
                                        <span class="text-muted small">не выбран</span>
                                    <?php endif; ?>
                                </td>
                                <td class="small">
                                    <?php echo date('d.m.Y H:i', strtotime($reg['created_at'])); ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>


    <!-- ══════════════════════════════════════════════════════
         БЛОК 7 — КАК РАБОТАЕТ ВЫБОР УЧИТЕЛЯ (JavaScript)
         ══════════════════════════════════════════════════════ -->
    <div class="card shadow-sm mb-4">
        <div class="card-header bg-dark text-white fw-bold">
            <span class="step-badge">7</span>
            ⚡ Как работает динамический выбор учителя (JavaScript)
        </div>
        <div class="card-body">
            <p class="text-muted small mb-3">
                В <code>contact.php</code> PHP передаёт данные в JavaScript через <code>json_encode()</code>.
                Когда пользователь меняет курс — JS фильтрует список учителей без перезагрузки страницы.
            </p>

            <h6 class="fw-bold">1️⃣ PHP → JavaScript: передача данных</h6>
            <div class="code-block mb-3">&lt;script&gt;
// json_encode() превращает PHP-массив в JSON-строку для JavaScript.
// JSON_UNESCAPED_UNICODE — сохраняет кириллицу/эстонские буквы как есть.
const teachersByCourse = &lt;?php echo json_encode($teachers_by_course, JSON_UNESCAPED_UNICODE); ?&gt;;

// Пример результата в браузере:
// teachersByCourse = {
//   "1": [{"id":1,"name":"Анна Иванова","photo":""}],
//   "2": [{"id":2,"name":"Ülle Tamm","photo":""}]
// }
&lt;/script&gt;</div>

            <h6 class="fw-bold">2️⃣ Слушатель события: смена курса</h6>
            <div class="code-block mb-3">// addEventListener — подписываемся на событие 'change' (выбор опции в &lt;select&gt;)
document.getElementById('course_select').addEventListener('change', function() {
    const courseId = parseInt(this.value) || 0;  // id выбранного курса
    document.getElementById('hidden_course_id').value = courseId; // обновляем hidden-поле
    renderTeachers(courseId);  // перерисовываем карточки учителей
});</div>

            <h6 class="fw-bold">3️⃣ Функция рендера карточек учителей</h6>
            <div class="code-block mb-3">function renderTeachers(courseId) {
    const container = document.getElementById('teacher_cards');
    container.innerHTML = '';  // очищаем контейнер

    const teachers = teachersByCourse[courseId] || [];  // достаём из JS-объекта

    if (teachers.length === 0) {
        container.innerHTML = '&lt;p&gt;Преподаватели не назначены&lt;/p&gt;';
        return;
    }

    teachers.forEach(t =&gt; {
        const card = document.createElement('div');   // создаём div
        card.className = 'teacher-card';
        card.innerHTML = `&lt;strong&gt;${t.name}&lt;/strong&gt;`;
        // При клике — записываем id учителя в hidden-поле формы
        card.addEventListener('click', () =&gt; {
            document.getElementById('hidden_teacher_id').value = t.id;
            // Выделяем выбранную карточку
            document.querySelectorAll('.teacher-card')
                    .forEach(c =&gt; c.classList.remove('selected'));
            card.classList.add('selected');
        });
        container.appendChild(card);  // добавляем в DOM
    });
}</div>

            <h6 class="fw-bold">4️⃣ Скрытые поля формы — передача данных в PHP</h6>
            <div class="code-block">&lt;!-- Скрытые поля — пользователь их не видит, но они отправляются в POST --&gt;
&lt;input type="hidden" name="course_id"  id="hidden_course_id"  value=""&gt;
&lt;input type="hidden" name="teacher_id" id="hidden_teacher_id" value=""&gt;

&lt;!-- В PHP читаем их так: --&gt;
$course_id  = (int)($_POST['course_id']  ?? 0) ?: null;
$teacher_id = (int)($_POST['teacher_id'] ?? 0) ?: null;
// (int) — приводим к целому числу (защита)
// ?? 0  — если ключа нет — берём 0
// ?: null — если 0 — делаем NULL (для БД)</div>
        </div>
    </div>


    <!-- ══════════════════════════════════════════════════════
         БЛОК 8 — СЕССИИ И МНОГОЯЗЫЧНОСТЬ
         ══════════════════════════════════════════════════════ -->
    <div class="card shadow-sm mb-4">
        <div class="card-header bg-dark text-white fw-bold">
            <span class="step-badge">8</span>
            🌍 Сессии и многоязычность — как это работает
        </div>
        <div class="card-body">
            <div class="row g-3">
                <div class="col-md-6">
                    <h6 class="fw-bold">Текущее состояние сессии</h6>
                    <table class="table table-sm table-bordered">
                        <tr>
                            <td><code>$_SESSION['lang']</code></td>
                            <td>
                                <span class="badge bg-primary">
                                    <?php echo htmlspecialchars($_SESSION['lang'] ?? 'не задан'); ?>
                                </span>
                            </td>
                        </tr>
                        <tr>
                            <td><code>$current_lang</code></td>
                            <td><code><?php echo htmlspecialchars($current_lang); ?></code></td>
                        </tr>
                        <tr>
                            <td>Загружен файл</td>
                            <td><code>lang/<?php echo $current_lang; ?>.php</code></td>
                        </tr>
                        <tr>
                            <td><code>$lang['teachers']</code></td>
                            <td><?php echo htmlspecialchars($lang['teachers']); ?></td>
                        </tr>
                        <tr>
                            <td><code>$lang['choose_teacher']</code></td>
                            <td><?php echo htmlspecialchars($lang['choose_teacher']); ?></td>
                        </tr>
                    </table>
                    <div class="d-flex gap-2 mt-2">
                        <a href="?lang=ru" class="btn btn-sm btn-outline-danger">Переключить на RU</a>
                        <a href="?lang=et" class="btn btn-sm btn-outline-primary">Переключить на ET</a>
                    </div>
                </div>
                <div class="col-md-6">
                    <h6 class="fw-bold">Как работает переключение языка</h6>
                    <div class="code-block" style="font-size:.78rem">// 1. Пользователь кликает кнопку RU или ET
//    &lt;a href="?lang=ru"&gt;RU&lt;/a&gt;
//    URL становится: courses.php?lang=ru

// 2. PHP читает GET-параметр
if (isset($_GET['lang'])) {
    $_SESSION['lang'] = $_GET['lang']; // 'ru' или 'et'
}

// 3. Определяем текущий язык
$current_lang = $_SESSION['lang'] ?? 'ru';

// 4. Подключаем нужный языковой файл
if ($current_lang == 'et') {
    include 'lang/et.php'; // $lang['courses'] = 'Kursused'
} else {
    include 'lang/ru.php'; // $lang['courses'] = 'Курсы'
}

// 5. Используем в HTML
echo $lang['courses']; // выведет 'Kursused' или 'Курсы'</div>
                </div>
            </div>
        </div>
    </div>


    <!-- ══════════════════════════════════════════════════════
         БЛОК 9 — PREPARED STATEMENT (БЕЗОПАСНОСТЬ)
         ══════════════════════════════════════════════════════ -->
    <div class="card shadow-sm mb-4">
        <div class="card-header bg-dark text-white fw-bold">
            <span class="step-badge">9</span>
            🔒 Безопасность: Prepared Statements vs SQL-инъекция
        </div>
        <div class="card-body">
            <div class="row g-3">
                <div class="col-md-6">
                    <div class="alert alert-danger">
                        <strong>❌ ОПАСНО — SQL-инъекция</strong>
                    </div>
                    <div class="code-block" style="font-size:.8rem">// Пользователь ввёл в поле имя:
// "'; DROP TABLE registrations; --"

$name = $_POST['name']; // опасно!

$sql = "INSERT INTO registrations
        (student_name) VALUES ('$name')";

// Итоговый SQL:
// INSERT INTO registrations (student_name)
//   VALUES (''; DROP TABLE registrations; --')
//
// Вся таблица заявок будет удалена! 💀</div>
                </div>
                <div class="col-md-6">
                    <div class="alert alert-success">
                        <strong>✅ БЕЗОПАСНО — Prepared Statement</strong>
                    </div>
                    <div class="code-block" style="font-size:.8rem">// Тот же ввод, но через prepare():
$name = $_POST['name']; // любой текст

$stmt = $pdo->prepare(
    "INSERT INTO registrations
     (student_name, student_email, course_id, teacher_id)
     VALUES (?, ?, ?, ?)"
);

// PDO экранирует спецсимволы автоматически
$stmt->execute([$name, $email, $course_id, $teacher_id]);

// SQL-инъекция невозможна ✅
// Имя сохранится как обычный текст</div>
                </div>
            </div>
        </div>
    </div>


    <!-- ══════════════════════════════════════════════════════
         БЛОК 10 — АРХИТЕКТУРА ПРОЕКТА (Файловая структура)
         ══════════════════════════════════════════════════════ -->
    <div class="card shadow-sm mb-4">
        <div class="card-header bg-dark text-white fw-bold">
            <span class="step-badge">10</span>
            🗂 Файловая структура проекта
        </div>
        <div class="card-body">
            <div class="row g-4">
                <div class="col-md-6">
                    <div class="code-block" style="font-size:.82rem">PHPnope/
│
├── index.php        ← Главная страница
│                      Мини-тест по уровню эстонского
│                      Навигация, мультиязычность
│
├── courses.php      ← Список курсов из БД
│                      Карточки с уровнем и ценой
│                      👨‍🏫 Аватары преподавателей курса
│                      Кнопка → contact.php?course_id=X
│
├── contact.php      ← Форма записи на курс
│                      Выбор курса (select)
│                      👨‍🏫 Динамические карточки учителей
│                      Сохранение в таблицу registrations
│
├── admin.php        ← Панель администратора
│                      Вкладка: Заявки студентов
│                      Вкладка: 👨‍🏫 Управление учителями
│                        – Добавить / удалить учителя
│                        – Назначить на курс / снять
│
├── db.php           ← Подключение к MySQL (PDO)
│                      $pdo = new PDO(...)
│
├── test.php         ← Простой тест (таблица HTML)
├── test1.php        ← ЭТОТ ФАЙЛ — разбор архитектуры
│
├── lang/
│   ├── ru.php       ← Переводы на русский ($lang[...])
│   └── et.php       ← Переводы на эстонский
│
├── css/
│   └── style.css    ← Стили (Bootstrap + кастомные)
│
└── js/
    └── main.js      ← JavaScript (анимация формы)</div>
                </div>
                <div class="col-md-6">
                    <h6 class="fw-bold mb-3">Схема работы при записи студента на курс:</h6>
                    <div class="border rounded p-3 bg-white">
                        <div class="d-flex flex-column gap-2" style="font-size:.88rem">
                            <div class="d-flex align-items-center gap-2">
                                <span class="badge bg-primary">1</span>
                                Студент открывает <strong>courses.php</strong>
                            </div>
                            <div class="text-center text-muted">↓ видит курсы и преподавателей</div>
                            <div class="d-flex align-items-center gap-2">
                                <span class="badge bg-primary">2</span>
                                Нажимает «Записаться» → переход на
                                <code>contact.php?course_id=2</code>
                            </div>
                            <div class="text-center text-muted">↓ PHP читает GET['course_id']</div>
                            <div class="d-flex align-items-center gap-2">
                                <span class="badge bg-primary">3</span>
                                JS загружает учителей из JSON,
                                показывает карточки
                            </div>
                            <div class="text-center text-muted">↓ студент кликает карточку</div>
                            <div class="d-flex align-items-center gap-2">
                                <span class="badge bg-primary">4</span>
                                JS записывает <code>teacher_id</code> в hidden-поле
                            </div>
                            <div class="text-center text-muted">↓ POST-форма отправляется</div>
                            <div class="d-flex align-items-center gap-2">
                                <span class="badge bg-success">5</span>
                                PHP сохраняет в таблицу <code>registrations</code>:
                                name, email, course_id, teacher_id
                            </div>
                            <div class="text-center text-muted">↓</div>
                            <div class="d-flex align-items-center gap-2">
                                <span class="badge bg-danger">6</span>
                                Администратор видит заявку в <strong>admin.php</strong>
                                с именем учителя
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>


    <!-- Нижняя навигация -->
    <div class="d-flex flex-wrap justify-content-center gap-2 my-4">
        <a href="index.php"   class="btn btn-dark">🏠 Главная</a>
        <a href="courses.php" class="btn btn-primary">📚 Курсы (с учителями)</a>
        <a href="contact.php" class="btn btn-success">📝 Форма записи</a>
        <a href="admin.php?tab=teachers" class="btn btn-warning">👨‍🏫 Управление учителями</a>
        <a href="admin.php"   class="btn btn-danger">🛡 Админ-панель</a>
    </div>

</div><!-- /container -->

<!--
    Bootstrap JS (bundle включает Popper.js для Tooltip, Dropdown и т.д.)
    Подключается в конце <body> — чтобы страница загружалась быстрее.
-->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>

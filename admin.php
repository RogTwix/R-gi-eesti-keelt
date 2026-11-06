<?php
session_start();
require_once 'db.php';

// ════════════════════════════════════════
//  МИГРАЦИЯ БД (выполняется автоматически)
// ════════════════════════════════════════
$pdo->exec("CREATE TABLE IF NOT EXISTS admins (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL
)");

$pdo->exec("CREATE TABLE IF NOT EXISTS teachers (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    bio_ru TEXT,
    bio_et TEXT,
    photo_url VARCHAR(255) DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)");

$pdo->exec("CREATE TABLE IF NOT EXISTS course_teacher (
    course_id INT NOT NULL,
    teacher_id INT NOT NULL,
    PRIMARY KEY (course_id, teacher_id)
)");

// Добавить teacher_id в registrations (если ещё нет)
try {
    $pdo->exec("ALTER TABLE registrations ADD COLUMN teacher_id INT DEFAULT NULL");
} catch (PDOException $e) { /* колонка уже существует */ }

// Дефолтный admin-пользователь
$stmt = $pdo->query("SELECT COUNT(*) FROM admins");
if ($stmt->fetchColumn() == 0) {
    $hash = password_hash('admin123', PASSWORD_DEFAULT);
    $pdo->exec("INSERT INTO admins (username, password) VALUES ('admin', '$hash')");
}

// ════════════════════════════════════════
//  ЭКШЕНЫ
// ════════════════════════════════════════

// Выход
if (isset($_GET['logout'])) {
    session_destroy();
    header("Location: admin.php");
    exit;
}

$is_admin = isset($_SESSION['admin_logged_in']);

// Удаление заявки
if (isset($_GET['delete_id']) && $is_admin) {
    $pdo->prepare("DELETE FROM registrations WHERE id = ?")->execute([(int)$_GET['delete_id']]);
    header("Location: admin.php?tab=registrations");
    exit;
}

// Добавление учителя
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'add_teacher' && $is_admin) {
    $t_name    = trim($_POST['t_name'] ?? '');
    $t_bio_ru  = trim($_POST['t_bio_ru'] ?? '');
    $t_bio_et  = trim($_POST['t_bio_et'] ?? '');
    $t_photo   = trim($_POST['t_photo'] ?? '');
    if ($t_name !== '') {
        $pdo->prepare("INSERT INTO teachers (name, bio_ru, bio_et, photo_url) VALUES (?, ?, ?, ?)")
            ->execute([$t_name, $t_bio_ru, $t_bio_et, $t_photo ?: null]);
    }
    header("Location: admin.php?tab=teachers");
    exit;
}

// Удаление учителя
if (isset($_GET['delete_teacher']) && $is_admin) {
    $tid = (int)$_GET['delete_teacher'];
    $pdo->prepare("DELETE FROM course_teacher WHERE teacher_id = ?")->execute([$tid]);
    $pdo->prepare("DELETE FROM registrations WHERE teacher_id = ?")->execute([$tid]);
    $pdo->prepare("DELETE FROM teachers WHERE id = ?")->execute([$tid]);
    header("Location: admin.php?tab=teachers");
    exit;
}

// Привязка учителя к курсу
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'assign_teacher' && $is_admin) {
    $a_course   = (int)($_POST['a_course_id'] ?? 0);
    $a_teacher  = (int)($_POST['a_teacher_id'] ?? 0);
    if ($a_course && $a_teacher) {
        $pdo->prepare("INSERT IGNORE INTO course_teacher (course_id, teacher_id) VALUES (?, ?)")
            ->execute([$a_course, $a_teacher]);
    }
    header("Location: admin.php?tab=teachers");
    exit;
}

// Снять учителя с курса
if (isset($_GET['unassign']) && isset($_GET['from_course']) && $is_admin) {
    $pdo->prepare("DELETE FROM course_teacher WHERE teacher_id = ? AND course_id = ?")
        ->execute([(int)$_GET['unassign'], (int)$_GET['from_course']]);
    header("Location: admin.php?tab=teachers");
    exit;
}

// Логин
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['login'])) {
    $username = trim($_POST['username']);
    $password = $_POST['password'];
    $stmt = $pdo->prepare("SELECT * FROM admins WHERE username = ?");
    $stmt->execute([$username]);
    $admin = $stmt->fetch();
    if ($admin && password_verify($password, $admin['password'])) {
        $_SESSION['admin_logged_in'] = true;
        header("Location: admin.php");
        exit;
    } else {
        $error = "Неверный логин или пароль!";
    }
}

// ════════════════════════════════════════
//  ДАННЫЕ ДЛЯ ДАШБОРДА
// ════════════════════════════════════════
$registrations = [];
$teachers      = [];
$courses       = [];
$course_teacher_map = [];

if ($is_admin) {
    $registrations = $pdo->query("
        SELECT r.*, c.title_ru, c.title_et, t.name AS teacher_name
        FROM registrations r
        LEFT JOIN courses c ON r.course_id = c.id
        LEFT JOIN teachers t ON r.teacher_id = t.id
        ORDER BY r.created_at DESC
    ")->fetchAll();

    $teachers = $pdo->query("SELECT * FROM teachers ORDER BY name")->fetchAll();
    $courses  = $pdo->query("SELECT * FROM courses ORDER BY id")->fetchAll();

    $ct_rows = $pdo->query("
        SELECT ct.course_id, ct.teacher_id, t.name AS t_name, c.title_ru AS c_title
        FROM course_teacher ct
        JOIN teachers t ON ct.teacher_id = t.id
        JOIN courses c ON ct.course_id = c.id
        ORDER BY c.id, t.name
    ")->fetchAll();

    foreach ($ct_rows as $row) {
        $course_teacher_map[(int)$row['course_id']][] = $row;
    }
}

$active_tab = $_GET['tab'] ?? 'registrations';

// Цвета для аватаров
$avatar_colors = ['#4361ee','#3a0ca3','#7209b7','#f72585','#4cc9f0','#06d6a0','#ef476f','#ffd166'];
function avatarColor($name, $colors) {
    $h = 0;
    for ($i = 0; $i < strlen($name); $i++) $h = ord($name[$i]) + (($h << 5) - $h);
    return $colors[abs($h) % count($colors)];
}
function getInitials($name) {
    preg_match_all('/\S+/', $name, $m);
    return mb_strtoupper(implode('', array_map(fn($w) => mb_substr($w,0,1,'UTF-8'), array_slice($m[0],0,2))), 'UTF-8');
}
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Панель администратора - EestiKool</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="css/style.css">
    <style>
        .teacher-avatar-sm {
            width: 38px; height: 38px; border-radius: 50%;
            object-fit: cover; flex-shrink: 0;
        }
        .teacher-initials-sm {
            width: 38px; height: 38px; border-radius: 50%;
            font-size: 14px; font-weight: 700;
            display: flex; align-items: center; justify-content: center;
            color: #fff; flex-shrink: 0;
        }
        .assign-badge {
            font-size: .75rem;
            display: inline-flex; align-items: center; gap: 4px;
        }
    </style>
</head>
<body class="bg-light">

    <!-- Навбар -->
    <nav class="navbar navbar-expand-lg navbar-dark bg-danger">
        <div class="container">
            <a class="navbar-brand fw-bold" href="admin.php">🛡 Админ-панель</a>
            <div class="collapse navbar-collapse">
                <ul class="navbar-nav ms-auto">
                    <li class="nav-item"><a class="nav-link" href="index.php" target="_blank">Перейти на сайт ↗</a></li>
                    <?php if ($is_admin): ?>
                    <li class="nav-item">
                        <a class="nav-link btn btn-outline-light ms-3" href="admin.php?logout=1">Выйти</a>
                    </li>
                    <?php endif; ?>
                </ul>
            </div>
        </div>
    </nav>

    <div class="container my-5">

    <?php if (!$is_admin): ?>
    <!-- ══════════════════════ ФОРМА ВХОДА ══════════════════════ -->
    <div class="row justify-content-center">
        <div class="col-md-5">
            <div class="card shadow-sm border-0 mt-5">
                <div class="card-body p-4">
                    <h3 class="text-center mb-4">Вход в систему</h3>
                    <?php if ($error): ?>
                        <div class="alert alert-danger"><?php echo $error; ?></div>
                    <?php endif; ?>
                    <form action="admin.php" method="POST">
                        <div class="mb-3">
                            <label class="form-label">Логин</label>
                            <input type="text" name="username" class="form-control" required>
                        </div>
                        <div class="mb-4">
                            <label class="form-label">Пароль</label>
                            <input type="password" name="password" class="form-control" required>
                        </div>
                        <button type="submit" name="login" class="btn btn-danger w-100 fw-bold">Войти</button>
                    </form>
                    <p class="text-muted text-center mt-3 small">Вы админ или сталкер?</p>
                </div>
            </div>
        </div>
    </div>

    <?php else: ?>
    <!-- ══════════════════════ ДАШБОРД ══════════════════════ -->

    <!-- Вкладки -->
    <ul class="nav nav-tabs mb-4" id="adminTabs">
        <li class="nav-item">
            <a class="nav-link <?php echo $active_tab === 'registrations' ? 'active' : ''; ?>"
               href="admin.php?tab=registrations">
                📋 Заявки
                <span class="badge bg-success ms-1"><?php echo count($registrations); ?></span>
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link <?php echo $active_tab === 'teachers' ? 'active' : ''; ?>"
               href="admin.php?tab=teachers">
                👨‍🏫 Преподаватели
                <span class="badge bg-primary ms-1"><?php echo count($teachers); ?></span>
            </a>
        </li>
    </ul>

    <?php if ($active_tab === 'registrations'): ?>
    <!-- ───────────── TAB: ЗАЯВКИ ───────────── -->
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h4 class="mb-0">Управление заявками</h4>
        <span class="badge bg-success fs-6">Всего: <?php echo count($registrations); ?></span>
    </div>

    <div class="card shadow-sm border-0">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead class="table-dark">
                        <tr>
                            <th>ID</th>
                            <th>Дата</th>
                            <th>Студент</th>
                            <th>Email</th>
                            <th>Курс</th>
                            <th>Преподаватель</th>
                            <th class="text-end">Действия</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!empty($registrations)): ?>
                            <?php foreach ($registrations as $reg): ?>
                            <tr>
                                <td><?php echo $reg['id']; ?></td>
                                <td><?php echo date('d.m.Y H:i', strtotime($reg['created_at'])); ?></td>
                                <td class="fw-bold"><?php echo htmlspecialchars($reg['student_name']); ?></td>
                                <td><a href="mailto:<?php echo htmlspecialchars($reg['student_email']); ?>">
                                    <?php echo htmlspecialchars($reg['student_email']); ?></a></td>
                                <td><?php echo htmlspecialchars($reg['title_ru'] ?? '—'); ?></td>
                                <td>
                                    <?php if ($reg['teacher_name']): ?>
                                        <span class="badge bg-info text-dark">
                                            👨‍🏫 <?php echo htmlspecialchars($reg['teacher_name']); ?>
                                        </span>
                                    <?php else: ?>
                                        <span class="text-muted">—</span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-end">
                                    <a href="admin.php?delete_id=<?php echo $reg['id']; ?>&tab=registrations"
                                       class="btn btn-sm btn-outline-danger"
                                       onclick="return confirm('Удалить заявку #<?php echo $reg['id']; ?>?')">
                                       Удалить
                                    </a>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="7" class="text-center py-4 text-muted">
                                    Пока нет ни одной заявки.
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <?php else: ?>
    <!-- ───────────── TAB: ПРЕПОДАВАТЕЛИ ───────────── -->
    <div class="row g-4">

        <!-- Левая колонка: список учителей + форма добавления -->
        <div class="col-lg-7">

            <!-- Список учителей -->
            <div class="card shadow-sm border-0 mb-4">
                <div class="card-header bg-primary text-white fw-bold">
                    👨‍🏫 Список преподавателей
                </div>
                <div class="card-body p-0">
                    <?php if (empty($teachers)): ?>
                        <p class="text-muted text-center py-3">Преподавателей пока нет. Добавьте первого!</p>
                    <?php else: ?>
                    <ul class="list-group list-group-flush">
                        <?php foreach ($teachers as $t):
                            $initials = getInitials($t['name']);
                            $color = avatarColor($t['name'], $avatar_colors);
                        ?>
                        <li class="list-group-item d-flex align-items-start gap-3 py-3">
                            <!-- Аватар -->
                            <?php if (!empty($t['photo_url'])): ?>
                                <img src="<?php echo htmlspecialchars($t['photo_url']); ?>"
                                     alt="<?php echo htmlspecialchars($t['name']); ?>"
                                     class="teacher-avatar-sm">
                            <?php else: ?>
                                <div class="teacher-initials-sm" style="background:<?php echo $color; ?>">
                                    <?php echo $initials; ?>
                                </div>
                            <?php endif; ?>

                            <!-- Инфо -->
                            <div class="flex-grow-1">
                                <strong><?php echo htmlspecialchars($t['name']); ?></strong>
                                <?php if ($t['bio_ru']): ?>
                                    <p class="text-muted small mb-1 mt-1"><?php echo htmlspecialchars($t['bio_ru']); ?></p>
                                <?php endif; ?>

                                <!-- Курсы этого учителя -->
                                <div class="mt-1 d-flex flex-wrap gap-1">
                                    <?php foreach ($courses as $c):
                                        $assigned = false;
                                        foreach (($course_teacher_map[(int)$c['id']] ?? []) as $ct) {
                                            if ((int)$ct['teacher_id'] === (int)$t['id']) { $assigned = true; break; }
                                        }
                                        if ($assigned):
                                    ?>
                                    <span class="badge bg-light text-dark border assign-badge">
                                        📚 <?php echo htmlspecialchars($c['title_ru']); ?>
                                        <a href="admin.php?unassign=<?php echo $t['id']; ?>&from_course=<?php echo $c['id']; ?>&tab=teachers"
                                           class="text-danger ms-1" title="Снять с курса"
                                           onclick="return confirm('Снять <?php echo htmlspecialchars($t['name']); ?> с курса?')">✕</a>
                                    </span>
                                    <?php endif; endforeach; ?>
                                </div>
                            </div>

                            <!-- Удалить -->
                            <a href="admin.php?delete_teacher=<?php echo $t['id']; ?>&tab=teachers"
                               class="btn btn-sm btn-outline-danger align-self-start"
                               onclick="return confirm('Удалить преподавателя «<?php echo htmlspecialchars($t['name']); ?>»?')">
                               🗑
                            </a>
                        </li>
                        <?php endforeach; ?>
                    </ul>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Форма привязки учителя к курсу -->
            <div class="card shadow-sm border-0">
                <div class="card-header bg-warning fw-bold">
                    🔗 Назначить преподавателя на курс
                </div>
                <div class="card-body">
                    <?php if (empty($teachers) || empty($courses)): ?>
                        <p class="text-muted small">Нужен хотя бы один преподаватель и один курс.</p>
                    <?php else: ?>
                    <form action="admin.php" method="POST">
                        <input type="hidden" name="action" value="assign_teacher">
                        <div class="row g-2 align-items-end">
                            <div class="col-sm-5">
                                <label class="form-label small fw-semibold">Преподаватель</label>
                                <select name="a_teacher_id" class="form-select form-select-sm" required>
                                    <option value="">— выбрать —</option>
                                    <?php foreach ($teachers as $t): ?>
                                    <option value="<?php echo $t['id']; ?>">
                                        <?php echo htmlspecialchars($t['name']); ?>
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-sm-5">
                                <label class="form-label small fw-semibold">Курс</label>
                                <select name="a_course_id" class="form-select form-select-sm" required>
                                    <option value="">— выбрать —</option>
                                    <?php foreach ($courses as $c): ?>
                                    <option value="<?php echo $c['id']; ?>">
                                        <?php echo htmlspecialchars($c['title_ru']); ?>
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-sm-2">
                                <button type="submit" class="btn btn-warning btn-sm w-100 fw-bold">
                                    Назначить
                                </button>
                            </div>
                        </div>
                    </form>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Правая колонка: форма добавления учителя -->
        <div class="col-lg-5">
            <div class="card shadow-sm border-0 sticky-top" style="top:20px">
                <div class="card-header bg-success text-white fw-bold">
                    ➕ Добавить преподавателя
                </div>
                <div class="card-body">
                    <form action="admin.php" method="POST">
                        <input type="hidden" name="action" value="add_teacher">

                        <div class="mb-3">
                            <label class="form-label small fw-semibold">ФИО / Имя *</label>
                            <input type="text" name="t_name" class="form-control form-control-sm"
                                   placeholder="Анна Иванова" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label small fw-semibold">Биография (RU)</label>
                            <textarea name="t_bio_ru" class="form-control form-control-sm" rows="2"
                                      placeholder="Сертифицированный преподаватель..."></textarea>
                        </div>
                        <div class="mb-3">
                            <label class="form-label small fw-semibold">Biograafia (ET)</label>
                            <textarea name="t_bio_et" class="form-control form-control-sm" rows="2"
                                      placeholder="Sertifitseeritud õpetaja..."></textarea>
                        </div>
                        <div class="mb-4">
                            <label class="form-label small fw-semibold">URL фото (необязательно)</label>
                            <input type="url" name="t_photo" class="form-control form-control-sm"
                                   placeholder="https://example.com/photo.jpg">
                            <div class="form-text">Оставьте пустым — будет автоматический аватар с инициалами.</div>
                        </div>
                        <button type="submit" class="btn btn-success w-100 fw-bold">
                            ➕ Добавить преподавателя
                        </button>
                    </form>
                </div>
            </div>
        </div>

    </div><!-- /row -->
    <?php endif; // end tabs ?>

    <?php endif; // end is_admin ?>

    </div><!-- /container -->

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>

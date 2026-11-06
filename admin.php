<?php
session_start();
require_once 'db.php';




$pdo->exec("CREATE TABLE IF NOT EXISTS admins (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL
)");





$stmt = $pdo->query("SELECT COUNT(*) FROM admins");
if ($stmt->fetchColumn() == 0) {
    $hash = password_hash('admin123', PASSWORD_DEFAULT);
    $pdo->exec("INSERT INTO admins (username, password) VALUES ('admin', '$hash')");
}

if (isset($_GET['logout'])) {
    session_destroy();
    header("Location: admin.php");
    exit;
}

if (isset($_GET['delete_id']) && isset($_SESSION['admin_logged_in'])) {
    $del_id = (int)$_GET['delete_id'];
    $pdo->prepare("DELETE FROM registrations WHERE id = ?")->execute([$del_id]);
    header("Location: admin.php");
    exit;
}

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




$registrations = [];
if (isset($_SESSION['admin_logged_in'])) {



$registrations = $pdo->query("
        SELECT r.*, c.title_ru, c.title_et 
        FROM registrations r 
        LEFT JOIN courses c ON r.course_id = c.id 
        ORDER BY r.created_at DESC
    ")->fetchAll();
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
</head>
<body class="bg-light">

    <!-- Навигация админки -->
    <nav class="navbar navbar-expand-lg navbar-dark bg-danger">
        <div class="container">
            <a class="navbar-brand fw-bold" href="admin.php">Админ-панель</a>
            <div class="collapse navbar-collapse">
                <ul class="navbar-nav ms-auto">
                    <li class="nav-item"><a class="nav-link" href="index.php" target="_blank">Перейти на сайт</a></li>
                    <?php if (isset($_SESSION['admin_logged_in'])): ?>
                        <li class="nav-item"><a class="nav-link btn btn-outline-light ms-3" href="admin.php?logout=1">Выйти</a></li>
                    <?php endif; ?>
                </ul>
            </div>
        </div>
    </nav>

    <div class="container my-5">
        
        <?php if (!isset($_SESSION['admin_logged_in'])): ?>






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
                            <p class="text-muted text-center mt-3 small">Вы админ или сталкер ?</p>
                        </div>
                    </div>
                </div>
            </div>

        <?php else: ?>
            <!-- ДАШБОРД -->
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h2>Управление заявками</h2>
                <span class="badge bg-success fs-6">Всего заявок: <?php echo count($registrations); ?></span>
            </div>

            <div class="card shadow-sm border-0">
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover mb-0">
                            <thead class="table-dark">
                                <tr>
                                    <th>ID</th>
                                    <th>Дата</th>
                                    <th>Имя студента</th>
                                    <th>Email</th>
                                    <th>Выбранный курс</th>
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
                                            <td><a href="mailto:<?php echo htmlspecialchars($reg['student_email']); ?>"><?php echo htmlspecialchars($reg['student_email']); ?></a></td>
                                            <td><?php echo htmlspecialchars($reg['title_ru'] ?? 'Курс удален'); ?></td>
                                            <td class="text-end">
                                                <a href="admin.php?delete_id=<?php echo $reg['id']; ?>" class="btn btn-sm btn-outline-danger" onclick="return confirm('Точно удалить заявку?');">Удалить</a>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="6" class="text-center py-4 text-muted">Пока нет ни одной заявки.</td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        <?php endif; ?>

    </div>


    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
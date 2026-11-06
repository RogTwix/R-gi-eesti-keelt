<?php
session_start();
require_once 'db.php';
$current_lang = $_SESSION['lang'] ?? 'ru';
if ($current_lang == 'et') { include 'lang/et.php'; } else { include 'lang/ru.php'; }

$status = "";
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name']);
    $email = trim($_POST['email']);
    
    if (!empty($name) && !empty($email)) {
        try {
            $stmt = $pdo->prepare("INSERT INTO registrations (student_name, student_email, course_id) VALUES (?, ?, ?)");
            $stmt->execute([$name, $email, 1]);
            $status = "<div class='alert alert-success mt-3'>Vorm saadetud! / Форма отправлена!</div>";
        } catch (PDOException $e) {
            $status = "<div class='alert alert-danger mt-3'>Viga / Ошибка записи в БД</div>";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="<?php echo $current_lang; ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $lang['contact']; ?> - EestiKool</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="css/style.css">
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-dark bg-dark">
        <div class="container">
            <a class="navbar-brand" href="index.php">🇪🇪 EestiKool</a>
            <div class="collapse navbar-collapse">
                <ul class="navbar-nav ms-auto">

                
                    <li class="nav-item"><a class="nav-link" href="index.php"><?php echo $lang['home']; ?></a></li>
                    <li class="nav-item"><a class="nav-link" href="courses.php"><?php echo $lang['courses']; ?></a></li>
                    <li class="nav-item"><a class="nav-link active" href="contact.php"><?php echo $lang['contact']; ?></a></li>
                    <li class="nav-item ms-3">


                    
                        <a href="contact.php?lang=ru" class="btn btn-sm btn-outline-light">RU</a>
                        <a href="contact.php?lang=et" class="btn btn-sm btn-outline-light">ET</a>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <div class="container my-5">
        <h2 class="text-center mb-4"><?php echo $lang['contact']; ?></h2>
        <div class="row justify-content-center">
            <div class="col-md-6">
                <form action="contact.php" method="POST" class="shadow p-4 rounded bg-light" onsubmit="this.querySelector('button').disabled = true;">
                    <div class="mb-3">
                        <label class="form-label">Nimi / Имя *</label>
                        <input type="text" name="name" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Email *</label>
                        <input type="email" name="email" class="form-control" required>
                    </div>
                    <button type="submit" class="btn btn-primary w-100">Saada / Отправить</button>
                    <?php echo $status; ?>
                </form>
            </div>
        </div>
    </div>
</body>
</html>
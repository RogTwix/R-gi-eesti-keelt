<?php
session_start();
$current_lang =$_SESSION['lang'] ?? 'ru';
if ($current_lang == 'et') { include 'lang/et.php'; } else { include 'lang/ru.php'; }
require_once 'db.php';

try {
    $stmt =$pdo->query("SELECT * FROM courses");
    $courses =$stmt->fetchAll();
} catch (PDOException $e) {$courses = []; }
?>
<!DOCTYPE html>
<html lang="<?php echo $current_lang; ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $lang['courses']; ?> - EestiKool</title>
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
                    <li class="nav-item"><a class="nav-link active" href="courses.php"><?php echo $lang['courses']; ?></a></li>
                    <li class="nav-item"><a class="nav-link" href="contact.php"><?php echo $lang['contact']; ?></a></li>
                    <li class="nav-item ms-3">
                        <a href="courses.php?lang=ru" class="btn btn-sm btn-outline-light">RU</a>
                        <a href="courses.php?lang=et" class="btn btn-sm btn-outline-light">ET</a>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <div class="container my-5">
        <h2 class="text-center mb-4 text-primary"><?php echo $lang['courses']; ?></h2>
        <div class="row">
            <?php foreach ($courses as$c): ?>
                <div class="col-md-4 mb-4">
                    <div class="card h-100 shadow-sm quiz-box">
                        <div class="card-body d-flex flex-column">
                            <span class="badge bg-secondary align-self-start mb-2"><?php echo htmlspecialchars($c['level']); ?></span>
                            <h4 class="card-title"><?php echo htmlspecialchars($current_lang == 'et' ? $c['title_et'] :$c['title_ru']); ?></h4>
                            <p class="card-text flex-grow-1"><?php echo htmlspecialchars($current_lang == 'et' ? $c['description_et'] :$c['description_ru']); ?></p>
                            <p class="fw-bold text-success fs-5"><?php echo htmlspecialchars($c['price']); ?> €</p>
                            <a href="contact.php" class="btn btn-outline-primary mt-auto"><?php echo $lang['book_course']; ?></a>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</body>
</html>
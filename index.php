<?php
session_start();

if (isset($_GET['lang'])) {
    $_SESSION['lang'] = $_GET['lang'];
}
$current_lang = $_SESSION['lang'] ?? 'ru';

if ($current_lang == 'et') {
    include 'lang/et.php';
} else {
    include 'lang/ru.php';
}

require_once 'db.php';
?>
<!DOCTYPE html>
<html lang="<?php echo $current_lang; ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>EestiKool - <?php echo $lang['home']; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="css/style.css">
</head>
<body>

    <nav class="navbar navbar-expand-lg navbar-dark bg-dark">
        <div class="container">
            <a class="navbar-brand" href="index.php">🇪🇪 EestiKool</a>
            <div class="collapse navbar-collapse">
                <ul class="navbar-nav ms-auto">
                    <li class="nav-item"><a class="nav-link active" href="index.php"><?php echo $lang['home']; ?></a></li>
                    <li class="nav-item"><a class="nav-link" href="courses.php"><?php echo $lang['courses']; ?></a></li>
                    <li class="nav-item"><a class="nav-link" href="contact.php"><?php echo $lang['contact']; ?></a></li>
                    <li class="nav-item ms-3">
                        <a href="index.php?lang=ru" class="btn btn-sm btn-outline-light">RU</a>
                        <a href="index.php?lang=et" class="btn btn-sm btn-outline-light">ET</a>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    
    <header class="bg-primary text-white text-center py-5">
        <div class="container">
            <h1 class="display-4 fw-bold"><?php echo $lang['welcome']; ?></h1>
            <a href="courses.php" class="btn btn-light btn-lg mt-3"><?php echo $lang['book_course']; ?></a>
        </div>
    </header>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
</body>
</html>
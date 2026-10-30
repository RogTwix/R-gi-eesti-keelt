<?php
session_start();
require_once 'db.php';

$current_lang = $_SESSION['lang'] ?? 'ru';
if ($current_lang == 'et') {
    include 'lang/et.php';
} else {
    include 'lang/ru.php';
}

$status_msg = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name']);
    $email = trim($_POST['email']);
    $course_id = $_POST['course_id'];
    $message = trim($_POST['message']);

    if (!empty($name) && !empty($email) && !empty($course_id)) {
        $stmt = $pdo->prepare("INSERT INTO registrations (course_id, student_name, student_email, message) VALUES (?, ?, ?, ?)");
        if ($stmt->execute([$course_id, $name, $email, $message])) {
            $status_msg = "<div class='alert alert-success mt-3'>" . $lang['success'] . "</div>";
        }
    } else {
        $status_msg = "<div class='alert alert-danger mt-3'>" . $lang['error'] . "</div>";
    }
}

$courses_stmt = $pdo->query("SELECT id, title_ru, title_et FROM courses");
$courses_list = $courses_stmt->fetchAll();
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
        <h2 class="text-center mb-4"><?php echo $lang['book_course']; ?></h2>
        
        <div class="row justify-content-center">
            <div class="col-md-6">
                <!-- Форма записи -->
                <form action="contact.php" method="POST" id="contactForm" class="shadow p-4 rounded bg-light">
                    <div class="mb-3">
                        <label class="form-label"><?php echo $lang['name']; ?> *</label>
                        <input type="text" name="name" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label"><?php echo $lang['email']; ?> *</label>
                        <input type="email" name="email" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label"><?php echo $lang['choose_course']; ?> *</label>
                        <select name="course_id" class="form-select" required>
                            <option value="">...</option>
                            <?php foreach ($courses_list as $c): ?>
                                <option value="<?php echo $c['id']; ?>">
                                    <?php echo htmlspecialchars($current_lang == 'et' ? $c['title_et'] : $c['title_ru']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label"><?php echo $lang['message']; ?></label>
                        <textarea name="message" class="form-control" rows="3"></textarea>
                    </div>
                    <button type="submit" class="btn btn-primary w-100"><?php echo $lang['send']; ?></button>
                    <?php echo $status_msg; ?>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="js/main.js"></script>
</body>
</html>
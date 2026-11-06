<?php
session_start();
$current_lang = $_SESSION['lang'] ?? 'ru';
if ($current_lang == 'et') { include 'lang/et.php'; } else { include 'lang/ru.php'; }
require_once 'db.php';

// ── Загружаем курсы ──────────────────────────────────────────
$courses = [];
try {
    $courses = $pdo->query("SELECT * FROM courses ORDER BY id")->fetchAll();
} catch (PDOException $e) { $courses = []; }

// ── Загружаем учителей для каждого курса ────────────────────
$course_teachers = [];
try {
    $t_rows = $pdo->query("
        SELECT ct.course_id, t.id, t.name, t.photo_url, t.bio_ru, t.bio_et
        FROM course_teacher ct
        JOIN teachers t ON ct.teacher_id = t.id
        ORDER BY t.name
    ")->fetchAll();
    foreach ($t_rows as $tr) {
        $course_teachers[(int)$tr['course_id']][] = $tr;
    }
} catch (PDOException $e) { $course_teachers = []; }
?>
<!DOCTYPE html>
<html lang="<?php echo $current_lang; ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $lang['courses']; ?> - EestiKool</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="css/style.css">
    <style>
        .teacher-avatar {
            width: 36px; height: 36px;
            border-radius: 50%;
            object-fit: cover;
            border: 2px solid #fff;
            box-shadow: 0 1px 4px rgba(0,0,0,.2);
        }
        .teacher-initials {
            width: 36px; height: 36px;
            border-radius: 50%;
            font-size: 13px; font-weight: 700;
            display: flex; align-items: center; justify-content: center;
            color: #fff;
            border: 2px solid #fff;
            box-shadow: 0 1px 4px rgba(0,0,0,.2);
            flex-shrink: 0;
        }
        .teacher-chip {
            display: flex; align-items: center; gap: 7px;
            background: #f0f4ff;
            border-radius: 20px;
            padding: 4px 10px 4px 4px;
            font-size: .82rem;
        }
        .teachers-section { border-top: 1px solid #e9ecef; padding-top: 10px; margin-top: 10px; }
    </style>
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
                        <a href="courses.php?lang=ru" class="btn btn-sm btn-outline-light <?php echo ($current_lang=='ru')?'active':''; ?>">RU</a>
                        <a href="courses.php?lang=et" class="btn btn-sm btn-outline-light <?php echo ($current_lang=='et')?'active':''; ?>">ET</a>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <div class="container my-5">
        <h2 class="text-center mb-4 text-primary"><?php echo $lang['courses']; ?></h2>
        <div class="row">
            <?php foreach ($courses as $c): ?>
                <?php
                    $teachers = $course_teachers[(int)$c['id']] ?? [];
                    // Цвета аватаров (по хэшу имени)
                    $colors = ['#4361ee','#3a0ca3','#7209b7','#f72585','#4cc9f0','#06d6a0','#ef476f','#ffd166'];
                ?>
                <div class="col-md-4 mb-4">
                    <div class="card h-100 shadow-sm">
                        <div class="card-body d-flex flex-column">
                            <span class="badge bg-secondary align-self-start mb-2">
                                <?php echo htmlspecialchars($c['level']); ?>
                            </span>
                            <h4 class="card-title">
                                <?php echo htmlspecialchars($current_lang == 'et' ? $c['title_et'] : $c['title_ru']); ?>
                            </h4>
                            <p class="card-text flex-grow-1">
                                <?php echo htmlspecialchars($current_lang == 'et' ? $c['description_et'] : $c['description_ru']); ?>
                            </p>
                            <p class="fw-bold text-success fs-5"><?php echo htmlspecialchars($c['price']); ?> €</p>

                            <!-- ── Блок преподавателей ── -->
                            <div class="teachers-section">
                                <p class="text-muted small mb-2">
                                    👨‍🏫 <?php echo $lang['teachers_on_course']; ?>:
                                </p>
                                <?php if (!empty($teachers)): ?>
                                    <div class="d-flex flex-wrap gap-2">
                                        <?php foreach ($teachers as $t):
                                            $initials = mb_strtoupper(mb_substr($t['name'], 0, 1, 'UTF-8'), 'UTF-8');
                                            $color = $colors[crc32($t['name']) % count($colors)];
                                            $bio = $current_lang == 'et' ? $t['bio_et'] : $t['bio_ru'];
                                        ?>
                                        <div class="teacher-chip" title="<?php echo htmlspecialchars($bio); ?>">
                                            <?php if (!empty($t['photo_url'])): ?>
                                                <img src="<?php echo htmlspecialchars($t['photo_url']); ?>"
                                                     alt="<?php echo htmlspecialchars($t['name']); ?>"
                                                     class="teacher-avatar">
                                            <?php else: ?>
                                                <div class="teacher-initials" style="background:<?php echo $color; ?>">
                                                    <?php echo $initials; ?>
                                                </div>
                                            <?php endif; ?>
                                            <span><?php echo htmlspecialchars($t['name']); ?></span>
                                        </div>
                                        <?php endforeach; ?>
                                    </div>
                                <?php else: ?>
                                    <p class="text-muted small fst-italic"><?php echo $lang['no_teachers']; ?></p>
                                <?php endif; ?>
                            </div>
                            <!-- ── /Блок преподавателей ── -->

                            <a href="contact.php?course_id=<?php echo (int)$c['id']; ?>"
                               class="btn btn-outline-primary mt-3">
                               <?php echo $lang['book_course']; ?>
                            </a>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>

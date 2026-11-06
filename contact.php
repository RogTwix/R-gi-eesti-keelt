<?php
session_start();
require_once 'db.php';
$current_lang = $_SESSION['lang'] ?? 'ru';
if ($current_lang == 'et') { include 'lang/et.php'; } else { include 'lang/ru.php'; }

// ── Предвыбранный курс из URL ────────────────────────────────
$selected_course_id = (int)($_GET['course_id'] ?? 0);

// ── Загружаем список курсов ──────────────────────────────────
$courses_list = [];
try {
    $courses_list = $pdo->query("SELECT * FROM courses ORDER BY id")->fetchAll();
} catch (PDOException $e) { $courses_list = []; }

// ── Загружаем учителей, сгруппированных по курсам (для JS) ──
$teachers_by_course = [];
try {
    $t_rows = $pdo->query("
        SELECT ct.course_id, t.id, t.name, t.photo_url
        FROM course_teacher ct
        JOIN teachers t ON ct.teacher_id = t.id
        ORDER BY t.name
    ")->fetchAll();
    foreach ($t_rows as $tr) {
        $teachers_by_course[(int)$tr['course_id']][] = [
            'id'    => (int)$tr['id'],
            'name'  => $tr['name'],
            'photo' => $tr['photo_url'] ?? '',
        ];
    }
} catch (PDOException $e) { $teachers_by_course = []; }

// ── Обработка отправки формы ─────────────────────────────────
$status = "";
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name       = trim($_POST['name'] ?? '');
    $email      = trim($_POST['email'] ?? '');
    $course_id  = (int)($_POST['course_id'] ?? 0) ?: null;
    $teacher_id = (int)($_POST['teacher_id'] ?? 0) ?: null;

    if (!empty($name) && !empty($email)) {
        try {
            $stmt = $pdo->prepare(
                "INSERT INTO registrations (student_name, student_email, course_id, teacher_id) VALUES (?, ?, ?, ?)"
            );
            $stmt->execute([$name, $email, $course_id, $teacher_id]);
            $status = "<div class='alert alert-success mt-3'>✅ " . $lang['success'] . "</div>";
        } catch (PDOException $e) {
            $status = "<div class='alert alert-danger mt-3'>❌ " . $lang['error'] . "</div>";
        }
    } else {
        $status = "<div class='alert alert-warning mt-3'>⚠️ " . $lang['error'] . "</div>";
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
    <style>
        .teacher-opt-avatar {
            width: 28px; height: 28px; border-radius: 50%;
            object-fit: cover; vertical-align: middle; margin-right: 6px;
        }
        .teacher-card {
            border: 2px solid transparent;
            border-radius: 10px;
            padding: 10px;
            cursor: pointer;
            transition: border-color .2s, background .2s;
        }
        .teacher-card:hover { border-color: #0d6efd; background: #f0f4ff; }
        .teacher-card.selected { border-color: #0d6efd; background: #e8efff; }
        .teacher-initials-sm {
            width: 42px; height: 42px; border-radius: 50%;
            font-size: 16px; font-weight: 700;
            display: flex; align-items: center; justify-content: center;
            color: #fff; flex-shrink: 0;
        }
    </style>
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
                        <a href="contact.php?lang=ru<?php echo $selected_course_id ? '&course_id='.$selected_course_id : ''; ?>"
                           class="btn btn-sm btn-outline-light <?php echo ($current_lang=='ru')?'active':''; ?>">RU</a>
                        <a href="contact.php?lang=et<?php echo $selected_course_id ? '&course_id='.$selected_course_id : ''; ?>"
                           class="btn btn-sm btn-outline-light <?php echo ($current_lang=='et')?'active':''; ?>">ET</a>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <div class="container my-5">
        <h2 class="text-center mb-4"><?php echo $lang['contact']; ?></h2>
        <div class="row justify-content-center">
            <div class="col-md-7">
                <form id="contactForm" action="contact.php" method="POST"
                      class="shadow p-4 rounded bg-light">

                    <!-- Скрытое поле course_id — обновляется JS-ом -->
                    <input type="hidden" name="course_id" id="hidden_course_id"
                           value="<?php echo $selected_course_id; ?>">
                    <!-- Скрытое поле teacher_id — обновляется при клике на карточку -->
                    <input type="hidden" name="teacher_id" id="hidden_teacher_id" value="">

                    <!-- ── Имя ── -->
                    <div class="mb-3">
                        <label class="form-label fw-semibold"><?php echo $lang['name']; ?> *</label>
                        <input type="text" name="name" class="form-control" required>
                    </div>

                    <!-- ── Email ── -->
                    <div class="mb-3">
                        <label class="form-label fw-semibold"><?php echo $lang['email']; ?> *</label>
                        <input type="email" name="email" class="form-control" required>
                    </div>

                    <!-- ── Выбор курса ── -->
                    <div class="mb-3">
                        <label class="form-label fw-semibold"><?php echo $lang['choose_course']; ?></label>
                        <select id="course_select" class="form-select">
                            <option value="">— <?php echo $lang['choose_course']; ?> —</option>
                            <?php foreach ($courses_list as $c):
                                $title = $current_lang == 'et' ? $c['title_et'] : $c['title_ru'];
                                $sel = ($selected_course_id == (int)$c['id']) ? 'selected' : '';
                            ?>
                            <option value="<?php echo (int)$c['id']; ?>" <?php echo $sel; ?>>
                                <?php echo htmlspecialchars($title); ?>
                                (<?php echo htmlspecialchars($c['level']); ?>)
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <!-- ── Выбор преподавателя (динамически) ── -->
                    <div class="mb-4" id="teacher_section">
                        <label class="form-label fw-semibold">
                            👨‍🏫 <?php echo $lang['choose_teacher']; ?>
                        </label>
                        <div id="teacher_cards" class="d-flex flex-wrap gap-2 mt-1">
                            <!-- заполняется JavaScript-ом -->
                            <p class="text-muted small fst-italic" id="teacher_placeholder">
                                ← <?php echo $current_lang == 'et'
                                    ? 'Vali esmalt kursus'
                                    : 'Сначала выберите курс'; ?>
                            </p>
                        </div>
                    </div>

                    <!-- ── Кнопка ── -->
                    <button type="submit" class="btn btn-primary w-100 fw-bold">
                        <?php echo $lang['send']; ?>
                    </button>

                    <?php echo $status; ?>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="js/main.js"></script>
    <script>
    // ── Данные учителей по курсам из PHP ──
    const teachersByCourse = <?php echo json_encode($teachers_by_course, JSON_UNESCAPED_UNICODE); ?>;
    const avatarColors = ['#4361ee','#3a0ca3','#7209b7','#f72585','#4cc9f0','#06d6a0','#ef476f','#ffd166'];
    const noTeachersText = <?php echo json_encode($lang['no_teachers']); ?>;
    const anyTeacherText = <?php echo json_encode($lang['any_teacher']); ?>;

    // Получаем хэш-цвет по имени
    function strColor(str) {
        let h = 0;
        for (let i = 0; i < str.length; i++) h = str.charCodeAt(i) + ((h << 5) - h);
        return avatarColors[Math.abs(h) % avatarColors.length];
    }

    function getInitials(name) {
        return name.trim().split(/\s+/).map(w => w[0]).join('').substring(0,2).toUpperCase();
    }

    function renderTeachers(courseId) {
        const container = document.getElementById('teacher_cards');
        const placeholder = document.getElementById('teacher_placeholder');
        document.getElementById('hidden_teacher_id').value = '';

        const teachers = teachersByCourse[courseId] || [];
        container.innerHTML = '';

        if (!courseId) {
            container.innerHTML = `<p class="text-muted small fst-italic" id="teacher_placeholder">← <?php echo $current_lang == 'et' ? 'Vali esmalt kursus' : 'Сначала выберите курс'; ?></p>`;
            return;
        }

        if (teachers.length === 0) {
            container.innerHTML = `<p class="text-muted small fst-italic">${noTeachersText}</p>`;
            return;
        }

        // Карточка "Любой"
        const anyCard = document.createElement('div');
        anyCard.className = 'teacher-card d-flex align-items-center gap-2';
        anyCard.dataset.id = '';
        anyCard.innerHTML = `<div class="teacher-initials-sm" style="background:#adb5bd;font-size:18px;">🎲</div>
                             <div><strong>${anyTeacherText}</strong></div>`;
        anyCard.addEventListener('click', () => selectTeacher(anyCard, ''));
        container.appendChild(anyCard);

        teachers.forEach(t => {
            const card = document.createElement('div');
            card.className = 'teacher-card d-flex align-items-center gap-2';
            card.dataset.id = t.id;

            let avatarHtml;
            if (t.photo) {
                avatarHtml = `<img src="${t.photo}" alt="${t.name}" class="teacher-initials-sm" style="object-fit:cover;">`;
            } else {
                const color = strColor(t.name);
                const initials = getInitials(t.name);
                avatarHtml = `<div class="teacher-initials-sm" style="background:${color}">${initials}</div>`;
            }

            card.innerHTML = `${avatarHtml}<div><strong>${t.name}</strong></div>`;
            card.addEventListener('click', () => selectTeacher(card, t.id));
            container.appendChild(card);
        });
    }

    function selectTeacher(card, teacherId) {
        document.querySelectorAll('.teacher-card').forEach(c => c.classList.remove('selected'));
        card.classList.add('selected');
        document.getElementById('hidden_teacher_id').value = teacherId;
    }

    // ── Слушатель выбора курса ──
    document.getElementById('course_select').addEventListener('change', function() {
        const cid = parseInt(this.value) || 0;
        document.getElementById('hidden_course_id').value = cid || '';
        renderTeachers(cid);
    });

    // ── Инициализация при загрузке страницы ──
    const initCourseId = <?php echo $selected_course_id ?: 0; ?>;
    if (initCourseId) renderTeachers(initCourseId);
    </script>
</body>
</html>

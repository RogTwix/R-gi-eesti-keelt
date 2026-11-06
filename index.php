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





$quiz = [
    'ru' => [
        'title' => 'Мини-тест: Узнай свой уровень эстонского!',
        'q1' => '1. Как правильно переводится слово "Здравствуйте"?',
        'q2' => '2. Что означает слово "Aitäh"?',
        'btn' => 'Проверить результат',
        'res_a1' => 'Вам отлично подойдет наш базовый курс для начинающих (A1)!',
        'res_a2' => 'У вас уже есть база! Ждем вас на разговорном курсе (A2/B1).'
    ],
    'et' => [
        'title' => 'Minitest: Uuri oma keeletaset!',
        'q1' => '1. Kuidas tõlkida sõna "Здравствуйте"?',
        'q2' => '2. Mida tähendab sõna "Aitäh"?',
        'btn' => 'Kontrolli tulemust',
        'res_a1' => 'Sulle sobib suurepäraselt algajate baaskursus (A1)!',
        'res_a2' => 'Sul on head algteadmised! Ootame sind suhtluskursusele (A2/B1).'
    ]
];



$q = $quiz[$current_lang];
?>
<!DOCTYPE html>
<html lang="<?php echo $current_lang; ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>EestiKool - <?php echo $lang['home']; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="css/style.css">
    <style>



        .quiz-box { transition: transform 0.3s ease, box-shadow 0.3s ease; }
        .quiz-box:hover { transform: translateY(-5px); box-shadow: 0 10px 20px rgba(0,0,0,0.1); }
    </style>
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
                        <a href="index.php?lang=ru" class="btn btn-sm btn-outline-light <?php echo ($current_lang == 'ru') ? 'active' : ''; ?>">RU</a>
                        <a href="index.php?lang=et" class="btn btn-sm btn-outline-light <?php echo ($current_lang == 'et') ? 'active' : ''; ?>">ET</a>
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





    <main class="container my-5">
        <div class="row justify-content-center">
            <div class="col-md-8">
                <div class="card quiz-box p-4 border-0 bg-light">
                    <h3 class="text-center mb-4 text-primary">gi <?php echo $q['title']; ?></h3>
                    
                    <div class="mb-4">
                        <p class="fw-bold"><?php echo $q['q1']; ?></p>
                        <div class="form-check">
                            <input class="form-check-input" type="radio" name="q1" id="q1_a" value="1">
                            <label class="form-check-label" for="q1_a">Tere</label>
                        </div>
                        <div class="form-check">
                            <input class="form-check-input" type="radio" name="q1" id="q1_b" value="0">
                            <label class="form-check-label" for="q1_b">Head aega</label>
                        </div>
                    </div>

                    <div class="mb-4">
                        <p class="fw-bold"><?php echo $q['q2']; ?></p>
                        <div class="form-check">
                            <input class="form-check-input" type="radio" name="q2" id="q2_a" value="0">
                            <label class="form-check-label" for="q2_a">Пожалуйста / Palun</label>
                        </div>
                        <div class="form-check">
                            <input class="form-check-input" type="radio" name="q2" id="q2_b" value="1">
                            <label class="form-check-label" for="q2_b">Спасибо / Aitäh</label>
                        </div>
                    </div>

                    <button type="button" class="btn btn-success w-100 fw-bold" onclick="checkQuiz()">
                        <?php echo $q['btn']; ?>
                    </button>

                    <!-- Блок для вывода результата из JavaScript -->
                    <div id="quiz-result" class="mt-3" style="display: none;"></div>
                </div>
            </div>
        </div>
    </main>


    

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
        function checkQuiz() {
            let score = 0;
            
            // Проверяем первый вопрос (правильный ответ A)
            if(document.getElementById('q1_a').checked) score++;
            
            // Проверяем второй вопрос (правильный ответ B)
            if(document.getElementById('q2_b').checked) score++;
            
            let resultDiv = document.getElementById('quiz-result');
            resultDiv.style.display = 'block'; 
            // Выводим результат в зависимости от баллов
            if (score === 2) {
                resultDiv.innerHTML = "🎉 <strong><?php echo $q['res_a2']; ?></strong>";
                resultDiv.className = "alert alert-success mt-3";
            } else {
                resultDiv.innerHTML = "💡 <strong><?php echo $q['res_a1']; ?></strong>";
                resultDiv.className = "alert alert-info mt-3";
            }
        }
    </script>
</body>
</html>
<?php
    session_start();

    // define APP_NAME
    const APP_NAME = 'Quizflow';

    // import config
    include_once __DIR__ . '/includes/core/config.php';

    // get questions
    $data = json_decode(file_get_contents(QUIZFLOW_DATA), true);

    // Schwierigkeit setzen, falls übergeben
    if (isset($_GET['difficulty'])) {
        $_SESSION['difficulty'] = $_GET['difficulty'];
    }
    // Start-Flag setzen, falls übergeben
    if (isset($_GET['start'])) {
        $_SESSION['start'] = true;
    }

    // Schwierigkeit aus Session holen
    $difficulty = $_SESSION['difficulty'] ?? null;
    $startQuiz = $_SESSION['start'] ?? false;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Quizflow</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.7.1/jquery.min.js" integrity="sha512-v2CJ7UaYy4JwqLDIrZUI/4hqeoQieOmAZNXBeQyjo21dadnwR+8ZaIJVT8EE2iyI61OV8e6M8PP2/4hpQINQ/g==" crossorigin="anonymous" referrerpolicy="no-referrer"></script>
    <script src="quiz.js"></script>
</head>
<body class="flex flex-col justify-center bg-gray-200">
<?php

// 1. Intro anzeigen, falls noch keine Schwierigkeit gewählt wurde
if (!$difficulty && !$startQuiz && !isset($_GET['submit'])) {
    echo '<div class="container mx-auto w-[95%] md:w-[60%] m-12"><div class="md:w-full bg-white shadow-lg rounded-2xl p-8 flex flex-col gap-4">';
    foreach ($data['intro_1'] as $introItem) {
        echo $introItem;
    }
    // Schwierigkeitsauswahl-Formular
    echo <<<HTML
        <form method="get" class="mt-8 flex flex-row justify-center gap-4">
            <button name="difficulty" value="1" class="w-24 h-24 flex items-center justify-center bg-green-500 hover:bg-green-700 text-white font-bold rounded-xl text-lg shadow-lg transition-all">Einfach</button>
            <button name="difficulty" value="2" class="w-24 h-24 flex items-center justify-center bg-yellow-500 hover:bg-yellow-700 text-white font-bold rounded-xl text-lg shadow-lg transition-all">Mittel</button>
            <button name="difficulty" value="3" class="w-24 h-24 flex items-center justify-center bg-red-500 hover:bg-red-700 text-white font-bold rounded-xl text-lg shadow-lg transition-all">Schwer</button>
        </form>
    HTML;
    echo '</div></div>';
    exit;
}

// 2. Nach Auswahl der Schwierigkeit: Start-Button anzeigen
if ($difficulty && !$startQuiz && !isset($_GET['submit'])) {
    echo '<div class="container mx-auto w-[95%] md:w-[60%] m-12"><div class="md:w-full bg-white shadow-lg rounded-2xl p-8 flex flex-col gap-4">';
    $difficultyText = match ($difficulty) {
        '1' => 'Einfach',
        '2' => 'Mittel',
        '3' => 'Schwer',
        default => 'Unbekannt',
    };
    echo "<p class='pb-8 font-bold text-2xl leading-normal'>Schwierigkeit gewählt: <span class='capitalize'>{$difficultyText}</span></p>";
    foreach ($data['intro_2'] as $introItem) {
        echo $introItem;
    }
    echo "<form method='get'><button name='start' value='1' class='bg-blue-500 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded-full'>Quiz starten</button></form>";
    echo '</div></div>';
    exit;
}

// 3. Quiz starten
if ($difficulty && $startQuiz && !isset($_GET['submit'])) {
    $_SESSION['startTime'] = microtime(true);
    $questions = $data[$difficulty] ?? [];
    echo <<<HTML
        <div class="progress-bar bg-green-500 h-2 w-0 fixed top-0 left-0"></div>
        <div class="container mx-auto w-[95%] md:w-[60%] m-12">
            <form id="quizForm" class="md:space-y-4 md:flex md:flex-col md:gap-6" method="POST" action="?submit">
    HTML;
    $questionNumber = 1;
    foreach ($questions as $question) {
        echo '<fieldset class="md:w-full bg-white shadow-lg rounded-2xl p-8 flex flex-col gap-4">';
        echo '<p class="pb-8 font-bold text-2xl leading-normal">' . $questionNumber . '. ' . $question['question'] . '</p>';
        $answerNumber = 1;
        foreach ($question['answers'] as $answer) {
            $optionId = 'option' . (($questionNumber - 1) * count($question['answers']) + $answerNumber);
            $isCorrect = ($answerNumber - 1) == $question['correctAnswer'] ? 'true' : 'false';
            echo '<input type="radio" name="q_' . $questionNumber . '" value="' . $answerNumber . '" id="' . $optionId . '" class="hidden" data-correct="' . $isCorrect . '">';
            echo '<label for="' . $optionId . '" class="option-label cursor-pointer py-2 px-4 border-2 rounded-3xl">' . $answer . '</label>';
            $answerNumber++;
        }
        echo '</fieldset>';
        $questionNumber++;
    }
    echo <<<HTML
                <fieldset>
                    <button type="submit" class="bg-blue-500 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded-full focus:outline-none focus:shadow-outline">Absenden</button>
                </fieldset>
            </form>          
        </div>
        <div class="fixed bottom-0 left-0 w-full flex justify-between p-4 rounded-t-2xl bg-white shadow-md md:hidden">
            <button class="bg-blue-500 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded-full focus:outline-none focus:shadow-outline" id="prev">Zurück</button>
            <button class="bg-blue-500 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded-full focus:outline-none focus:shadow-outline" id="next">Weiter</button>
        </div>
    HTML;
    exit;
}

// 4. Auswertung
if (isset($_GET['submit']) && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $difficulty = $_SESSION['difficulty'] ?? '1';
    $questions = $data[$difficulty] ?? [];

    // init params
    $param = [];

    // get time
    $_SESSION['submitTime'] = microtime(true);
    $param['time'] = $_SESSION['submitTime'] - $_SESSION['startTime'];
    $param['time'] = number_format($param['time'], 2);

    // create code
    file_exists(QUIZFLOW_CODE) ?: file_put_contents(QUIZFLOW_CODE, '000000');
    $param['code'] = str_pad((int)file_get_contents(QUIZFLOW_CODE) + 1, 6, '0', STR_PAD_LEFT);
    file_put_contents(QUIZFLOW_CODE, $param['code']);

    // get answers
    $json = file_get_contents(QUIZFLOW_DATA);
    $data = json_decode($json, true);

    // get answers
    $param['answer'] = [];
    foreach ($_POST as $inputName => $userAnswer) {
        // sanitize input name and userAnswer
        if (preg_match('/^q_/', $inputName)) {
            if (ctype_digit($userAnswer)) {
                // iterate over questions
                $questionNumber = 1;
                foreach ($questions as $question) {
                    // check if inputName is equal to question name
                    if ($inputName == 'q_' . $questionNumber) {
                        // check answer
                        $isCorrect = ((int)$userAnswer - 1) == $question['correctAnswer'] ? 'true' : 'false';
                
                        // Add the answer to the array
                        $param['answer'][] = [
                            'name' => $inputName,
                            'isCorrect' => $isCorrect
                        ];
                
                        break;
                    }
                    $questionNumber++;
                }
            }
        }
    }

    // insert answers
    $query = 'INSERT INTO answers (code, time, answer, difficulty) VALUES (:code, :time, :answer, :difficulty)';
    $params = [
        'code' => $param['code'],
        'time' => $param['time'],
        'answer' => json_encode($param['answer']),
        'difficulty' => $difficulty
    ];
    $dbAdapter->db_query($query, $params);

    echo '<div class="container mx-auto w-[95%] md:w-[60%] m-12"><div class="md:w-full bg-white shadow-lg rounded-2xl p-8 flex flex-col gap-4">';

    foreach ($data['outro'] as $outroItem) {
        echo $outroItem;
    }

    echo "
        <p class='pb-8 font-bold text-2xl leading-normal'>Ihr Code: {$param['code']}</p>
        <div class='flex justify-center'><a href='?start' class='bg-blue-500 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded-full focus:outline-none focus:shadow-outline'>Nochmal spielen</a></div>
    ";

    echo '</div></div>';
} else {
    echo '<div class="container mx-auto w-[95%] md:w-[60%] m-12"><div class="md:w-full bg-white shadow-lg rounded-2xl p-8 flex flex-col gap-4">';

    foreach ($data['intro'] as $introItem) {
        echo $introItem;
    }

    echo '</div></div>';
}

?>
</body>
</html>
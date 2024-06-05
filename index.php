<?php
    session_start();
    $data = json_decode(file_get_contents('includes/questions.json'), true);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Quizflow</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.7.1/jquery.min.js" integrity="sha512-v2CJ7UaYy4JwqLDIrZUI/4hqeoQieOmAZNXBeQyjo21dadnwR+8ZaIJVT8EE2iyI61OV8e6M8PP2/4hpQINQ/g==" crossorigin="anonymous" referrerpolicy="no-referrer"></script>
    <script src="includes/js/quiz.js"></script>
</head>
<body class="flex flex-col justify-center bg-gray-200">
<?php
    if (isset($_GET['start'])) {
        $_SESSION['startTime'] = microtime(true);
        echo <<<HTML
            <div class="progress-bar bg-green-500 h-2 w-0"></div>
            <div class="container mx-auto w-[95%] md:w-[60%] m-12">
                <form id="quizForm" class="md:space-y-4 md:flex md:flex-col md:gap-6" method="post" action="?submit">
        HTML;
            $questionNumber = 1;
            foreach ($data['questions'] as $question) {
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
    } elseif (isset($_GET['submit'])) {
        $_SESSION['submitTime'] = microtime(true);
        $time = $_SESSION['submitTime'] - $_SESSION['startTime'];
        $time = number_format($time, 2);

        echo '<div class="container mx-auto w-[95%] md:w-[60%] m-12"><div class="md:w-full bg-white shadow-lg rounded-2xl p-8 flex flex-col gap-4">';

        foreach ($data['outro'] as $outroItem) {
            echo $outroItem;
        }

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
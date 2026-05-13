<?php
session_start();

const APP_NAME = 'Quizflow';

include_once __DIR__ . '/includes/core/db_adapter.php';
include_once __DIR__ . '/includes/core/quiz_manager.php';

$dbAdapter = new \Quizflow\Core\DatabaseAdapter();
$dbAdapter->db_init();
$quizManager = new \Quizflow\Core\QuizManager();

$quizData = $quizManager->loadData();
$catalogs = $quizManager->getCatalogs($quizData);

if (isset($_GET['reset'])) {
    $_SESSION = [];
    session_destroy();
    header('Location: ./');
    exit;
}

if (isset($_GET['difficulty']) && isset($catalogs[$_GET['difficulty']])) {
    $_SESSION['difficulty'] = (string) $_GET['difficulty'];
    unset($_SESSION['start']);
}

$selectedDifficulty = $_SESSION['difficulty'] ?? null;
$selectedCatalog = $selectedDifficulty !== null ? ($catalogs[$selectedDifficulty] ?? null) : null;
$quizStarted = !empty($_SESSION['start']);
$errorMessage = null;
$result = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'start_quiz') {
        $selectedDifficulty = $_SESSION['difficulty'] ?? ($_POST['difficulty'] ?? null);
        $selectedCatalog = $selectedDifficulty !== null ? ($catalogs[$selectedDifficulty] ?? null) : null;

        if ($selectedCatalog === null) {
            header('Location: ./');
            exit;
        }

        $nameValidation = $quizManager->validatePlayerName($_POST['player_name'] ?? '');
        if (!$nameValidation['valid']) {
            $errorMessage = $nameValidation['error'];
        } else {
            $_SESSION['difficulty'] = (string) $selectedDifficulty;
            $_SESSION['player_name'] = $nameValidation['name'];
            $_SESSION['start'] = true;
            $_SESSION['startTime'] = microtime(true);
            header('Location: ./');
            exit;
        }
    }

    if ($action === 'submit_quiz') {
        $selectedDifficulty = $_SESSION['difficulty'] ?? null;
        $selectedCatalog = $selectedDifficulty !== null ? ($catalogs[$selectedDifficulty] ?? null) : null;
        $playerName = $_SESSION['player_name'] ?? '';
        $startTime = $_SESSION['startTime'] ?? null;

        if ($selectedCatalog === null || !$quizStarted || $startTime === null || $playerName === '') {
            header('Location: ./?reset=1');
            exit;
        }

        $questions = $quizManager->getQuestions($quizData, $selectedDifficulty);
        $summary = $quizManager->scoreAnswers($questions, $_POST, (float) $selectedCatalog['multiplier']);

        $submitTime = microtime(true);
        $duration = round($submitTime - $startTime, 2);

        if (!file_exists(QUIZFLOW_CODE)) {
            file_put_contents(QUIZFLOW_CODE, '000000');
        }

        $nextCode = str_pad((int) trim((string) file_get_contents(QUIZFLOW_CODE)) + 1, 6, '0', STR_PAD_LEFT);
        file_put_contents(QUIZFLOW_CODE, $nextCode);

        $params = [
            'code' => $nextCode,
            'player_name' => $playerName,
            'time' => $duration,
            'answer' => json_encode($summary['answers']),
            'difficulty' => $selectedDifficulty,
            'difficulty_label' => $selectedCatalog['label'],
            'multiplier' => $selectedCatalog['multiplier'],
            'correct_answers' => $summary['correct_answers'],
            'total_questions' => $summary['total_questions'],
            'score' => $summary['score'],
        ];

        $dbAdapter->db_query(
            'INSERT INTO answers (code, player_name, time, answer, difficulty, difficulty_label, multiplier, correct_answers, total_questions, score) VALUES (:code, :player_name, :time, :answer, :difficulty, :difficulty_label, :multiplier, :correct_answers, :total_questions, :score)',
            $params
        );

        $rankResult = $dbAdapter->db_query(
            'SELECT COUNT(*) AS better_entries FROM answers WHERE moderation_status = :status AND (score > :score OR (score = :score AND correct_answers > :correct_answers) OR (score = :score AND correct_answers = :correct_answers AND time < :time))',
            [
                'status' => 'approved',
                'score' => $summary['score'],
                'correct_answers' => $summary['correct_answers'],
                'time' => $duration,
            ]
        );

        $leaderboard = $dbAdapter->db_query(
            'SELECT player_name, score, time, difficulty_label, correct_answers, total_questions, multiplier, code, timestamp FROM answers WHERE moderation_status = :status ORDER BY score DESC, correct_answers DESC, time ASC, timestamp ASC LIMIT 10',
            ['status' => 'approved']
        );

        $result = [
            'player_name' => $playerName,
            'code' => $nextCode,
            'time' => $duration,
            'score' => $summary['score'],
            'correct_answers' => $summary['correct_answers'],
            'total_questions' => $summary['total_questions'],
            'percentage' => $summary['total_questions'] > 0 ? round(($summary['correct_answers'] / $summary['total_questions']) * 100, 1) : 0,
            'difficulty_label' => $selectedCatalog['label'],
            'multiplier' => $selectedCatalog['multiplier'],
            'leaderboard_rank' => isset($rankResult[0]['better_entries']) ? ((int) $rankResult[0]['better_entries'] + 1) : null,
            'leaderboard' => $leaderboard,
        ];

        $_SESSION = [];
        session_destroy();
    }
}

if ($result === null) {
    $leaderboard = $dbAdapter->db_query(
        'SELECT player_name, score, time, difficulty_label, correct_answers, total_questions, multiplier, code, timestamp FROM answers WHERE moderation_status = :status ORDER BY score DESC, correct_answers DESC, time ASC, timestamp ASC LIMIT 10',
        ['status' => 'approved']
    );
} else {
    $leaderboard = $result['leaderboard'];
}

function e($value) {
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function render_info_lines($lines) {
    foreach ($lines as $line) {
        echo $line;
    }
}

function format_time_value($value) {
    return number_format((float) $value, 2, ',', '.') . ' s';
}
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Quizflow</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.7.1/jquery.min.js" integrity="sha512-v2CJ7UaYy4JwqLDIrZUI/4hqeoQieOmAZNXBeQyjo21dadnwR+8ZaIJVT8EE2iyI61OV8e6M8PP2/4hpQINQ/g==" crossorigin="anonymous" referrerpolicy="no-referrer"></script>
    <script src="quiz.js"></script>
</head>
<body class="min-h-screen bg-gray-200 text-gray-900">
    <div class="container mx-auto w-[95%] py-8 md:w-[60%]">
        <div class="mb-6 flex flex-wrap items-center justify-between gap-3">
            <a href="./" class="text-sm font-semibold text-blue-700">Quizflow</a>
            <div class="flex flex-wrap gap-2">
                <a href="./?leaderboard=1" class="rounded-full bg-white px-4 py-2 text-sm font-semibold text-gray-700 shadow">Leaderboard</a>
                <a href="./?reset=1" class="rounded-full bg-white px-4 py-2 text-sm font-semibold text-gray-700 shadow">Neu starten</a>
                <a href="login.php" class="rounded-full bg-blue-600 px-4 py-2 text-sm font-semibold text-white shadow">Admin</a>
            </div>
        </div>

        <?php if (isset($_GET['leaderboard'])): ?>
            <section class="rounded-2xl bg-white p-8 shadow-lg">
                <div class="mb-6 flex items-center justify-between gap-3">
                    <div>
                        <h1 class="text-3xl font-bold">Leaderboard</h1>
                        <p class="mt-2 text-sm text-gray-500">Sortiert nach Score, richtigen Antworten und Zeit.</p>
                    </div>
                    <a href="./" class="rounded-full bg-blue-600 px-4 py-2 text-sm font-semibold text-white">Zurueck zum Quiz</a>
                </div>

                <?php if (empty($leaderboard)): ?>
                    <p class="rounded-2xl bg-gray-100 p-6 text-gray-600">Noch keine freigegebenen Eintraege vorhanden.</p>
                <?php else: ?>
                    <div class="overflow-hidden rounded-2xl border border-gray-100">
                        <table class="min-w-full divide-y divide-gray-100 text-sm">
                            <thead class="bg-gray-50 text-left text-gray-500">
                                <tr>
                                    <th class="px-4 py-3">Platz</th>
                                    <th class="px-4 py-3">Name</th>
                                    <th class="px-4 py-3">Katalog</th>
                                    <th class="px-4 py-3">Score</th>
                                    <th class="px-4 py-3">Treffer</th>
                                    <th class="px-4 py-3">Zeit</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100 bg-white">
                                <?php foreach ($leaderboard as $index => $entry): ?>
                                    <tr>
                                        <td class="px-4 py-3 font-semibold"><?php echo $index + 1; ?></td>
                                        <td class="px-4 py-3"><?php echo e($entry['player_name']); ?></td>
                                        <td class="px-4 py-3"><?php echo e($entry['difficulty_label']); ?> (x<?php echo e($entry['multiplier']); ?>)</td>
                                        <td class="px-4 py-3 font-semibold"><?php echo e($entry['score']); ?></td>
                                        <td class="px-4 py-3"><?php echo e($entry['correct_answers']); ?>/<?php echo e($entry['total_questions']); ?></td>
                                        <td class="px-4 py-3"><?php echo e(format_time_value($entry['time'])); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </section>
        <?php elseif ($result !== null): ?>
            <section class="rounded-2xl bg-white p-8 shadow-lg">
                <div class="mb-8 flex flex-col gap-3 md:flex-row md:items-start md:justify-between">
                    <div>
                        <h1 class="text-3xl font-bold">Vielen Dank fuer Ihre Teilnahme!</h1>
                        <p class="mt-2 text-gray-600">Ihr Ergebnis wurde fuer das Leaderboard gespeichert.</p>
                    </div>
                    <?php if ($result['leaderboard_rank'] !== null): ?>
                        <div class="rounded-2xl bg-blue-50 px-5 py-4 text-blue-800">
                            <p class="text-sm font-semibold uppercase tracking-wide">Aktueller Rang</p>
                            <p class="text-3xl font-bold">#<?php echo e($result['leaderboard_rank']); ?></p>
                        </div>
                    <?php endif; ?>
                </div>

                <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
                    <div class="rounded-2xl bg-gray-50 p-5">
                        <p class="text-sm text-gray-500">Name</p>
                        <p class="mt-2 text-2xl font-bold"><?php echo e($result['player_name']); ?></p>
                    </div>
                    <div class="rounded-2xl bg-gray-50 p-5">
                        <p class="text-sm text-gray-500">Code</p>
                        <p class="mt-2 text-2xl font-bold"><?php echo e($result['code']); ?></p>
                    </div>
                    <div class="rounded-2xl bg-gray-50 p-5">
                        <p class="text-sm text-gray-500">Score</p>
                        <p class="mt-2 text-2xl font-bold"><?php echo e($result['score']); ?></p>
                        <p class="mt-1 text-sm text-gray-500"><?php echo e($result['difficulty_label']); ?> mit Multiplikator x<?php echo e($result['multiplier']); ?></p>
                    </div>
                    <div class="rounded-2xl bg-gray-50 p-5">
                        <p class="text-sm text-gray-500">Richtige Antworten</p>
                        <p class="mt-2 text-2xl font-bold"><?php echo e($result['correct_answers']); ?>/<?php echo e($result['total_questions']); ?></p>
                        <p class="mt-1 text-sm text-gray-500"><?php echo e($result['percentage']); ?>% in <?php echo e(format_time_value($result['time'])); ?></p>
                    </div>
                </div>

                <div class="mt-8 rounded-2xl bg-gray-50 p-6">
                    <?php render_info_lines($quizData['outro']); ?>
                </div>

                <div class="mt-8 flex flex-wrap gap-3">
                    <a href="./" class="rounded-full bg-blue-600 px-5 py-3 font-semibold text-white">Nochmal spielen</a>
                    <a href="./?leaderboard=1" class="rounded-full bg-white px-5 py-3 font-semibold text-gray-700 shadow">Gesamtes Leaderboard</a>
                </div>

                <?php if (!empty($leaderboard)): ?>
                    <div class="mt-10">
                        <h2 class="text-xl font-bold">Top 10</h2>
                        <div class="mt-4 overflow-hidden rounded-2xl border border-gray-100">
                            <table class="min-w-full divide-y divide-gray-100 text-sm">
                                <thead class="bg-gray-50 text-left text-gray-500">
                                    <tr>
                                        <th class="px-4 py-3">Platz</th>
                                        <th class="px-4 py-3">Name</th>
                                        <th class="px-4 py-3">Score</th>
                                        <th class="px-4 py-3">Zeit</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-100 bg-white">
                                    <?php foreach ($leaderboard as $index => $entry): ?>
                                        <tr>
                                            <td class="px-4 py-3 font-semibold"><?php echo $index + 1; ?></td>
                                            <td class="px-4 py-3"><?php echo e($entry['player_name']); ?></td>
                                            <td class="px-4 py-3 font-semibold"><?php echo e($entry['score']); ?></td>
                                            <td class="px-4 py-3"><?php echo e(format_time_value($entry['time'])); ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                <?php endif; ?>
            </section>
        <?php elseif ($selectedCatalog === null || !$quizStarted): ?>
            <section class="grid gap-6 lg:grid-cols-[1.4fr_0.9fr]">
                <div class="rounded-2xl bg-white p-8 shadow-lg">
                    <?php if ($selectedCatalog === null): ?>
                        <?php render_info_lines($quizData['intro_1']); ?>
                        <div class="mt-8 grid gap-4 md:grid-cols-3">
                            <?php foreach ($catalogs as $catalogId => $catalog): ?>
                                <a href="./?difficulty=<?php echo urlencode((string) $catalogId); ?>" class="flex min-h-32 flex-col justify-between rounded-2xl border border-gray-100 bg-gray-50 p-5 shadow-sm transition hover:-translate-y-1 hover:shadow-md">
                                    <div>
                                        <p class="text-sm font-semibold uppercase tracking-wide text-gray-500">Katalog <?php echo e($catalogId); ?></p>
                                        <h2 class="mt-2 text-2xl font-bold text-gray-900"><?php echo e($catalog['label']); ?></h2>
                                    </div>
                                    <p class="mt-6 text-sm text-gray-600">Multiplikator x<?php echo e($catalog['multiplier']); ?></p>
                                </a>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <div class="flex items-start justify-between gap-3">
                            <div>
                                <p class="text-sm font-semibold uppercase tracking-wide text-gray-500">Ausgewaehlter Katalog</p>
                                <h1 class="mt-2 text-3xl font-bold"><?php echo e($selectedCatalog['label']); ?></h1>
                                <p class="mt-2 text-gray-600">Score-Multiplikator x<?php echo e($selectedCatalog['multiplier']); ?></p>
                            </div>
                            <a href="./?reset=1" class="rounded-full bg-gray-100 px-4 py-2 text-sm font-semibold text-gray-700">Andere Auswahl</a>
                        </div>

                        <div class="mt-8 rounded-2xl bg-gray-50 p-6">
                            <?php render_info_lines($quizData['intro_2']); ?>
                        </div>

                        <?php if ($errorMessage !== null): ?>
                            <div class="mt-6 rounded-2xl bg-red-50 p-4 text-sm font-semibold text-red-700"><?php echo e($errorMessage); ?></div>
                        <?php endif; ?>

                        <form method="post" class="mt-8 space-y-4">
                            <input type="hidden" name="action" value="start_quiz">
                            <div>
                                <label for="player_name" class="mb-2 block text-sm font-semibold text-gray-700">Name fuer das Leaderboard</label>
                                <input id="player_name" name="player_name" maxlength="40" value="<?php echo e($_SESSION['player_name'] ?? ''); ?>" class="w-full rounded-2xl border border-gray-200 px-4 py-3 outline-none transition focus:border-blue-500" placeholder="z. B. Team Quizflow" required>
                                <p class="mt-2 text-sm text-gray-500">Der Name wird zusammen mit Score, Katalog und Zeit im Leaderboard angezeigt.</p>
                            </div>
                            <button type="submit" class="rounded-full bg-blue-600 px-6 py-3 font-semibold text-white">Quiz starten</button>
                        </form>
                    <?php endif; ?>
                </div>

                <aside class="rounded-2xl bg-white p-8 shadow-lg">
                    <div class="flex items-center justify-between gap-3">
                        <h2 class="text-xl font-bold">Top 10</h2>
                        <a href="./?leaderboard=1" class="text-sm font-semibold text-blue-700">Alle ansehen</a>
                    </div>

                    <?php if (empty($leaderboard)): ?>
                        <p class="mt-6 rounded-2xl bg-gray-50 p-5 text-sm text-gray-600">Noch keine freigegebenen Eintraege vorhanden.</p>
                    <?php else: ?>
                        <div class="mt-6 space-y-3">
                            <?php foreach ($leaderboard as $index => $entry): ?>
                                <div class="rounded-2xl bg-gray-50 p-4">
                                    <div class="flex items-center justify-between gap-4">
                                        <div>
                                            <p class="text-sm font-semibold text-gray-500">#<?php echo $index + 1; ?> · <?php echo e($entry['difficulty_label']); ?></p>
                                            <p class="mt-1 text-lg font-bold"><?php echo e($entry['player_name']); ?></p>
                                        </div>
                                        <div class="text-right">
                                            <p class="text-lg font-bold"><?php echo e($entry['score']); ?></p>
                                            <p class="text-sm text-gray-500"><?php echo e(format_time_value($entry['time'])); ?></p>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </aside>
            </section>
        <?php else: ?>
            <?php $questions = $quizManager->getQuestions($quizData, $selectedDifficulty); ?>
            <div class="progress-bar fixed left-0 top-0 h-2 w-0 bg-green-500"></div>
            <section class="rounded-2xl bg-transparent shadow-none">
                <div class="mb-6 rounded-2xl bg-white p-6 shadow-lg">
                    <div class="flex flex-wrap items-center justify-between gap-3">
                        <div>
                            <p class="text-sm font-semibold uppercase tracking-wide text-gray-500">Spieler</p>
                            <h1 class="mt-1 text-2xl font-bold"><?php echo e($_SESSION['player_name'] ?? ''); ?></h1>
                        </div>
                        <div class="text-right">
                            <p class="text-sm font-semibold uppercase tracking-wide text-gray-500"><?php echo e($selectedCatalog['label']); ?></p>
                            <p class="mt-1 text-lg font-bold">Multiplikator x<?php echo e($selectedCatalog['multiplier']); ?></p>
                        </div>
                    </div>
                </div>

                <form id="quizForm" class="space-y-4 md:flex md:flex-col md:gap-6 md:space-y-0" method="post">
                    <input type="hidden" name="action" value="submit_quiz">
                    <?php foreach ($questions as $questionNumber => $question): ?>
                        <fieldset class="rounded-2xl bg-white p-8 shadow-lg">
                            <p class="pb-8 text-2xl font-bold leading-normal"><?php echo ($questionNumber + 1) . '. ' . e($question['question']); ?></p>
                            <div class="flex flex-col gap-4">
                                <?php foreach ($question['answers'] as $answerNumber => $answer): ?>
                                    <?php $optionId = 'option' . $questionNumber . '_' . $answerNumber; ?>
                                    <?php $isCorrect = $answerNumber === (int) $question['correctAnswer'] ? 'true' : 'false'; ?>
                                    <input type="radio" name="q_<?php echo $questionNumber + 1; ?>" value="<?php echo $answerNumber + 1; ?>" id="<?php echo e($optionId); ?>" class="hidden" data-correct="<?php echo $isCorrect; ?>">
                                    <label for="<?php echo e($optionId); ?>" class="option-label cursor-pointer rounded-3xl border-2 px-4 py-3 transition"><?php echo e($answer); ?></label>
                                <?php endforeach; ?>
                            </div>
                        </fieldset>
                    <?php endforeach; ?>
                    <fieldset>
                        <button type="submit" class="rounded-full bg-blue-600 px-5 py-3 font-semibold text-white">Absenden</button>
                    </fieldset>
                </form>

                <div class="fixed bottom-0 left-0 flex w-full justify-between rounded-t-2xl bg-white p-4 shadow-md md:hidden">
                    <button class="rounded-full bg-blue-600 px-4 py-2 font-semibold text-white" id="prev">Zurueck</button>
                    <button class="rounded-full bg-blue-600 px-4 py-2 font-semibold text-white" id="next">Weiter</button>
                </div>
            </section>
        <?php endif; ?>
    </div>
</body>
</html>
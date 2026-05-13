<?php
session_start();

if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    header('Location: login.php');
    exit;
}

const APP_NAME = 'Quizflow';

include_once __DIR__ . '/includes/core/db_adapter.php';
include_once __DIR__ . '/includes/core/quiz_manager.php';

$dbAdapter = new \Quizflow\Core\DatabaseAdapter();
$dbAdapter->db_init();
$quizManager = new \Quizflow\Core\QuizManager();

$quizData = $quizManager->loadData();
$catalogs = $quizManager->getCatalogs($quizData);
$section = $_GET['section'] ?? 'leaderboard';
$message = null;
$error = null;

if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $postedToken = $_POST['csrf_token'] ?? '';
    if (!hash_equals($_SESSION['csrf_token'], $postedToken)) {
        $error = 'Die Anfrage konnte nicht verifiziert werden.';
    } else {
        $action = $_POST['action'] ?? '';

        if ($action === 'delete_entry') {
            $dbAdapter->db_query('DELETE FROM answers WHERE uuid = :uuid', ['uuid' => $_POST['uuid'] ?? '']);
            $message = 'Eintrag wurde aus der Datenbank geloescht.';
            $section = 'leaderboard';
        } elseif ($action === 'block_entry') {
            $dbAdapter->db_query(
                'UPDATE answers SET moderation_status = :status, moderation_note = :note, flagged_at = CURRENT_TIMESTAMP WHERE uuid = :uuid',
                [
                    'status' => 'blocked',
                    'note' => trim((string) ($_POST['moderation_note'] ?? 'Manuell blockiert')),
                    'uuid' => $_POST['uuid'] ?? '',
                ]
            );
            $message = 'Eintrag wurde aus dem Leaderboard entfernt.';
            $section = 'leaderboard';
        } elseif ($action === 'approve_entry') {
            $dbAdapter->db_query(
                'UPDATE answers SET moderation_status = :status, moderation_note = NULL, flagged_at = NULL WHERE uuid = :uuid',
                [
                    'status' => 'approved',
                    'uuid' => $_POST['uuid'] ?? '',
                ]
            );
            $message = 'Eintrag wurde wieder freigegeben.';
            $section = 'leaderboard';
        } elseif ($action === 'save_catalog') {
            $catalogId = trim((string) ($_POST['catalog_id'] ?? ''));
            $label = trim((string) ($_POST['catalog_label'] ?? ''));
            $multiplier = (float) ($_POST['catalog_multiplier'] ?? 1);

            if (!preg_match('/^[A-Za-z0-9_-]+$/', $catalogId)) {
                $error = 'Katalog-ID darf nur Buchstaben, Zahlen, Bindestriche und Unterstriche enthalten.';
            } elseif ($label === '') {
                $error = 'Bitte einen Katalognamen angeben.';
            } elseif ($multiplier <= 0) {
                $error = 'Der Multiplikator muss groesser als 0 sein.';
            } else {
                $quizData = $quizManager->saveCatalog($quizData, $catalogId, $label, $multiplier);
                if ($quizManager->saveData($quizData)) {
                    $catalogs = $quizManager->getCatalogs($quizData);
                    $message = 'Katalog gespeichert.';
                    $section = 'questions';
                    $_GET['catalog'] = $catalogId;
                } else {
                    $error = 'Katalog konnte nicht gespeichert werden.';
                }
            }
        } elseif ($action === 'delete_catalog') {
            $catalogId = (string) ($_POST['catalog_id'] ?? '');
            if (count($catalogs) <= 1) {
                $error = 'Mindestens ein Katalog muss bestehen bleiben.';
            } else {
                $quizData = $quizManager->deleteCatalog($quizData, $catalogId);
                if ($quizManager->saveData($quizData)) {
                    $catalogs = $quizManager->getCatalogs($quizData);
                    $message = 'Katalog geloescht.';
                    $section = 'questions';
                    unset($_GET['catalog'], $_GET['edit']);
                } else {
                    $error = 'Katalog konnte nicht geloescht werden.';
                }
            }
        } elseif ($action === 'save_question') {
            $catalogId = (string) ($_POST['catalog_id'] ?? '');
            $questionIndex = isset($_POST['question_index']) && $_POST['question_index'] !== '' ? (int) $_POST['question_index'] : null;
            $questionText = trim((string) ($_POST['question_text'] ?? ''));
            $answersText = trim((string) ($_POST['answers_text'] ?? ''));
            $answers = preg_split('/\r\n|\r|\n/', $answersText) ?: [];
            $answers = array_values(array_filter(array_map('trim', $answers), static function ($answer) {
                return $answer !== '';
            }));
            $correctAnswer = (int) ($_POST['correct_answer'] ?? 1) - 1;

            if (!isset($catalogs[$catalogId])) {
                $error = 'Der ausgewaehlte Katalog existiert nicht.';
            } elseif ($questionText === '') {
                $error = 'Bitte einen Fragetext angeben.';
            } elseif (count($answers) < 2) {
                $error = 'Bitte mindestens zwei Antwortoptionen angeben.';
            } elseif (!isset($answers[$correctAnswer])) {
                $error = 'Die richtige Antwort muss zu einer vorhandenen Option passen.';
            } else {
                $quizData = $quizManager->saveQuestion($quizData, $catalogId, $questionIndex, $questionText, $answers, $correctAnswer);
                if ($quizManager->saveData($quizData)) {
                    $catalogs = $quizManager->getCatalogs($quizData);
                    $message = 'Frage gespeichert.';
                    $section = 'questions';
                    $_GET['catalog'] = $catalogId;
                    unset($_GET['edit']);
                } else {
                    $error = 'Frage konnte nicht gespeichert werden.';
                }
            }
        } elseif ($action === 'delete_question') {
            $catalogId = (string) ($_POST['catalog_id'] ?? '');
            $questionIndex = (int) ($_POST['question_index'] ?? -1);

            $quizData = $quizManager->deleteQuestion($quizData, $catalogId, $questionIndex);
            if ($quizManager->saveData($quizData)) {
                $catalogs = $quizManager->getCatalogs($quizData);
                $message = 'Frage geloescht.';
                $section = 'questions';
                $_GET['catalog'] = $catalogId;
                unset($_GET['edit']);
            } else {
                $error = 'Frage konnte nicht geloescht werden.';
            }
        }
    }
}

$catalogKeys = array_keys($catalogs);
$activeCatalogId = $_GET['catalog'] ?? ($catalogKeys[0] ?? null);
if ($activeCatalogId !== null && !isset($catalogs[$activeCatalogId])) {
    $activeCatalogId = $catalogKeys[0] ?? null;
}

$activeQuestions = $activeCatalogId !== null ? $quizManager->getQuestions($quizData, $activeCatalogId) : [];
$editIndex = isset($_GET['edit']) && ctype_digit((string) $_GET['edit']) ? (int) $_GET['edit'] : null;
$editingQuestion = ($activeCatalogId !== null && $editIndex !== null && isset($activeQuestions[$editIndex])) ? $activeQuestions[$editIndex] : null;

$entries = $dbAdapter->db_query('SELECT * FROM answers ORDER BY timestamp DESC');
$leaderboardStats = [
    'total' => count($entries),
    'approved' => 0,
    'blocked' => 0,
    'best_score' => 0,
];

foreach ($entries as $index => $entry) {
    $correctAnswers = isset($entry['correct_answers']) ? (int) $entry['correct_answers'] : 0;
    $totalQuestions = isset($entry['total_questions']) ? (int) $entry['total_questions'] : 0;

    if ($totalQuestions === 0 && !empty($entry['answer'])) {
        $decodedAnswers = json_decode($entry['answer'], true);
        if (is_array($decodedAnswers)) {
            $totalQuestions = count($decodedAnswers);
            $correctAnswers = 0;
            foreach ($decodedAnswers as $answer) {
                if (($answer['isCorrect'] ?? 'false') === 'true') {
                    $correctAnswers++;
                }
            }
        }
    }

    $entries[$index]['correct_answers'] = $correctAnswers;
    $entries[$index]['total_questions'] = $totalQuestions;
    $entries[$index]['correct_percentage'] = $totalQuestions > 0 ? round(($correctAnswers / $totalQuestions) * 100, 1) : 0;
    $entries[$index]['score'] = isset($entry['score']) ? (float) $entry['score'] : (float) $correctAnswers;
    $entries[$index]['moderation_status'] = $entry['moderation_status'] ?? 'approved';

    if ($entries[$index]['moderation_status'] === 'approved') {
        $leaderboardStats['approved']++;
    } else {
        $leaderboardStats['blocked']++;
    }

    if ($entries[$index]['score'] > $leaderboardStats['best_score']) {
        $leaderboardStats['best_score'] = $entries[$index]['score'];
    }
}

function e($value) {
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Quizflow Admin</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="min-h-screen bg-gray-200 text-gray-900">
    <div class="mx-auto max-w-7xl px-4 py-8">
        <div class="mb-6 rounded-3xl bg-white p-6 shadow-lg">
            <div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
                <div>
                    <p class="text-sm font-semibold uppercase tracking-wide text-gray-500">Administration</p>
                    <h1 class="mt-2 text-3xl font-bold">Quizflow Control Center</h1>
                    <p class="mt-2 text-gray-600">Leaderboard moderieren, Fragenkataloge pflegen und Multiplikatoren steuern.</p>
                </div>
                <div class="flex flex-wrap gap-2">
                    <a href="./" class="rounded-full bg-white px-4 py-2 text-sm font-semibold text-gray-700 shadow">Zur Website</a>
                    <a href="admin.php?section=leaderboard" class="rounded-full px-4 py-2 text-sm font-semibold <?php echo $section === 'leaderboard' ? 'bg-blue-600 text-white' : 'bg-gray-100 text-gray-700'; ?>">Leaderboard</a>
                    <a href="admin.php?section=questions" class="rounded-full px-4 py-2 text-sm font-semibold <?php echo $section === 'questions' ? 'bg-blue-600 text-white' : 'bg-gray-100 text-gray-700'; ?>">Frageneditor</a>
                </div>
            </div>
        </div>

        <?php if ($message !== null): ?>
            <div class="mb-6 rounded-2xl bg-green-50 p-4 text-sm font-semibold text-green-700"><?php echo e($message); ?></div>
        <?php endif; ?>
        <?php if ($error !== null): ?>
            <div class="mb-6 rounded-2xl bg-red-50 p-4 text-sm font-semibold text-red-700"><?php echo e($error); ?></div>
        <?php endif; ?>

        <?php if ($section === 'questions'): ?>
            <div class="grid gap-6 xl:grid-cols-[0.95fr_1.4fr]">
                <section class="rounded-3xl bg-white p-6 shadow-lg">
                    <div class="mb-6">
                        <h2 class="text-2xl font-bold">Kataloge</h2>
                        <p class="mt-1 text-sm text-gray-500">Labels und Multiplikatoren direkt in der JSON-Datei pflegen.</p>
                    </div>

                    <div class="space-y-4">
                        <?php foreach ($catalogs as $catalogId => $catalog): ?>
                            <div class="rounded-2xl border border-gray-100 bg-gray-50 p-4">
                                <div class="mb-3 flex items-center justify-between gap-3">
                                    <a href="admin.php?section=questions&amp;catalog=<?php echo urlencode((string) $catalogId); ?>" class="text-lg font-bold text-gray-900"><?php echo e($catalog['label']); ?></a>
                                    <span class="rounded-full bg-white px-3 py-1 text-xs font-semibold text-gray-500 shadow">ID <?php echo e($catalogId); ?></span>
                                </div>
                                <form method="post" class="space-y-3">
                                    <input type="hidden" name="csrf_token" value="<?php echo e($_SESSION['csrf_token']); ?>">
                                    <input type="hidden" name="action" value="save_catalog">
                                    <input type="hidden" name="catalog_id" value="<?php echo e($catalogId); ?>">
                                    <div>
                                        <label class="mb-1 block text-sm font-semibold text-gray-600">Name</label>
                                        <input name="catalog_label" value="<?php echo e($catalog['label']); ?>" class="w-full rounded-2xl border border-gray-200 px-4 py-2">
                                    </div>
                                    <div>
                                        <label class="mb-1 block text-sm font-semibold text-gray-600">Multiplikator</label>
                                        <input type="number" step="0.1" min="0.1" name="catalog_multiplier" value="<?php echo e($catalog['multiplier']); ?>" class="w-full rounded-2xl border border-gray-200 px-4 py-2">
                                    </div>
                                    <button type="submit" class="rounded-full bg-blue-600 px-4 py-2 text-sm font-semibold text-white">Speichern</button>
                                </form>
                                <form method="post" class="mt-3">
                                    <input type="hidden" name="csrf_token" value="<?php echo e($_SESSION['csrf_token']); ?>">
                                    <input type="hidden" name="action" value="delete_catalog">
                                    <input type="hidden" name="catalog_id" value="<?php echo e($catalogId); ?>">
                                    <button type="submit" class="rounded-full bg-red-600 px-4 py-2 text-sm font-semibold text-white">Loeschen</button>
                                </form>
                            </div>
                        <?php endforeach; ?>
                    </div>

                    <div class="mt-8 rounded-2xl border border-dashed border-gray-200 p-4">
                        <h3 class="text-lg font-bold">Neuen Katalog anlegen</h3>
                        <form method="post" class="mt-4 space-y-3">
                            <input type="hidden" name="csrf_token" value="<?php echo e($_SESSION['csrf_token']); ?>">
                            <input type="hidden" name="action" value="save_catalog">
                            <div>
                                <label class="mb-1 block text-sm font-semibold text-gray-600">ID</label>
                                <input name="catalog_id" placeholder="z. B. experten" class="w-full rounded-2xl border border-gray-200 px-4 py-2">
                            </div>
                            <div>
                                <label class="mb-1 block text-sm font-semibold text-gray-600">Name</label>
                                <input name="catalog_label" placeholder="Experten" class="w-full rounded-2xl border border-gray-200 px-4 py-2">
                            </div>
                            <div>
                                <label class="mb-1 block text-sm font-semibold text-gray-600">Multiplikator</label>
                                <input type="number" step="0.1" min="0.1" name="catalog_multiplier" value="1" class="w-full rounded-2xl border border-gray-200 px-4 py-2">
                            </div>
                            <button type="submit" class="rounded-full bg-gray-900 px-4 py-2 text-sm font-semibold text-white">Katalog anlegen</button>
                        </form>
                    </div>
                </section>

                <section class="rounded-3xl bg-white p-6 shadow-lg">
                    <div class="mb-6 flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
                        <div>
                            <h2 class="text-2xl font-bold">Frageneditor</h2>
                            <?php if ($activeCatalogId !== null): ?>
                                <p class="mt-1 text-sm text-gray-500">Aktiver Katalog: <?php echo e($catalogs[$activeCatalogId]['label']); ?> mit Multiplikator x<?php echo e($catalogs[$activeCatalogId]['multiplier']); ?></p>
                            <?php endif; ?>
                        </div>
                        <?php if ($editingQuestion !== null): ?>
                            <a href="admin.php?section=questions&amp;catalog=<?php echo urlencode((string) $activeCatalogId); ?>" class="rounded-full bg-gray-100 px-4 py-2 text-sm font-semibold text-gray-700">Neue Frage</a>
                        <?php endif; ?>
                    </div>

                    <?php if ($activeCatalogId === null): ?>
                        <p class="rounded-2xl bg-gray-50 p-5 text-gray-600">Es ist noch kein Fragenkatalog vorhanden.</p>
                    <?php else: ?>
                        <div class="rounded-2xl bg-gray-50 p-5">
                            <form method="post" class="space-y-4">
                                <input type="hidden" name="csrf_token" value="<?php echo e($_SESSION['csrf_token']); ?>">
                                <input type="hidden" name="action" value="save_question">
                                <input type="hidden" name="catalog_id" value="<?php echo e($activeCatalogId); ?>">
                                <input type="hidden" name="question_index" value="<?php echo $editIndex !== null ? e((string) $editIndex) : ''; ?>">
                                <div>
                                    <label class="mb-1 block text-sm font-semibold text-gray-600">Frage</label>
                                    <textarea name="question_text" rows="3" class="w-full rounded-2xl border border-gray-200 px-4 py-3"><?php echo e($editingQuestion['question'] ?? ''); ?></textarea>
                                </div>
                                <div>
                                    <label class="mb-1 block text-sm font-semibold text-gray-600">Antwortoptionen</label>
                                    <textarea name="answers_text" rows="6" class="w-full rounded-2xl border border-gray-200 px-4 py-3" placeholder="Eine Antwort pro Zeile"><?php echo e($editingQuestion !== null ? implode(PHP_EOL, $editingQuestion['answers']) : ''); ?></textarea>
                                    <p class="mt-2 text-sm text-gray-500">Mindestens zwei Antworten. Jede Zeile entspricht einer Option.</p>
                                </div>
                                <div>
                                    <label class="mb-1 block text-sm font-semibold text-gray-600">Richtige Antwort</label>
                                    <input type="number" min="1" name="correct_answer" value="<?php echo e((string) (($editingQuestion['correctAnswer'] ?? 0) + 1)); ?>" class="w-full rounded-2xl border border-gray-200 px-4 py-2">
                                    <p class="mt-2 text-sm text-gray-500">Nummer der korrekten Zeile, beginnend bei 1.</p>
                                </div>
                                <button type="submit" class="rounded-full bg-blue-600 px-5 py-3 font-semibold text-white"><?php echo $editingQuestion !== null ? 'Frage aktualisieren' : 'Frage anlegen'; ?></button>
                            </form>
                        </div>

                        <div class="mt-8 space-y-4">
                            <?php foreach ($activeQuestions as $questionIndex => $question): ?>
                                <article class="rounded-2xl border border-gray-100 bg-white p-5 shadow-sm">
                                    <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
                                        <div>
                                            <p class="text-sm font-semibold uppercase tracking-wide text-gray-500">Frage <?php echo $questionIndex + 1; ?></p>
                                            <h3 class="mt-2 text-lg font-bold text-gray-900"><?php echo e($question['question']); ?></h3>
                                            <ol class="mt-4 space-y-2 text-sm text-gray-600">
                                                <?php foreach ($question['answers'] as $answerIndex => $answer): ?>
                                                    <li class="rounded-xl px-3 py-2 <?php echo $answerIndex === (int) $question['correctAnswer'] ? 'bg-green-50 font-semibold text-green-700' : 'bg-gray-50'; ?>">
                                                        <?php echo ($answerIndex + 1) . '. ' . e($answer); ?>
                                                    </li>
                                                <?php endforeach; ?>
                                            </ol>
                                        </div>
                                        <div class="flex flex-wrap gap-2 lg:justify-end">
                                            <a href="admin.php?section=questions&amp;catalog=<?php echo urlencode((string) $activeCatalogId); ?>&amp;edit=<?php echo $questionIndex; ?>" class="rounded-full bg-gray-100 px-4 py-2 text-sm font-semibold text-gray-700">Bearbeiten</a>
                                            <form method="post">
                                                <input type="hidden" name="csrf_token" value="<?php echo e($_SESSION['csrf_token']); ?>">
                                                <input type="hidden" name="action" value="delete_question">
                                                <input type="hidden" name="catalog_id" value="<?php echo e($activeCatalogId); ?>">
                                                <input type="hidden" name="question_index" value="<?php echo $questionIndex; ?>">
                                                <button type="submit" class="rounded-full bg-red-600 px-4 py-2 text-sm font-semibold text-white">Loeschen</button>
                                            </form>
                                        </div>
                                    </div>
                                </article>
                            <?php endforeach; ?>

                            <?php if (empty($activeQuestions)): ?>
                                <p class="rounded-2xl bg-gray-50 p-5 text-gray-600">In diesem Katalog sind noch keine Fragen vorhanden.</p>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                </section>
            </div>
        <?php else: ?>
            <section class="space-y-6">
                <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
                    <div class="rounded-3xl bg-white p-6 shadow-lg">
                        <p class="text-sm font-semibold uppercase tracking-wide text-gray-500">Eintraege</p>
                        <p class="mt-2 text-3xl font-bold"><?php echo e((string) $leaderboardStats['total']); ?></p>
                    </div>
                    <div class="rounded-3xl bg-white p-6 shadow-lg">
                        <p class="text-sm font-semibold uppercase tracking-wide text-gray-500">Freigegeben</p>
                        <p class="mt-2 text-3xl font-bold"><?php echo e((string) $leaderboardStats['approved']); ?></p>
                    </div>
                    <div class="rounded-3xl bg-white p-6 shadow-lg">
                        <p class="text-sm font-semibold uppercase tracking-wide text-gray-500">Blockiert</p>
                        <p class="mt-2 text-3xl font-bold"><?php echo e((string) $leaderboardStats['blocked']); ?></p>
                    </div>
                    <div class="rounded-3xl bg-white p-6 shadow-lg">
                        <p class="text-sm font-semibold uppercase tracking-wide text-gray-500">Bester Score</p>
                        <p class="mt-2 text-3xl font-bold"><?php echo e((string) $leaderboardStats['best_score']); ?></p>
                    </div>
                </div>

                <div class="overflow-hidden rounded-3xl bg-white shadow-lg">
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-100 text-sm">
                            <thead class="bg-gray-50 text-left text-gray-500">
                                <tr>
                                    <th class="px-4 py-3">Zeitpunkt</th>
                                    <th class="px-4 py-3">Name</th>
                                    <th class="px-4 py-3">Code</th>
                                    <th class="px-4 py-3">Katalog</th>
                                    <th class="px-4 py-3">Score</th>
                                    <th class="px-4 py-3">Treffer</th>
                                    <th class="px-4 py-3">Zeit</th>
                                    <th class="px-4 py-3">Status</th>
                                    <th class="px-4 py-3">Aktionen</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100 bg-white">
                                <?php foreach ($entries as $entry): ?>
                                    <tr class="align-top">
                                        <td class="px-4 py-4"><?php echo e(date('d.m.Y H:i:s', strtotime($entry['timestamp']))); ?></td>
                                        <td class="px-4 py-4 font-semibold"><?php echo e($entry['player_name'] ?? 'Ohne Name'); ?></td>
                                        <td class="px-4 py-4"><?php echo e(str_pad((string) ($entry['code'] ?? ''), 6, '0', STR_PAD_LEFT)); ?></td>
                                        <td class="px-4 py-4"><?php echo e($entry['difficulty_label'] ?? ($entry['difficulty'] ?? '-')); ?></td>
                                        <td class="px-4 py-4 font-semibold"><?php echo e((string) $entry['score']); ?></td>
                                        <td class="px-4 py-4"><?php echo e((string) $entry['correct_answers']); ?>/<?php echo e((string) $entry['total_questions']); ?> (<?php echo e((string) $entry['correct_percentage']); ?>%)</td>
                                        <td class="px-4 py-4"><?php echo e(number_format((float) $entry['time'], 2, ',', '.')); ?> s</td>
                                        <td class="px-4 py-4">
                                            <span class="rounded-full px-3 py-1 text-xs font-semibold <?php echo $entry['moderation_status'] === 'approved' ? 'bg-green-50 text-green-700' : 'bg-red-50 text-red-700'; ?>">
                                                <?php echo e($entry['moderation_status']); ?>
                                            </span>
                                            <?php if (!empty($entry['moderation_note'])): ?>
                                                <p class="mt-2 max-w-xs text-xs text-gray-500"><?php echo e($entry['moderation_note']); ?></p>
                                            <?php endif; ?>
                                        </td>
                                        <td class="px-4 py-4">
                                            <div class="flex min-w-[15rem] flex-col gap-2">
                                                <?php if ($entry['moderation_status'] === 'approved'): ?>
                                                    <form method="post" class="space-y-2">
                                                        <input type="hidden" name="csrf_token" value="<?php echo e($_SESSION['csrf_token']); ?>">
                                                        <input type="hidden" name="action" value="block_entry">
                                                        <input type="hidden" name="uuid" value="<?php echo e($entry['uuid']); ?>">
                                                        <input name="moderation_note" value="Anstoessiger oder unpassender Name" class="w-full rounded-xl border border-gray-200 px-3 py-2 text-xs">
                                                        <button type="submit" class="w-full rounded-full bg-yellow-500 px-4 py-2 text-xs font-semibold text-white">Aus Leaderboard entfernen</button>
                                                    </form>
                                                <?php else: ?>
                                                    <form method="post">
                                                        <input type="hidden" name="csrf_token" value="<?php echo e($_SESSION['csrf_token']); ?>">
                                                        <input type="hidden" name="action" value="approve_entry">
                                                        <input type="hidden" name="uuid" value="<?php echo e($entry['uuid']); ?>">
                                                        <button type="submit" class="w-full rounded-full bg-green-600 px-4 py-2 text-xs font-semibold text-white">Wieder freigeben</button>
                                                    </form>
                                                <?php endif; ?>
                                                <form method="post">
                                                    <input type="hidden" name="csrf_token" value="<?php echo e($_SESSION['csrf_token']); ?>">
                                                    <input type="hidden" name="action" value="delete_entry">
                                                    <input type="hidden" name="uuid" value="<?php echo e($entry['uuid']); ?>">
                                                    <button type="submit" class="w-full rounded-full bg-red-600 px-4 py-2 text-xs font-semibold text-white">Aus Datenbank loeschen</button>
                                                </form>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>

                                <?php if (empty($entries)): ?>
                                    <tr>
                                        <td colspan="9" class="px-4 py-10 text-center text-gray-500">Noch keine Eintraege vorhanden.</td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </section>
        <?php endif; ?>
    </div>
</body>
</html>
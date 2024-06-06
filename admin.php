<?php
    // use db_adapter
    const APP_NAME = 'Quizflow';
    include_once __DIR__ . '/includes/core/db_adapter.php';
    $dbAdapter = new \Quizflow\Core\DatabaseAdapter();
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
        // Fetch all data from the database
        $data = $dbAdapter->db_query('SELECT * FROM answers ORDER BY timestamp DESC');
    
        // Calculate the percentage of correct answers
        foreach ($data as $key => $value) {
            // Check if the key "answer" exists and is not null
            if (isset($value['answer']) && $value['answer'] !== null) {
                // Decode the JSON string into an array
                $answers = json_decode($value['answer'], true);
    
                $totalAnswers = count($answers);
                $correctAnswers = 0;
                foreach ($answers as $answer) {
                    if ($answer['isCorrect'] == 'true') {
                        $correctAnswers++;
                    }
                }
                // Check if totalAnswers is greater than zero before performing the division
                if ($totalAnswers > 0) {
                    $data[$key]['correctPercentage'] = ($correctAnswers / $totalAnswers) * 100;
                } else {
                    $data[$key]['correctPercentage'] = 0;
                }
                $data[$key]['answers'] = $answers; // Replace the JSON string with the decoded array
            }
        }
    ?>
    
    <!-- Display the data in a table -->
    <div class="m-4 bg-white shadow-lg rounded-2xl p-8 text-left">
        <table class="w-full">
            <tr>
                <th class="p-2 border-b max-w-lg overflow-auto">Timestamp</th>
                <th class="p-2 border-b max-w-lg overflow-auto">Code</th>
                <th class="p-2 border-b max-w-lg overflow-auto">Time</th>
                <th class="p-2 border-b max-w-lg overflow-auto">Correct Answers (%)</th>
            </tr>
            <?php foreach ($data as $entry): ?>
                <tr>
                    <td class="p-2 border-b max-w-lg overflow-auto"><?php echo isset($entry['timestamp']) ? date('H:i:s \U\h\r, d.m.Y', strtotime($entry['timestamp'])) : ''; ?></td>
                    <td class="p-2 border-b max-w-lg overflow-auto"><?php echo isset($entry['code']) ? str_pad($entry['code'], 6, '0', STR_PAD_LEFT) : ''; ?></td>
                    <td class="p-2 border-b max-w-lg overflow-auto"><?php echo isset($entry['time']) ? $entry['time'] : ''; ?></td>
                    <td class="p-2 border-b max-w-lg overflow-auto"><?php echo isset($entry['correctPercentage']) ? $entry['correctPercentage'] : ''; ?></td>
                </tr>
                <tr>
                    <td colspan="4" class="p-2 border-b max-w-lg overflow-auto">
                        <details>
                            <summary>View Answers</summary>
                            <ul>
                                <?php if (isset($entry['answers']) && is_array($entry['answers'])): ?>
                                    <?php foreach ($entry['answers'] as $answer): ?>
                                        <li><?php echo $answer['name'] . ': ' . ($answer['isCorrect'] == 'true' ? 'Correct' : 'Incorrect'); ?></li>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </ul>
                        </details>
                    </td>
                </tr>
            <?php endforeach; ?>
        </table>
    </div>
</body>
</html>
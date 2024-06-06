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
        $data = $dbAdapter->db_query('SELECT * FROM answers');
    
        // Calculate the percentage of correct answers
        foreach ($data as $key => $value) {
            // Decode the JSON string into an array
            $answers = json_decode($value['answers'], true);
    
            $totalAnswers = count($answers);
            $correctAnswers = 0;
            foreach ($answers as $answer) {
                if ($answer['isCorrect'] == 'true') {
                    $correctAnswers++;
                }
            }
            $data[$key]['correctPercentage'] = ($correctAnswers / $totalAnswers) * 100;
            $data[$key]['answers'] = $answers; // Replace the JSON string with the decoded array
        }
    ?>

    <!-- Display the data in a table -->
    <table>
        <tr>
            <th>Time</th>
            <th>Name</th>
            <th>Correct Answers (%)</th>
        </tr>
        <?php foreach ($data as $entry): ?>
            <tr>
                <td><?php echo $entry['time']; ?></td>
                <td><?php echo $entry['name']; ?></td>
                <td><?php echo $entry['correctPercentage']; ?></td>
            </tr>
            <tr>
                <td colspan="2">
                    <details>
                        <summary>View Answers</summary>
                        <ul>
                            <?php foreach ($entry['answers'] as $answer): ?>
                                <li><?php echo $answer['name'] . ': ' . ($answer['isCorrect'] == 'true' ? 'Correct' : 'Incorrect'); ?></li>
                            <?php endforeach; ?>
                        </ul>
                    </details>
                </td>
            </tr>
        <?php endforeach; ?>
    </table>
</body>
</html>
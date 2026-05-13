<?php
namespace Quizflow\Core;

if (!defined('APP_NAME')) {
    die('Access denied');
}

include_once __DIR__ . '/config.php';

class QuizManager {
    private const DEFAULT_CATALOGS = [
        '1' => [
            'label' => 'Einfach',
            'multiplier' => 1.0,
        ],
        '2' => [
            'label' => 'Mittel',
            'multiplier' => 1.5,
        ],
        '3' => [
            'label' => 'Schwer',
            'multiplier' => 2.0,
        ],
    ];

    public function loadData() {
        if (!file_exists(QUIZFLOW_DATA)) {
            return $this->getDefaultData();
        }

        $json = file_get_contents(QUIZFLOW_DATA);
        $data = json_decode($json, true);

        if (!is_array($data)) {
            return $this->getDefaultData();
        }

        $data['intro_1'] = isset($data['intro_1']) && is_array($data['intro_1']) ? $data['intro_1'] : [];
        $data['intro_2'] = isset($data['intro_2']) && is_array($data['intro_2']) ? $data['intro_2'] : [];
        $data['outro'] = isset($data['outro']) && is_array($data['outro']) ? $data['outro'] : [];
        $data['catalogs'] = $this->normalizeCatalogs($data);

        foreach (array_keys($data['catalogs']) as $catalogId) {
            if (!isset($data[$catalogId]) || !is_array($data[$catalogId])) {
                $data[$catalogId] = [];
            }
        }

        return $data;
    }

    public function saveData($data) {
        $encoded = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($encoded === false) {
            return false;
        }

        $tmpPath = QUIZFLOW_DATA . '.tmp';
        $bytes = file_put_contents($tmpPath, $encoded . PHP_EOL, LOCK_EX);
        if ($bytes === false) {
            return false;
        }

        return rename($tmpPath, QUIZFLOW_DATA);
    }

    public function getCatalogs($data) {
        return $data['catalogs'] ?? [];
    }

    public function getCatalog($data, $catalogId) {
        $catalogs = $this->getCatalogs($data);
        return $catalogs[$catalogId] ?? null;
    }

    public function getQuestions($data, $catalogId) {
        return isset($data[$catalogId]) && is_array($data[$catalogId]) ? $data[$catalogId] : [];
    }

    public function saveCatalog($data, $catalogId, $label, $multiplier) {
        $data['catalogs'][$catalogId] = [
            'label' => $label,
            'multiplier' => round((float)$multiplier, 2),
        ];

        if (!isset($data[$catalogId]) || !is_array($data[$catalogId])) {
            $data[$catalogId] = [];
        }

        ksort($data['catalogs']);
        return $data;
    }

    public function deleteCatalog($data, $catalogId) {
        unset($data['catalogs'][$catalogId], $data[$catalogId]);
        return $data;
    }

    public function saveQuestion($data, $catalogId, $questionIndex, $questionText, $answers, $correctAnswer) {
        $question = [
            'question' => trim($questionText),
            'answers' => $this->normalizeAnswers($answers),
            'correctAnswer' => (int)$correctAnswer,
        ];

        if (!isset($data[$catalogId]) || !is_array($data[$catalogId])) {
            $data[$catalogId] = [];
        }

        if ($questionIndex === null || !isset($data[$catalogId][$questionIndex])) {
            $data[$catalogId][] = $question;
        } else {
            $data[$catalogId][$questionIndex] = $question;
        }

        $data[$catalogId] = array_values($data[$catalogId]);
        return $data;
    }

    public function deleteQuestion($data, $catalogId, $questionIndex) {
        if (isset($data[$catalogId][$questionIndex])) {
            unset($data[$catalogId][$questionIndex]);
            $data[$catalogId] = array_values($data[$catalogId]);
        }

        return $data;
    }

    public function validatePlayerName($name) {
        $sanitized = $this->sanitizePlayerName($name);
        $length = function_exists('mb_strlen') ? mb_strlen($sanitized) : strlen($sanitized);

        if ($length < 2) {
            return [
                'valid' => false,
                'name' => $sanitized,
                'error' => 'Bitte geben Sie mindestens 2 Zeichen fuer den Namen ein.',
            ];
        }

        if ($length > 40) {
            return [
                'valid' => false,
                'name' => $sanitized,
                'error' => 'Der Name darf maximal 40 Zeichen lang sein.',
            ];
        }

        return [
            'valid' => true,
            'name' => $sanitized,
            'error' => null,
        ];
    }

    public function sanitizePlayerName($name) {
        $name = trim(strip_tags((string)$name));
        $name = preg_replace('/\s+/u', ' ', $name);
        $sanitized = preg_replace('/[^\p{L}\p{N}\s._-]/u', '', $name);

        if (!is_string($sanitized)) {
            return '';
        }

        $sanitized = trim($sanitized);
        if (function_exists('mb_substr')) {
            return mb_substr($sanitized, 0, 40);
        }

        return substr($sanitized, 0, 40);
    }

    public function scoreAnswers($questions, $submittedAnswers, $multiplier) {
        $payload = [];
        $correctAnswers = 0;

        foreach ($questions as $index => $question) {
            $questionNumber = $index + 1;
            $inputName = 'q_' . $questionNumber;
            $submittedValue = isset($submittedAnswers[$inputName]) && ctype_digit((string)$submittedAnswers[$inputName])
                ? (int)$submittedAnswers[$inputName] - 1
                : null;
            $isCorrect = $submittedValue !== null && $submittedValue === (int)$question['correctAnswer'];

            if ($isCorrect) {
                $correctAnswers++;
            }

            $payload[] = [
                'name' => $inputName,
                'selected' => $submittedValue,
                'correctAnswer' => (int)$question['correctAnswer'],
                'isCorrect' => $isCorrect ? 'true' : 'false',
            ];
        }

        $totalQuestions = count($questions);
        return [
            'answers' => $payload,
            'correct_answers' => $correctAnswers,
            'total_questions' => $totalQuestions,
            'score' => round($correctAnswers * (float)$multiplier, 2),
        ];
    }

    private function normalizeCatalogs($data) {
        $catalogs = isset($data['catalogs']) && is_array($data['catalogs']) ? $data['catalogs'] : [];

        foreach ($data as $key => $value) {
            if (in_array($key, ['intro_1', 'intro_2', 'outro', 'catalogs'], true)) {
                continue;
            }

            if (is_array($value) && !isset($catalogs[$key])) {
                $default = self::DEFAULT_CATALOGS[$key] ?? [
                    'label' => 'Katalog ' . $key,
                    'multiplier' => 1.0,
                ];
                $catalogs[$key] = $default;
            }
        }

        foreach (self::DEFAULT_CATALOGS as $catalogId => $catalog) {
            if (!isset($catalogs[$catalogId]) && isset($data[$catalogId]) && is_array($data[$catalogId])) {
                $catalogs[$catalogId] = $catalog;
            }
        }

        foreach ($catalogs as $catalogId => $catalog) {
            $catalogs[$catalogId] = [
                'label' => trim((string)($catalog['label'] ?? ('Katalog ' . $catalogId))),
                'multiplier' => round((float)($catalog['multiplier'] ?? 1), 2),
            ];
        }

        ksort($catalogs);
        return $catalogs;
    }

    private function normalizeAnswers($answers) {
        $normalized = [];
        foreach ($answers as $answer) {
            $answer = trim((string)$answer);
            if ($answer !== '') {
                $normalized[] = $answer;
            }
        }

        return $normalized;
    }

    private function getDefaultData() {
        return [
            'intro_1' => [],
            'intro_2' => [],
            'outro' => [],
            'catalogs' => self::DEFAULT_CATALOGS,
            '1' => [],
            '2' => [],
            '3' => [],
        ];
    }
}
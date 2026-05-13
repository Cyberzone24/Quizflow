<?php
// check if APP_NAME is defined
if (!defined('APP_NAME')) {
    die('Access denied');
}
const DB_TYPE = 'pgsql';
const DB_SERVER = 'localhost';
const DB_PORT = '5432';
const DB_NAME = 'quizflow_local';
const DB_USER = 'quizflow_local';
const DB_PASSWORD = 'QuizflowLocal2026';
const QUIZFLOW_DATA = __DIR__ . '/../questions.json';
const QUIZFLOW_CODE = __DIR__ . '/../code.txt';
const ADMIN_PASSWORD_HASH = '$2y$12$I7XOI0Wv2E9yWtLaJ6.LieFR3PNGgRjgB4QW.T9ZYygVgk1tb5U7u';

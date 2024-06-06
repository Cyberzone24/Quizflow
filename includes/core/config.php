<?php
// check if APP_NAME is defined
if (!defined('APP_NAME')) {
    die('Access denied');
}
const DB_TYPE = 'pgsql';
const DB_SERVER = 'localhost';
const DB_PORT = '5432';
const DB_NAME = 'quizflow';
const DB_USER = 'username';
const DB_PASSWORD = 'password';
const QUIZFLOW_DATA = __DIR__ . '/../questions.json';
const QUIZFLOW_CODE = __DIR__ . '/../code.txt';
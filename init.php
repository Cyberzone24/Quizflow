<?php
    // use db_adapter
    const APP_NAME = 'Quizflow';
    include_once __DIR__ . '/includes/core/db_adapter.php';
    $dbAdapter = new \Quizflow\Core\DatabaseAdapter();

    $dbAdapter->db_init();
?>


<?php
require_once __DIR__ . '/../config/database.php';
$db = Database::getInstance();
try {
    $out = [
        'courses' => $db->fetchAll('SELECT * FROM courses LIMIT 3'),
        'instructors' => $db->fetchAll('SELECT * FROM instructors LIMIT 3')
    ];
    file_put_contents(__DIR__ . '/dump.json', json_encode($out, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    echo "Dumped to dump.json\n";
} catch (Exception $e) {
    echo $e->getMessage();
}

<?php
require_once __DIR__ . '/../config/database.php';
$db = Database::getInstance();
try {
    $res = $db->fetch('SELECT * FROM subjects LIMIT 1');
    if ($res) {
        echo implode(", ", array_keys($res));
        echo "\n---\n";
        print_r($res);
    } else {
        echo "No subjects found";
    }
} catch (Exception $e) {
    echo $e->getMessage();
}

<?php
require_once __DIR__ . '/../config/database.php';
$db = Database::getInstance();
try {
    echo "--- USERS SAMPLE ---\n";
    $res = $db->fetchAll("SELECT id, name, `group`, faculty, department FROM users WHERE role = 'student' LIMIT 5");
    print_r($res);
} catch (Exception $e) {
    echo $e->getMessage();
}

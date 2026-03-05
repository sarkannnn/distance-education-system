<?php
require_once 'd:/laragon/www/distant-tehsil/student/config/database.php';
$db = Database::getInstance();
try {
    $users = $db->fetchAll('SELECT * FROM users LIMIT 10');
    print_r($users);
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";

}

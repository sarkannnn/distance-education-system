<?php
require_once 'config/database.php';
$db = Database::getInstance();
$output = "";
$columns = $db->fetchAll("SHOW COLUMNS FROM live_alerts");
foreach ($columns as $col) {
    if ($col['Field'] == 'course_id') {
        $output .= "Field: {$col['Field']} | Null: {$col['Null']}\n";
    }
}
file_put_contents('verification.txt', $output);
?>
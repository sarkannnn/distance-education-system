<?php
require_once 'config/database.php';
$db = Database::getInstance();

echo "Starting database modification...\n";

try {
    // Make course_id nullable to support "All Students" alerts
    $db->query("ALTER TABLE live_alerts MODIFY course_id INT NULL");
    echo "SUCCESS: course_id is now nullable.\n";

    // Check structure again to be sure
    $columns = $db->fetchAll("SHOW COLUMNS FROM live_alerts");
    foreach ($columns as $col) {
        echo "Field: {$col['Field']} | Null: {$col['Null']}\n";
    }
} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
}
?>
<?php
require_once 'config/database.php';
$db = Database::getInstance();

echo "--- Instructors ---\n";
$instructors = $db->fetchAll("SELECT id, name, user_id, email FROM instructors LIMIT 10");
foreach ($instructors as $ins) {
    echo "ID: {$ins['id']} | Name: {$ins['name']} | UserID: {$ins['user_id']} | Email: {$ins['email']}\n";
}

echo "\n--- Recent Live Classes (last 10) ---\n";
$classes = $db->fetchAll("SELECT id, title, instructor_id, status, created_at FROM live_classes ORDER BY id DESC LIMIT 10");
foreach ($classes as $c) {
    echo "ID: {$c['id']} | Title: {$c['title']} | InsID: {$c['instructor_id']} | Status: {$c['status']} | Created: {$c['created_at']}\n";
}

echo "\n--- Recent Attendance ---\n";
$attendance = $db->fetchAll("SELECT live_class_id, role, COUNT(*) as count FROM live_attendance GROUP BY live_class_id, role ORDER BY live_class_id DESC LIMIT 10");
foreach ($attendance as $a) {
    echo "ClassID: {$a['live_class_id']} | Role: {$a['role']} | Count: {$a['count']}\n";
}
?>
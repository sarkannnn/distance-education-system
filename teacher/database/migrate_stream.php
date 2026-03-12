<?php
require_once __DIR__ . '/../config/database.php';
$db = Database::getInstance();

echo "Adding stream columns to live_classes...\n";

try {
    $db->query("ALTER TABLE live_classes ADD COLUMN stream_course_ids VARCHAR(500) DEFAULT NULL");
    echo "Added stream_course_ids column.\n";
} catch (Exception $e) {
    echo "stream_course_ids: " . $e->getMessage() . "\n";
}

try {
    $db->query("ALTER TABLE live_classes ADD COLUMN is_stream TINYINT(1) DEFAULT 0");
    echo "Added is_stream column.\n";
} catch (Exception $e) {
    echo "is_stream: " . $e->getMessage() . "\n";
}

echo "Migration complete.\n";

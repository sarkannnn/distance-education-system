<?php
require 'config/database.php';
$db = Database::getInstance();
print_r($db->fetchAll('SELECT id, title, lesson_type, duration_minutes, recording_path FROM live_classes ORDER BY id DESC LIMIT 5'));
print_r($db->fetchAll('SELECT * FROM archived_lessons ORDER BY id DESC LIMIT 5'));

<?php require "config/database.php"; $db = Database::getInstance(); print_r($db->fetchAll("SELECT id, course_id FROM live_classes ORDER BY id DESC LIMIT 10"));

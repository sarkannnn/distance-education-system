<?php
require_once 'includes/auth.php';
$db = Database::getInstance();
try {
    $db->query("DESCRIBE live_alerts");
    echo "exist";
} catch (Exception $e) {
    echo "not exist: " . $e->getMessage();
}

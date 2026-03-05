<?php
require_once 'includes/auth.php';
$db = Database::getInstance();
$table = 'courses';
$cols = $db->fetchAll("DESCRIBE $table");
foreach ($cols as $col) {
    echo $col['Field'] . "\n";
}
echo "----\n";
$table = 'live_classes';
$cols = $db->fetchAll("DESCRIBE $table");
foreach ($cols as $col) {
    echo $col['Field'] . "\n";
}
?>
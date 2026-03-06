<?php
require_once 'config/database.php';
$db = Database::getInstance();
$res = $db->fetch("SELECT NOW() as now");
echo "MySQL NOW(): " . $res['now'] . "\n";
echo "PHP date(): " . date('Y-m-d H:i:s') . "\n";
?>
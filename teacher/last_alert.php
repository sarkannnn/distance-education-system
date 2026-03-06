<?php
require_once 'config/database.php';
$db = Database::getInstance();
$res = $db->fetchAll("SELECT * FROM live_alerts ORDER BY id DESC LIMIT 1");
var_dump($res);
?>
<?php
require 'd:/laragon/www/distant-tehsil/config/database.php';
$db = Database::getInstance();
$res = $db->fetchAll('DESCRIBE courses');
foreach($res as $r) {
    echo $r['Field'] . "\n";
}
unlink(__FILE__);

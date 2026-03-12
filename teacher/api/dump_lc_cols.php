<?php
require_once __DIR__ . '/../config/database.php';
$db = Database::getInstance();
try {
    $res = $db->fetchAll('DESCRIBE live_classes');
    $cols = array_map(function($r){ return $r['Field']; }, $res);
    file_put_contents(__DIR__ . '/lc_cols.json', json_encode($cols));
    echo "Done";
} catch (Exception $e) {
    echo $e->getMessage();
}

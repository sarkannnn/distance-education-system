<?php
require_once __DIR__ . '/includes/auth.php';
$auth = new Auth();
$currentUser = $auth->getCurrentUser();

header('Content-Type: text/plain');

echo "CURRENT USER DATA:\n";
print_r($currentUser);

echo "\nSESSION DATA:\n";
print_r($_SESSION);

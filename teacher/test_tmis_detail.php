<?php
require_once 'includes/auth.php';
require_once 'includes/tmis_api.php';

$auth = new Auth();
$user = $auth->getCurrentUser();
if (!$user) {
    die("Not logged in");
}

$token = TmisApi::getToken();
if (!$token) {
    die("No TMIS token");
}

$id = isset($_GET['id']) ? (int) $_GET['id'] : 9719;
echo "<pre>";
echo "Testing ID: $id\n";
$res = TmisApi::getSubjectDetails($token, $id);
print_r($res);
echo "</pre>";

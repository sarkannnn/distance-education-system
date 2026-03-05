<?php
require_once 'd:/laragon/www/distant-tehsil/teacher/includes/auth.php';
require_once 'd:/laragon/www/distant-tehsil/teacher/includes/tmis_api.php';

$auth = new Auth();
$user = $auth->getCurrentUser();
if (!$user) {
    die("Not logged in");
}

$token = TmisApi::getToken();
if (!$token) {
    die("No TMIS token");
}

echo "<pre>";
$res = TmisApi::getAnalyticsCourseStats($token);
print_r($res);
echo "</pre>";

<?php
require_once 'includes/auth.php';
require_once 'includes/tmis_api.php';
$token = TmisApi::getToken();
if ($token) {
    $res = TmisApi::getActivities($token);
    echo json_encode($res, JSON_PRETTY_PRINT);
} else {
    echo "No token";
}
?>
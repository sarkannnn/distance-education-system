<?php
/**
 * Distant Təhsil - Tələbə Çıxış / Portala keçid
 */
require_once 'includes/auth.php';

$auth = new Auth();

if (isset($_GET['action']) && $_GET['action'] === 'exit') {
    $auth->exitToPortal();
    header('Location: https://tmis.ndu.edu.az/login');
    exit;
}

$auth->logout();
header('Location: login.php');
exit;

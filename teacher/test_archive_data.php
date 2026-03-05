<?php
require 'includes/auth.php';
require 'includes/helpers.php';
$token = TmisApi::getToken();
$res = TmisApi::getArchive($token);
print_r($res);

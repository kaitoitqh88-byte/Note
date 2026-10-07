<?php
// AJAX VPS Login Checker
header('Content-Type: application/json');
if (!isset($_POST['ip'], $_POST['username'], $_POST['password'])) {
    echo json_encode(['success' => false, 'error' => 'Missing parameters']);
    exit;
}
include_once __DIR__ . '/vps_login_checker.php';
$result = checkVpsLogin($_POST['ip'], $_POST['username'], $_POST['password']);
echo json_encode($result);

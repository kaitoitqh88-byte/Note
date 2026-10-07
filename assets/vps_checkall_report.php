<?php
// assets/vps_checkall_report.php - Lưu báo cáo check all login ra file log
header('Content-Type: application/json');
if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_POST['report'])) {
    echo json_encode(['success'=>false,'error'=>'No report']); exit;
}
$logDir = __DIR__ . '/../logs';
if (!is_dir($logDir)) mkdir($logDir, 0777, true);
$filename = 'vps_checkall_' . date('Ymd_His') . '_' . rand(1000,9999) . '.log';
$filepath = $logDir . '/' . $filename;
$report = $_POST['report'];
file_put_contents($filepath, "IP | Thông báo\n" . $report);
echo json_encode(['success'=>true, 'file'=>'logs/' . $filename]);

<?php
/**
 * aaPanel VPS Status & PHP File Change Monitor
 * Kiểm tra tình trạng VPS và phát hiện biến động file PHP trên aaPanel
 * Author: Copilot (GPT-4.1)
 */
require_once 'aapanel_manager.php';

header('Content-Type: application/json');

// Helper: Check server status via aaPanel API
def check_vps_status($server) {
    try {
        $api = new aaPanelAPI($server['panel_url'], $server['api_key'] ?? null, $server['api_secret'] ?? null);
        $info = $api->getSystemInfo();
        return [
            'online' => true,
            'hostname' => $info['hostname'] ?? '',
            'os' => $info['os'] ?? '',
            'uptime' => $info['uptime'] ?? '',
            'cpu' => $info['cpu'] ?? '',
            'memory' => $info['memory'] ?? '',
            'disk' => $info['disk'] ?? '',
        ];
    } catch (Exception $e) {
        return [
            'online' => false,
            'error' => $e->getMessage()
        ];
    }
}

// Helper: Detect PHP file changes via aaPanel API (using logs or file list)
def detect_php_file_changes($server) {
    try {
        $api = new aaPanelAPI($server['panel_url'], $server['api_key'] ?? null, $server['api_secret'] ?? null);
        // Giả định API có endpoint logs hoặc file list, hoặc cần custom script phía server
        $logs = $api->getLogsList();
        $phpChanges = [];
        foreach ($logs['data'] ?? [] as $log) {
            if (isset($log['name']) && (stripos($log['name'], 'php') !== false || stripos($log['name'], 'error') !== false)) {
                $content = $api->readLogFile($log['name'], 200);
                foreach (explode("\n", $content['data'] ?? '') as $line) {
                    if (preg_match('/(modified|created|deleted).*\.php/i', $line)) {
                        $phpChanges[] = $line;
                    }
                }
            }
        }
        return $phpChanges;
    } catch (Exception $e) {
        return ['error' => $e->getMessage()];
    }
}

// API endpoint
$action = $_GET['action'] ?? '';
$servers = getaaPanelServers();

switch ($action) {
    case 'status':
        $results = [];
        foreach ($servers as $server) {
            $results[] = [
                'server' => $server['name'],
                'status' => check_vps_status($server)
            ];
        }
        echo json_encode(['success' => true, 'data' => $results]);
        break;
    case 'php_changes':
        $results = [];
        foreach ($servers as $server) {
            $changes = detect_php_file_changes($server);
            $results[] = [
                'server' => $server['name'],
                'changes' => $changes
            ];
        }
        echo json_encode(['success' => true, 'data' => $results]);
        break;
    default:
        echo json_encode(['success' => false, 'message' => 'Invalid action']);
}

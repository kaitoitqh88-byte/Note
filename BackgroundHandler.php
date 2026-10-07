<?php
/**
 * Background Handler
 * Xử lý Background Loader - auto-loading data for performance
 */

/**
 * Xử lý Background Loader - auto-loading data for performance
 */
function handleBackgroundLoader($cloudflare) {
    require_once 'BackgroundLoader.php';
    
    $operation = $_POST['operation'] ?? $_GET['operation'] ?? 'status';
    $force = ($_POST['force'] ?? $_GET['force'] ?? 'false') === 'true';
    
    $loader = new BackgroundLoader();
    
    try {
        switch ($operation) {
            case 'run':
                if ($_SERVER['REQUEST_METHOD'] !== 'POST' && !$force) {
                    http_response_code(405);
                    echo json_encode(['error' => 'Use POST to run background loader']);
                    return;
                }
                
                $result = $loader->run($force);
                echo json_encode([
                    'success' => $result['success'],
                    'operation' => 'run',
                    'result' => $result
                ]);
                break;
                
            case 'config':
                if ($_SERVER['REQUEST_METHOD'] === 'POST') {
                    // Update configuration
                    $newConfig = json_decode(file_get_contents('php://input'), true);
                    if ($newConfig) {
                        $loader->updateConfig($newConfig);
                        echo json_encode([
                            'success' => true,
                            'operation' => 'config_update',
                            'message' => 'Configuration updated'
                        ]);
                    } else {
                        echo json_encode([
                            'success' => false,
                            'operation' => 'config_update',
                            'error' => 'Invalid configuration data'
                        ]);
                    }
                } else {
                    // Get current configuration
                    $status = $loader->getStatus();
                    echo json_encode([
                        'success' => true,
                        'operation' => 'config_get',
                        'config' => $status['config']
                    ]);
                }
                break;
                
            case 'logs':
                $lines = (int)($_GET['lines'] ?? 50);
                $logs = $loader->getLogs($lines);
                echo json_encode([
                    'success' => true,
                    'operation' => 'logs',
                    'logs' => $logs,
                    'total_lines' => count($logs)
                ]);
                break;
                
            case 'status':
            default:
                $status = $loader->getStatus();
                echo json_encode([
                    'success' => true,
                    'operation' => 'status',
                    'status' => $status
                ]);
                break;
        }
        
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'operation' => $operation,
            'error' => $e->getMessage()
        ]);
    }
}
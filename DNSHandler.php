<?php
/**
 * DNS Handler  
 * Xử lý DNS Records
 */

/**
 * Xử lý DNS Records
 */
function handleDNS($cloudflare) {
    $method = $_SERVER['REQUEST_METHOD'];
    $zoneId = $_GET['zone_id'] ?? $_POST['zone_id'] ?? null;
    
    if (!$zoneId) {
        http_response_code(400);
        echo json_encode(['error' => 'Zone ID is required']);
        return;
    }
    
    switch ($method) {
        case 'GET':
            $type = $_GET['type'] ?? null;
            $name = $_GET['name'] ?? null;
            $result = $cloudflare->listDNSRecords($zoneId, $type, $name);
            break;
            
        case 'POST':
            $input = json_decode(file_get_contents('php://input'), true);
            $result = $cloudflare->createDNSRecord(
                $zoneId,
                $input['type'],
                $input['name'],
                $input['content'],
                $input['ttl'] ?? 1
            );
            break;
            
        case 'PUT':
            $recordId = $_GET['record_id'] ?? null;
            if (!$recordId) {
                http_response_code(400);
                echo json_encode(['error' => 'Record ID is required']);
                return;
            }
            
            $input = json_decode(file_get_contents('php://input'), true);
            $result = $cloudflare->updateDNSRecord(
                $zoneId,
                $recordId,
                $input['type'],
                $input['name'],
                $input['content'],
                $input['ttl'] ?? 1
            );
            break;
            
        case 'DELETE':
            $recordId = $_GET['record_id'] ?? null;
            if (!$recordId) {
                http_response_code(400);
                echo json_encode(['error' => 'Record ID is required']);
                return;
            }
            
            $result = $cloudflare->deleteDNSRecord($zoneId, $recordId);
            break;
            
        default:
            http_response_code(405);
            echo json_encode(['error' => 'Method not allowed']);
            return;
    }
    
    echo json_encode([
        'success' => true,
        'data' => $result
    ]);
}
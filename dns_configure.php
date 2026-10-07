<?php
/**
 * DNS Records Configuration Tool
 * Cấu hình DNS Records trên Cloudflare (Create, Update, Delete)
 */

require_once 'config.php';
require_once 'CloudflareAPI.php';

// Initialize Cloudflare API
$cloudflare = new CloudflareAPI();

// Handle AJAX requests
if (isset($_POST['action'])) {
    header('Content-Type: application/json');
    
    try {
        switch ($_POST['action']) {
            case 'get_zones':
                $zones = $cloudflare->listZones();
                if ($zones['success']) {
                    echo json_encode([
                        'success' => true,
                        'zones' => $zones['result'],
                        'total' => count($zones['result'])
                    ]);
                } else {
                    echo json_encode([
                        'success' => false,
                        'error' => 'Failed to fetch zones: ' . ($zones['errors'][0]['message'] ?? 'Unknown error')
                    ]);
                }
                break;
                
            case 'get_dns_records':
                $zoneId = $_POST['zone_id'] ?? '';
                $recordType = $_POST['record_type'] ?? '';
                $recordName = $_POST['record_name'] ?? '';
                
                if (!$zoneId) {
                    echo json_encode(['success' => false, 'error' => 'Zone ID is required']);
                    break;
                }
                
                $records = $cloudflare->listDNSRecords($zoneId, 
                    $recordType && $recordType !== 'all' ? strtoupper($recordType) : null, 
                    $recordName ?: null
                );
                
                if ($records['success']) {
                    echo json_encode([
                        'success' => true,
                        'records' => $records['result'],
                        'total' => count($records['result'])
                    ]);
                } else {
                    echo json_encode([
                        'success' => false,
                        'error' => 'Failed to fetch DNS records: ' . ($records['errors'][0]['message'] ?? 'Unknown error')
                    ]);
                }
                break;
                
            case 'create_dns_record':
                $zoneId = $_POST['zone_id'] ?? '';
                $type = strtoupper($_POST['type'] ?? '');
                $name = $_POST['name'] ?? '';
                $content = $_POST['content'] ?? '';
                $ttl = intval($_POST['ttl'] ?? 1);
                $priority = $_POST['priority'] ?? null;
                $proxied = isset($_POST['proxied']) && $_POST['proxied'] === 'true';
                
                if (!$zoneId || !$type || !$name || !$content) {
                    echo json_encode(['success' => false, 'error' => 'Missing required fields']);
                    break;
                }
                
                // Prepare data for API call
                $recordData = [
                    'type' => $type,
                    'name' => $name,
                    'content' => $content,
                    'ttl' => $ttl
                ];
                
                // Add priority for MX and SRV records
                if ($priority !== null && $priority !== '' && in_array($type, ['MX', 'SRV'])) {
                    $recordData['priority'] = intval($priority);
                }
                
                // Add proxy setting for supported record types
                if (in_array($type, ['A', 'AAAA', 'CNAME'])) {
                    $recordData['proxied'] = $proxied;
                }
                
                $result = $cloudflare->createDNSRecordAdvanced($zoneId, $recordData);
                
                if ($result['success']) {
                    echo json_encode([
                        'success' => true,
                        'message' => 'DNS record created successfully',
                        'record' => $result['result']
                    ]);
                } else {
                    echo json_encode([
                        'success' => false,
                        'error' => 'Failed to create DNS record: ' . ($result['errors'][0]['message'] ?? 'Unknown error')
                    ]);
                }
                break;
                
            case 'update_dns_record':
                $zoneId = $_POST['zone_id'] ?? '';
                $recordId = $_POST['record_id'] ?? '';
                $type = strtoupper($_POST['type'] ?? '');
                $name = $_POST['name'] ?? '';
                $content = $_POST['content'] ?? '';
                $ttl = intval($_POST['ttl'] ?? 1);
                $priority = $_POST['priority'] ?? null;
                $proxied = isset($_POST['proxied']) && $_POST['proxied'] === 'true';
                
                if (!$zoneId || !$recordId || !$type || !$name || !$content) {
                    echo json_encode(['success' => false, 'error' => 'Missing required fields']);
                    break;
                }
                
                // Prepare update data
                $updateData = [
                    'type' => $type,
                    'name' => $name,
                    'content' => $content,
                    'ttl' => $ttl
                ];
                
                // Add priority for MX and SRV records
                if ($priority !== null && $priority !== '' && in_array($type, ['MX', 'SRV'])) {
                    $updateData['priority'] = intval($priority);
                }
                
                // Add proxy setting for supported record types
                if (in_array($type, ['A', 'AAAA', 'CNAME'])) {
                    $updateData['proxied'] = $proxied;
                }
                
                $result = $cloudflare->updateDNSRecordAdvanced($zoneId, $recordId, $updateData);
                
                if ($result['success']) {
                    echo json_encode([
                        'success' => true,
                        'message' => 'DNS record updated successfully',
                        'record' => $result['result']
                    ]);
                } else {
                    echo json_encode([
                        'success' => false,
                        'error' => 'Failed to update DNS record: ' . ($result['errors'][0]['message'] ?? 'Unknown error')
                    ]);
                }
                break;
                
            case 'delete_dns_record':
                $zoneId = $_POST['zone_id'] ?? '';
                $recordId = $_POST['record_id'] ?? '';
                
                if (!$zoneId || !$recordId) {
                    echo json_encode(['success' => false, 'error' => 'Zone ID and Record ID are required']);
                    break;
                }
                
                $result = $cloudflare->deleteDNSRecord($zoneId, $recordId);
                
                if ($result['success']) {
                    echo json_encode([
                        'success' => true,
                        'message' => 'DNS record deleted successfully'
                    ]);
                } else {
                    echo json_encode([
                        'success' => false,
                        'error' => 'Failed to delete DNS record: ' . ($result['errors'][0]['message'] ?? 'Unknown error')
                    ]);
                }
                break;
                
            case 'batch_delete':
                $zoneId = $_POST['zone_id'] ?? '';
                $recordIds = $_POST['record_ids'] ?? [];
                
                if (!$zoneId || empty($recordIds)) {
                    echo json_encode(['success' => false, 'error' => 'Zone ID and Record IDs are required']);
                    break;
                }
                
                $successful = 0;
                $failed = 0;
                $errors = [];
                
                foreach ($recordIds as $recordId) {
                    $result = $cloudflare->deleteDNSRecord($zoneId, $recordId);
                    if ($result['success']) {
                        $successful++;
                    } else {
                        $failed++;
                        $errors[] = $result['errors'][0]['message'] ?? 'Unknown error';
                    }
                }
                
                echo json_encode([
                    'success' => $failed === 0,
                    'message' => "Batch delete completed: {$successful} successful, {$failed} failed",
                    'details' => [
                        'successful' => $successful,
                        'failed' => $failed,
                        'errors' => $errors
                    ]
                ]);
                break;
                
            case 'validate_record':
                $type = strtoupper($_POST['type'] ?? '');
                $content = $_POST['content'] ?? '';
                
                $validation = validateDNSRecord($type, $content);
                echo json_encode($validation);
                break;
                
            default:
                echo json_encode(['success' => false, 'error' => 'Invalid action']);
        }
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
    exit;
}

function validateDNSRecord($type, $content) {
    $errors = [];
    
    switch ($type) {
        case 'A':
            if (!filter_var($content, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
                $errors[] = 'Invalid IPv4 address';
            }
            break;
            
        case 'AAAA':
            if (!filter_var($content, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) {
                $errors[] = 'Invalid IPv6 address';
            }
            break;
            
        case 'CNAME':
            if (!preg_match('/^[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}$/', $content) && $content !== '@') {
                $errors[] = 'Invalid domain name for CNAME';
            }
            break;
            
        case 'MX':
            if (!preg_match('/^[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}$/', $content)) {
                $errors[] = 'Invalid mail server domain';
            }
            break;
            
        case 'TXT':
            if (strlen($content) > 255) {
                $errors[] = 'TXT record too long (max 255 characters)';
            }
            break;
    }
    
    return [
        'valid' => empty($errors),
        'errors' => $errors
    ];
}

?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>DNS Records Configuration - Cloudflare</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        /* Enhanced DNS Header with Animation */
        .dns-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border-radius: 15px;
            padding: 3rem 2rem;
            margin-bottom: 2rem;
            position: relative;
            overflow: hidden;
            box-shadow: 0 10px 30px rgba(102, 126, 234, 0.3);
        }
        
        .dns-header::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255,255,255,0.2), transparent);
            animation: shimmer 3s infinite;
        }
        
        @keyframes shimmer {
            0% { left: -100%; }
            100% { left: 100%; }
        }
        
        .dns-header h1 {
            font-size: 2.5rem;
            font-weight: 700;
            text-shadow: 0 2px 4px rgba(0,0,0,0.3);
            margin-bottom: 0.5rem;
        }
        
        .dns-header h1 i {
            animation: rotate 4s linear infinite;
            margin-right: 1rem;
        }
        
        @keyframes rotate {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
        
        .dns-header p {
            font-size: 1.2rem;
            opacity: 0.9;
            font-weight: 300;
        }
        
        /* Enhanced Record Cards */
        .record-card {
            border: 1px solid #e9ecef;
            border-radius: 12px;
            margin-bottom: 1rem;
            transition: all 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275);
            background: linear-gradient(145deg, #ffffff, #f8f9fa);
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            position: relative;
            overflow: hidden;
        }
        
        .record-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 4px;
            background: linear-gradient(90deg, #667eea, #764ba2);
            transition: left 0.5s ease;
        }
        
        .record-card:hover::before {
            left: 0;
        }
        
        .record-card:hover {
            box-shadow: 0 8px 25px rgba(102, 126, 234, 0.15);
            border-color: #667eea;
            transform: translateY(-5px);
        }
        
        .record-card.selected {
            border-color: #667eea;
            background: linear-gradient(145deg, #f8f9ff, #e6ebff);
            box-shadow: 0 8px 25px rgba(102, 126, 234, 0.2);
        }
        
        /* Enhanced Record Type Badges */
        .record-type-badge {
            font-weight: bold;
            min-width: 70px;
            text-align: center;
            border-radius: 20px;
            padding: 0.5rem 1rem;
            font-size: 0.9rem;
            letter-spacing: 0.5px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
            border: none;
        }
        
        .record-type-A { background: linear-gradient(135deg, #28a745, #20c997); }
        .record-type-AAAA { background: linear-gradient(135deg, #17a2b8, #007bff); }
        .record-type-CNAME { background: linear-gradient(135deg, #ffc107, #fd7e14); color: #212529; }
        .record-type-MX { background: linear-gradient(135deg, #dc3545, #e83e8c); }
        .record-type-TXT { background: linear-gradient(135deg, #6f42c1, #495057); }
        .record-type-NS { background: linear-gradient(135deg, #fd7e14, #dc3545); }
        .record-type-SRV { background: linear-gradient(135deg, #20c997, #28a745); }
        .record-type-CAA { background: linear-gradient(135deg, #495057, #6c757d); }
        .record-type-PTR { background: linear-gradient(135deg, #e83e8c, #dc3545); }
        
        /* Enhanced Zone Cards */
        .zone-card {
            cursor: pointer;
            transition: all 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275);
            border-radius: 12px;
            border: 1px solid #e9ecef;
            background: linear-gradient(145deg, #ffffff, #f8f9fa);
            position: relative;
            overflow: hidden;
        }
        
        .zone-card::after {
            content: '';
            position: absolute;
            top: 0;
            right: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(102, 126, 234, 0.1), transparent);
            transition: right 0.6s ease;
        }
        
        .zone-card:hover::after {
            right: 100%;
        }
        
        .zone-card:hover {
            transform: translateY(-8px) scale(1.02);
            box-shadow: 0 15px 35px rgba(102, 126, 234, 0.2);
            border-color: #667eea;
        }
        
        .zone-card.selected {
            border-color: #667eea;
            background: linear-gradient(145deg, #f8f9ff, #e6ebff);
            box-shadow: 0 10px 30px rgba(102, 126, 234, 0.25);
            transform: scale(1.05);
        }
        
        /* Enhanced Action Buttons */
        .action-buttons {
            opacity: 0;
            transition: opacity 0.4s ease, transform 0.4s ease;
            transform: translateY(10px);
        }
        
        .record-card:hover .action-buttons {
            opacity: 1;
            transform: translateY(0);
        }
        
        /* Enhanced Loading Overlay */
        .loading-overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: linear-gradient(135deg, rgba(102, 126, 234, 0.9), rgba(118, 75, 162, 0.9));
            display: none;
            align-items: center;
            justify-content: center;
            z-index: 9999;
            backdrop-filter: blur(10px);
        }
        
        /* Enhanced Form Sections */
        .form-section {
            background: linear-gradient(145deg, #ffffff, #f8f9fa);
            border-radius: 15px;
            padding: 2rem;
            margin-bottom: 2rem;
            border: 1px solid #e9ecef;
            box-shadow: 0 5px 15px rgba(0,0,0,0.08);
            position: relative;
            overflow: hidden;
        }
        
        .form-section::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 4px;
            background: linear-gradient(90deg, #667eea, #764ba2);
        }
        
        /* Enhanced Cards */
        .card {
            border-radius: 15px;
            border: none;
            box-shadow: 0 5px 20px rgba(0,0,0,0.08);
            transition: all 0.3s ease;
            background: linear-gradient(145deg, #ffffff, #f8f9fa);
        }
        
        .card:hover {
            box-shadow: 0 10px 30px rgba(0,0,0,0.15);
            transform: translateY(-2px);
        }
        
        .card-header {
            background: linear-gradient(135deg, #667eea, #764ba2);
            color: white;
            border-radius: 15px 15px 0 0 !important;
            border-bottom: none;
            font-weight: 600;
            text-shadow: 0 1px 2px rgba(0,0,0,0.2);
        }
        
        /* Enhanced Buttons */
        .btn {
            border-radius: 25px;
            padding: 0.7rem 1.5rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            transition: all 0.3s ease;
            border: none;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
        }
        
        .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(0,0,0,0.2);
        }
        
        .btn-primary {
            background: linear-gradient(135deg, #667eea, #764ba2);
        }
        
        .btn-success {
            background: linear-gradient(135deg, #28a745, #20c997);
        }
        
        .btn-danger {
            background: linear-gradient(135deg, #dc3545, #e83e8c);
        }
        
        .btn-warning {
            background: linear-gradient(135deg, #ffc107, #fd7e14);
            color: #212529;
        }
        
        .btn-light {
            background: linear-gradient(145deg, #ffffff, #f8f9fa);
            color: #667eea;
            border: 2px solid rgba(102, 126, 234, 0.2);
        }
        
        .btn-light:hover {
            background: linear-gradient(135deg, #667eea, #764ba2);
            color: white;
        }
        
        /* Enhanced Input Fields */
        .form-control,
        .form-select {
            border-radius: 12px;
            border: 2px solid #e9ecef;
            padding: 0.8rem 1rem;
            transition: all 0.3s ease;
            background: linear-gradient(145deg, #ffffff, #f8f9fa);
        }
        
        .form-control:focus,
        .form-select:focus {
            border-color: #667eea;
            box-shadow: 0 0 0 0.2rem rgba(102, 126, 234, 0.25);
            background: white;
        }
        
        /* Enhanced Validation */
        .validation-error {
            border-color: #dc3545 !important;
            animation: shake 0.5s ease-in-out;
        }
        
        @keyframes shake {
            0%, 100% { transform: translateX(0); }
            25% { transform: translateX(-5px); }
            75% { transform: translateX(5px); }
        }
        
        .validation-message {
            color: #dc3545;
            font-size: 0.875em;
            animation: fadeIn 0.3s ease;
        }
        
        @keyframes fadeIn {
            0% { opacity: 0; transform: translateY(-10px); }
            100% { opacity: 1; transform: translateY(0); }
        }
        
        /* Enhanced Batch Actions */
        .batch-actions {
            background: linear-gradient(135deg, #fff3cd, #ffeaa7);
            border: 2px solid #ffc107;
            border-radius: 12px;
            padding: 1.5rem;
            margin-bottom: 2rem;
            display: none;
            box-shadow: 0 4px 15px rgba(255, 193, 7, 0.2);
        }
        
        /* Responsive Enhancements */
        @media (max-width: 768px) {
            .dns-header {
                padding: 2rem 1.5rem;
            }
            
            .dns-header h1 {
                font-size: 2rem;
            }
            
            .record-card:hover {
                transform: translateY(-2px);
            }
            
            .zone-card:hover {
                transform: translateY(-4px) scale(1.01);
            }
        }
        
        /* Custom Scrollbar */
        ::-webkit-scrollbar {
            width: 8px;
        }
        
        ::-webkit-scrollbar-track {
            background: #f1f1f1;
            border-radius: 10px;
        }
        
        ::-webkit-scrollbar-thumb {
            background: linear-gradient(135deg, #667eea, #764ba2);
            border-radius: 10px;
        }
        
        ::-webkit-scrollbar-thumb:hover {
            background: linear-gradient(135deg, #5a6fd8, #6a4c93);
        }
        
        /* Enhanced Main Navigation for DNS Configure Page */
        .navbar-main {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%) !important;
            box-shadow: 0 4px 20px rgba(102, 126, 234, 0.3);
            border-bottom: 3px solid rgba(255, 255, 255, 0.1);
        }
        
        .navbar-main .navbar-brand {
            font-weight: 800;
            font-size: 1.5rem;
            text-shadow: 0 2px 4px rgba(0,0,0,0.3);
        }
        
        .navbar-main .nav-link {
            color: rgba(255, 255, 255, 0.9) !important;
            font-weight: 600;
            padding: 0.8rem 1.2rem !important;
            margin: 0 0.2rem;
            border-radius: 25px;
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
        }
        
        .navbar-main .nav-link::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255,255,255,0.2), transparent);
            transition: left 0.5s ease;
        }
        
        .navbar-main .nav-link:hover::before {
            left: 100%;
        }
        
        .navbar-main .nav-link:hover {
            color: white !important;
            background: rgba(255, 255, 255, 0.15);
            transform: translateY(-2px);
            box-shadow: 0 4px 15px rgba(0,0,0,0.2);
        }
        
        .navbar-main .nav-link.active {
            color: #667eea !important;
            background: rgba(255, 255, 255, 0.95);
            font-weight: 700;
            box-shadow: 0 4px 15px rgba(0,0,0,0.2);
        }
        
        .navbar-main .nav-link.active i {
            animation: pulse 2s infinite;
        }
        
        @keyframes pulse {
            0% { transform: scale(1); }
            50% { transform: scale(1.1); }
            100% { transform: scale(1); }
        }
        
        .navbar-main .dropdown-menu {
            background: linear-gradient(145deg, #ffffff, #f8f9fa);
            border: none;
            border-radius: 15px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.15);
            padding: 0.5rem;
        }
        
        .navbar-main .dropdown-item {
            border-radius: 10px;
            margin: 0.2rem 0;
            padding: 0.7rem 1rem;
            transition: all 0.3s ease;
        }
        
        .navbar-main .dropdown-item:hover {
            background: linear-gradient(135deg, #667eea, #764ba2);
            color: white;
            transform: translateX(5px);
        }
        
        .navbar-toggler {
            border: 2px solid rgba(255, 255, 255, 0.3);
            border-radius: 10px;
        }
        
        .navbar-toggler:focus {
            box-shadow: 0 0 0 0.2rem rgba(255, 255, 255, 0.25);
        }
        
        .navbar-toggler-icon {
            background-image: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 30 30'%3e%3cpath stroke='rgba%28255, 255, 255, 0.8%29' stroke-linecap='round' stroke-miterlimit='10' stroke-width='2' d='M4 7h22M4 15h22M4 23h22'/%3e%3c/svg%3e");
        }
        
        /* Responsive Navigation */
        @media (max-width: 991px) {
            .navbar-main .navbar-collapse {
                background: rgba(255, 255, 255, 0.95);
                border-radius: 15px;
                margin-top: 1rem;
                padding: 1rem;
                box-shadow: 0 10px 30px rgba(0,0,0,0.15);
            }
            
            .navbar-main .nav-link {
                color: #667eea !important;
                margin: 0.2rem 0;
            }
            
            .navbar-main .nav-link:hover {
                background: linear-gradient(135deg, #667eea, #764ba2);
                color: white !important;
                transform: translateX(10px);
            }
        }
    </style>
</head>
<body>
    <?php 
    $currentPage = 'dns-configure'; 
    include 'includes/navigation.php'; 
    ?>

    <!-- Loading Overlay -->
    <div class="loading-overlay" id="loading-overlay">
        <div class="text-center text-white">
            <div class="spinner-border mb-3" style="width: 3rem; height: 3rem;"></div>
            <h5>Processing DNS changes...</h5>
        </div>
    </div>

    <div class="container-fluid mt-4">
        <!-- Header -->
        <div class="dns-header">
            <div class="row align-items-center">
                <div class="col">
                    <h1><i class="fas fa-cogs"></i> DNS Records Configuration</h1>
                    <p class="mb-0">Quản lý DNS Records trên Cloudflare - Create, Update, Delete</p>
                </div>
                <div class="col-auto">
                    <button class="btn btn-light btn-lg" onclick="showAddRecordModal()">
                        <i class="fas fa-plus"></i> Add Record
                    </button>
                </div>
            </div>
        </div>

        <div class="row">
            <!-- Zone Selection Sidebar -->
            <div class="col-lg-3">
                <div class="card">
                    <div class="card-header">
                        <h6 class="mb-0">
                            <i class="fas fa-cloud"></i> Cloudflare Zones
                            <button class="btn btn-sm btn-primary float-end" onclick="loadZones()">
                                <i class="fas fa-sync"></i>
                            </button>
                        </h6>
                    </div>
                    <div class="card-body p-0">
                        <div id="zones-list">
                            <div class="text-center p-3">
                                <div class="spinner-border spinner-border-sm"></div>
                                <p class="mt-2 mb-0">Loading zones...</p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Quick Stats -->
                <div class="card mt-3" id="stats-card" style="display: none;">
                    <div class="card-header">
                        <h6 class="mb-0"><i class="fas fa-chart-bar"></i> Zone Statistics</h6>
                    </div>
                    <div class="card-body">
                        <div class="row text-center">
                            <div class="col-6">
                                <h4 class="text-primary mb-0" id="total-records">0</h4>
                                <small>Total Records</small>
                            </div>
                            <div class="col-6">
                                <h4 class="text-success mb-0" id="selected-count">0</h4>
                                <small>Selected</small>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- DNS Records Management -->
            <div class="col-lg-9">
                <!-- Search and Filter Controls -->
                <div class="form-section">
                    <div class="row">
                        <div class="col-md-3">
                            <label class="form-label">Record Type</label>
                            <select class="form-select" id="record-type-filter">
                                <option value="all">All Types</option>
                                <option value="A">A (IPv4)</option>
                                <option value="AAAA">AAAA (IPv6)</option>
                                <option value="CNAME">CNAME (Alias)</option>
                                <option value="MX">MX (Mail Exchange)</option>
                                <option value="TXT">TXT (Text)</option>
                                <option value="NS">NS (Name Server)</option>
                                <option value="SRV">SRV (Service)</option>
                                <option value="CAA">CAA (Certificate Authority)</option>
                                <option value="PTR">PTR (Reverse DNS)</option>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Search by Name</label>
                            <input type="text" class="form-control" id="record-name-filter" 
                                   placeholder="e.g., www, mail, api">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">&nbsp;</label>
                            <div class="d-grid">
                                <button class="btn btn-primary" onclick="loadDNSRecords()">
                                    <i class="fas fa-search"></i> Search
                                </button>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">&nbsp;</label>
                            <div class="d-grid">
                                <label class="btn btn-outline-secondary">
                                    <input type="checkbox" id="select-all" onchange="toggleSelectAll()"> Select All
                                </label>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Batch Actions -->
                <div class="batch-actions" id="batch-actions">
                    <div class="d-flex justify-content-between align-items-center">
                        <span><strong id="selected-count-text">0</strong> records selected</span>
                        <div class="btn-group">
                            <button class="btn btn-danger btn-sm" onclick="batchDelete()">
                                <i class="fas fa-trash"></i> Delete Selected
                            </button>
                            <button class="btn btn-secondary btn-sm" onclick="clearSelection()">
                                <i class="fas fa-times"></i> Clear Selection
                            </button>
                        </div>
                    </div>
                </div>

                <!-- DNS Records Display -->
                <div class="card">
                    <div class="card-header">
                        <div class="d-flex justify-content-between align-items-center">
                            <h6 class="mb-0">
                                <i class="fas fa-list-ul"></i> DNS Records
                                <span class="badge bg-secondary" id="displayed-count">0</span>
                            </h6>
                            <button class="btn btn-success btn-sm" onclick="showAddRecordModal()">
                                <i class="fas fa-plus"></i> Add New Record
                            </button>
                        </div>
                    </div>
                    <div class="card-body p-0">
                        <div id="dns-records">
                            <div class="text-center p-5 text-muted">
                                <i class="fas fa-globe fa-3x mb-3"></i>
                                <h5>Select a zone to manage DNS records</h5>
                                <p>Choose a domain from the sidebar to start configuring DNS</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Add/Edit DNS Record Modal -->
    <div class="modal fade" id="recordModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="recordModalTitle">Add DNS Record</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <form id="recordForm">
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Record Type *</label>
                                    <select class="form-select" id="record-type" required onchange="updateFormFields()">
                                        <option value="">Select Type</option>
                                        <option value="A">A - IPv4 Address</option>
                                        <option value="AAAA">AAAA - IPv6 Address</option>
                                        <option value="CNAME">CNAME - Canonical Name</option>
                                        <option value="MX">MX - Mail Exchange</option>
                                        <option value="TXT">TXT - Text Record</option>
                                        <option value="NS">NS - Name Server</option>
                                        <option value="SRV">SRV - Service Record</option>
                                        <option value="CAA">CAA - Certificate Authority</option>
                                    </select>
                                    <div class="validation-message" id="type-error"></div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Name *</label>
                                    <input type="text" class="form-control" id="record-name" required 
                                           placeholder="www, mail, @ for root domain">
                                    <div class="validation-message" id="name-error"></div>
                                </div>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Content *</label>
                            <textarea class="form-control" id="record-content" rows="3" required 
                                      placeholder="Enter record value..."></textarea>
                            <div class="validation-message" id="content-error"></div>
                            <small class="form-text text-muted" id="content-help"></small>
                        </div>

                        <div class="row">
                            <div class="col-md-4">
                                <div class="mb-3">
                                    <label class="form-label">TTL</label>
                                    <select class="form-select" id="record-ttl">
                                        <option value="1">Auto</option>
                                        <option value="120">2 minutes</option>
                                        <option value="300">5 minutes</option>
                                        <option value="600">10 minutes</option>
                                        <option value="900">15 minutes</option>
                                        <option value="1800">30 minutes</option>
                                        <option value="3600">1 hour</option>
                                        <option value="7200">2 hours</option>
                                        <option value="18000">5 hours</option>
                                        <option value="43200">12 hours</option>
                                        <option value="86400">1 day</option>
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-4" id="priority-field" style="display: none;">
                                <div class="mb-3">
                                    <label class="form-label">Priority</label>
                                    <input type="number" class="form-control" id="record-priority" min="0" max="65535">
                                </div>
                            </div>
                            <div class="col-md-4" id="proxy-field" style="display: none;">
                                <div class="mb-3">
                                    <label class="form-label d-block">Proxy Status</label>
                                    <div class="form-check form-switch">
                                        <input class="form-check-input" type="checkbox" id="record-proxied">
                                        <label class="form-check-label" for="record-proxied">
                                            Proxied through Cloudflare
                                        </label>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <input type="hidden" id="zone-id-hidden">
                        <input type="hidden" id="record-id-hidden">
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-primary" onclick="saveRecord()">
                        <i class="fas fa-save"></i> Save Record
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Delete Confirmation Modal -->
    <div class="modal fade" id="deleteModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Confirm Delete</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p>Are you sure you want to delete this DNS record?</p>
                    <div class="alert alert-warning">
                        <strong>Warning:</strong> This action cannot be undone and may affect your website's accessibility.
                    </div>
                    <div id="delete-record-info"></div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-danger" onclick="confirmDelete()">
                        <i class="fas fa-trash"></i> Delete Record
                    </button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        let currentZone = null;
        let allRecords = [];
        let selectedRecords = new Set();
        let recordToDelete = null;

        document.addEventListener('DOMContentLoaded', function() {
            loadZones();
        });

        async function loadZones() {
            try {
                showLoading();
                
                const response = await fetch('', {
                    method: 'POST',
                    headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                    body: 'action=get_zones'
                });
                
                const data = await response.json();
                
                if (data.success) {
                    displayZones(data.zones);
                } else {
                    showNotification('error', 'Failed to load zones: ' + data.error);
                }
            } catch (error) {
                showNotification('error', 'Error loading zones: ' + error.message);
            } finally {
                hideLoading();
            }
        }

        function displayZones(zones) {
            const container = document.getElementById('zones-list');
            
            if (!zones || zones.length === 0) {
                container.innerHTML = `
                    <div class="text-center p-3 text-muted">
                        <i class="fas fa-exclamation-triangle fa-2x mb-2"></i>
                        <p>No zones found</p>
                    </div>
                `;
                return;
            }

            let html = '';
            zones.forEach((zone, index) => {
                const status = zone.status === 'active' ? 'success' : 'warning';
                html += `
                    <div class="zone-card border-0 border-bottom p-3" onclick="selectZone('${zone.id}', '${zone.name}', this)">
                        <div class="d-flex align-items-center">
                            <div class="flex-grow-1">
                                <h6 class="mb-1">${zone.name}</h6>
                                <small class="text-muted">
                                    <span class="badge bg-${status}">${zone.status}</span>
                                    ${zone.plan ? zone.plan.name : 'Free'}
                                </small>
                            </div>
                            <div class="text-muted">
                                <i class="fas fa-chevron-right"></i>
                            </div>
                        </div>
                    </div>
                `;
            });

            container.innerHTML = html;
        }

        async function selectZone(zoneId, zoneName, element) {
            // Update UI selection
            document.querySelectorAll('.zone-card').forEach(card => {
                card.classList.remove('selected');
            });
            element.classList.add('selected');

            currentZone = { id: zoneId, name: zoneName };
            
            // Clear selections
            clearSelection();
            
            // Reset filters
            document.getElementById('record-type-filter').value = 'all';
            document.getElementById('record-name-filter').value = '';
            
            // Load DNS records
            await loadDNSRecords();
        }

        async function loadDNSRecords() {
            if (!currentZone) {
                showNotification('warning', 'Please select a zone first');
                return;
            }

            try {
                showLoading();
                
                const recordType = document.getElementById('record-type-filter').value;
                const recordName = document.getElementById('record-name-filter').value;
                
                const formData = new FormData();
                formData.append('action', 'get_dns_records');
                formData.append('zone_id', currentZone.id);
                formData.append('record_type', recordType);
                formData.append('record_name', recordName);
                
                const response = await fetch('', {
                    method: 'POST',
                    body: formData
                });
                
                const data = await response.json();
                
                if (data.success) {
                    allRecords = data.records;
                    displayDNSRecords(data.records);
                    updateStatistics();
                    document.getElementById('stats-card').style.display = 'block';
                } else {
                    showNotification('error', 'Failed to load DNS records: ' + data.error);
                }
            } catch (error) {
                showNotification('error', 'Error loading DNS records: ' + error.message);
            } finally {
                hideLoading();
            }
        }

        function displayDNSRecords(records) {
            const container = document.getElementById('dns-records');
            
            if (!records || records.length === 0) {
                container.innerHTML = `
                    <div class="text-center p-5 text-muted">
                        <i class="fas fa-search fa-3x mb-3"></i>
                        <h5>No DNS records found</h5>
                        <p>Try adjusting your search filters or add a new record</p>
                    </div>
                `;
                return;
            }

            let html = '';
            records.forEach(record => {
                const typeClass = `record-type-${record.type}`;
                const isSelected = selectedRecords.has(record.id);
                
                html += `
                    <div class="record-card p-3 ${isSelected ? 'selected' : ''}" data-record-id="${record.id}">
                        <div class="row align-items-center">
                            <div class="col-md-1">
                                <input type="checkbox" class="form-check-input record-checkbox" 
                                       value="${record.id}" onchange="toggleRecordSelection('${record.id}')"
                                       ${isSelected ? 'checked' : ''}>
                            </div>
                            <div class="col-md-2">
                                <span class="badge record-type-badge ${typeClass}">${record.type}</span>
                            </div>
                            <div class="col-md-3">
                                <strong>${record.name}</strong>
                            </div>
                            <div class="col-md-4">
                                <code class="text-break">${record.content}</code>
                            </div>
                            <div class="col-md-2">
                                <div class="action-buttons">
                                    <div class="btn-group btn-group-sm">
                                        <button class="btn btn-outline-primary" onclick="editRecord('${record.id}')" title="Edit">
                                            <i class="fas fa-edit"></i>
                                        </button>
                                        <button class="btn btn-outline-danger" onclick="deleteRecord('${record.id}')" title="Delete">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </div>
                                </div>
                                <div class="small text-muted">
                                    TTL: ${record.ttl === 1 ? 'Auto' : record.ttl}
                                    ${record.proxied ? '<span class="badge bg-warning text-dark">Proxied</span>' : ''}
                                </div>
                            </div>
                        </div>
                    </div>
                `;
            });

            container.innerHTML = html;
            document.getElementById('displayed-count').textContent = records.length;
        }

        function updateStatistics() {
            document.getElementById('total-records').textContent = allRecords.length;
            updateSelectionCount();
        }

        function toggleRecordSelection(recordId) {
            if (selectedRecords.has(recordId)) {
                selectedRecords.delete(recordId);
            } else {
                selectedRecords.add(recordId);
            }
            updateSelectionCount();
            updateBatchActions();
        }

        function toggleSelectAll() {
            const selectAll = document.getElementById('select-all').checked;
            const checkboxes = document.querySelectorAll('.record-checkbox');
            
            if (selectAll) {
                checkboxes.forEach(checkbox => {
                    checkbox.checked = true;
                    selectedRecords.add(checkbox.value);
                });
            } else {
                checkboxes.forEach(checkbox => {
                    checkbox.checked = false;
                });
                selectedRecords.clear();
            }
            
            updateSelectionCount();
            updateBatchActions();
        }

        function clearSelection() {
            selectedRecords.clear();
            document.getElementById('select-all').checked = false;
            document.querySelectorAll('.record-checkbox').forEach(checkbox => {
                checkbox.checked = false;
            });
            document.querySelectorAll('.record-card').forEach(card => {
                card.classList.remove('selected');
            });
            updateSelectionCount();
            updateBatchActions();
        }

        function updateSelectionCount() {
            const count = selectedRecords.size;
            document.getElementById('selected-count').textContent = count;
            document.getElementById('selected-count-text').textContent = count;
        }

        function updateBatchActions() {
            const batchActions = document.getElementById('batch-actions');
            if (selectedRecords.size > 0) {
                batchActions.style.display = 'block';
            } else {
                batchActions.style.display = 'none';
            }
        }

        function showAddRecordModal() {
            if (!currentZone) {
                showNotification('warning', 'Please select a zone first');
                return;
            }
            
            // Reset form
            document.getElementById('recordForm').reset();
            document.getElementById('recordModalTitle').textContent = 'Add DNS Record';
            document.getElementById('zone-id-hidden').value = currentZone.id;
            document.getElementById('record-id-hidden').value = '';
            
            updateFormFields();
            
            const modal = new bootstrap.Modal(document.getElementById('recordModal'));
            modal.show();
        }

        function editRecord(recordId) {
            const record = allRecords.find(r => r.id === recordId);
            if (!record) return;
            
            // Populate form with record data
            document.getElementById('recordModalTitle').textContent = 'Edit DNS Record';
            document.getElementById('zone-id-hidden').value = currentZone.id;
            document.getElementById('record-id-hidden').value = recordId;
            document.getElementById('record-type').value = record.type;
            document.getElementById('record-name').value = record.name;
            document.getElementById('record-content').value = record.content;
            document.getElementById('record-ttl').value = record.ttl;
            document.getElementById('record-priority').value = record.priority || '';
            document.getElementById('record-proxied').checked = record.proxied || false;
            
            updateFormFields();
            
            const modal = new bootstrap.Modal(document.getElementById('recordModal'));
            modal.show();
        }

        function deleteRecord(recordId) {
            const record = allRecords.find(r => r.id === recordId);
            if (!record) return;
            
            recordToDelete = recordId;
            
            document.getElementById('delete-record-info').innerHTML = `
                <div class="border rounded p-2">
                    <strong>Type:</strong> ${record.type}<br>
                    <strong>Name:</strong> ${record.name}<br>
                    <strong>Content:</strong> ${record.content}
                </div>
            `;
            
            const modal = new bootstrap.Modal(document.getElementById('deleteModal'));
            modal.show();
        }

        async function confirmDelete() {
            if (!recordToDelete || !currentZone) return;
            
            try {
                showLoading();
                
                const formData = new FormData();
                formData.append('action', 'delete_dns_record');
                formData.append('zone_id', currentZone.id);
                formData.append('record_id', recordToDelete);
                
                const response = await fetch('', {
                    method: 'POST',
                    body: formData
                });
                
                const data = await response.json();
                
                if (data.success) {
                    showNotification('success', 'DNS record deleted successfully');
                    bootstrap.Modal.getInstance(document.getElementById('deleteModal')).hide();
                    await loadDNSRecords();
                } else {
                    showNotification('error', 'Failed to delete record: ' + data.error);
                }
            } catch (error) {
                showNotification('error', 'Error deleting record: ' + error.message);
            } finally {
                hideLoading();
                recordToDelete = null;
            }
        }

        async function batchDelete() {
            if (selectedRecords.size === 0) {
                showNotification('warning', 'No records selected');
                return;
            }
            
            if (!confirm(`Are you sure you want to delete ${selectedRecords.size} selected records? This action cannot be undone.`)) {
                return;
            }
            
            try {
                showLoading();
                
                const formData = new FormData();
                formData.append('action', 'batch_delete');
                formData.append('zone_id', currentZone.id);
                
                selectedRecords.forEach(recordId => {
                    formData.append('record_ids[]', recordId);
                });
                
                const response = await fetch('', {
                    method: 'POST',
                    body: formData
                });
                
                const data = await response.json();
                
                if (data.success || data.details.successful > 0) {
                    showNotification('success', data.message);
                    clearSelection();
                    await loadDNSRecords();
                } else {
                    showNotification('error', 'Batch delete failed: ' + data.error);
                }
            } catch (error) {
                showNotification('error', 'Error during batch delete: ' + error.message);
            } finally {
                hideLoading();
            }
        }

        function updateFormFields() {
            const type = document.getElementById('record-type').value;
            const priorityField = document.getElementById('priority-field');
            const proxyField = document.getElementById('proxy-field');
            const contentHelp = document.getElementById('content-help');
            
            // Hide all optional fields first
            priorityField.style.display = 'none';
            proxyField.style.display = 'none';
            
            // Show relevant fields based on record type
            switch (type) {
                case 'A':
                    proxyField.style.display = 'block';
                    contentHelp.textContent = 'IPv4 address (e.g., 192.168.1.1)';
                    break;
                case 'AAAA':
                    proxyField.style.display = 'block';
                    contentHelp.textContent = 'IPv6 address (e.g., 2001:db8::1)';
                    break;
                case 'CNAME':
                    proxyField.style.display = 'block';
                    contentHelp.textContent = 'Target domain name (e.g., example.com)';
                    break;
                case 'MX':
                    priorityField.style.display = 'block';
                    contentHelp.textContent = 'Mail server hostname (e.g., mail.example.com)';
                    break;
                case 'TXT':
                    contentHelp.textContent = 'Text content (e.g., "v=spf1 include:_spf.google.com ~all")';
                    break;
                case 'NS':
                    contentHelp.textContent = 'Name server hostname (e.g., ns1.example.com)';
                    break;
                case 'SRV':
                    priorityField.style.display = 'block';
                    contentHelp.textContent = 'Weight Port Target (e.g., "10 80 target.example.com")';
                    break;
                case 'CAA':
                    contentHelp.textContent = 'CAA record format (e.g., "0 issue letsencrypt.org")';
                    break;
                default:
                    contentHelp.textContent = 'Enter the record content based on the selected type';
            }
        }

        async function saveRecord() {
            const form = document.getElementById('recordForm');
            const formData = new FormData();
            
            // Validation
            if (!validateForm()) return;
            
            const isEdit = document.getElementById('record-id-hidden').value !== '';
            const action = isEdit ? 'update_dns_record' : 'create_dns_record';
            
            formData.append('action', action);
            formData.append('zone_id', document.getElementById('zone-id-hidden').value);
            if (isEdit) {
                formData.append('record_id', document.getElementById('record-id-hidden').value);
            }
            formData.append('type', document.getElementById('record-type').value);
            formData.append('name', document.getElementById('record-name').value);
            formData.append('content', document.getElementById('record-content').value);
            formData.append('ttl', document.getElementById('record-ttl').value);
            formData.append('priority', document.getElementById('record-priority').value);
            formData.append('proxied', document.getElementById('record-proxied').checked);
            
            try {
                showLoading();
                
                const response = await fetch('', {
                    method: 'POST',
                    body: formData
                });
                
                const data = await response.json();
                
                if (data.success) {
                    showNotification('success', data.message);
                    bootstrap.Modal.getInstance(document.getElementById('recordModal')).hide();
                    await loadDNSRecords();
                } else {
                    showNotification('error', data.error);
                }
            } catch (error) {
                showNotification('error', 'Error saving record: ' + error.message);
            } finally {
                hideLoading();
            }
        }

        function validateForm() {
            const type = document.getElementById('record-type').value;
            const name = document.getElementById('record-name').value;
            const content = document.getElementById('record-content').value;
            
            // Reset validation styles
            document.querySelectorAll('.form-control').forEach(el => {
                el.classList.remove('validation-error');
            });
            document.querySelectorAll('.validation-message').forEach(el => {
                el.textContent = '';
            });
            
            let isValid = true;
            
            if (!type) {
                document.getElementById('record-type').classList.add('validation-error');
                document.getElementById('type-error').textContent = 'Record type is required';
                isValid = false;
            }
            
            if (!name) {
                document.getElementById('record-name').classList.add('validation-error');
                document.getElementById('name-error').textContent = 'Name is required';
                isValid = false;
            }
            
            if (!content) {
                document.getElementById('record-content').classList.add('validation-error');
                document.getElementById('content-error').textContent = 'Content is required';
                isValid = false;
            }
            
            return isValid;
        }

        function showLoading() {
            document.getElementById('loading-overlay').style.display = 'flex';
        }

        function hideLoading() {
            document.getElementById('loading-overlay').style.display = 'none';
        }

        function showNotification(type, message) {
            const notification = document.createElement('div');
            notification.className = `alert alert-${type === 'success' ? 'success' : type === 'warning' ? 'warning' : 'danger'} alert-dismissible fade show`;
            notification.style.position = 'fixed';
            notification.style.top = '20px';
            notification.style.right = '20px';
            notification.style.zIndex = '9999';
            notification.style.maxWidth = '400px';
            notification.innerHTML = `
                ${message}
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            `;
            
            document.body.appendChild(notification);
            
            setTimeout(() => {
                if (notification.parentNode) {
                    notification.remove();
                }
            }, 5000);
        }

        // Event listeners for filters
        document.getElementById('record-type-filter').addEventListener('change', function() {
            if (currentZone) loadDNSRecords();
        });

        document.getElementById('record-name-filter').addEventListener('input', debounce(function() {
            if (currentZone) loadDNSRecords();
        }, 500));

        function debounce(func, wait) {
            let timeout;
            return function executedFunction(...args) {
                const later = () => {
                    clearTimeout(timeout);
                    func(...args);
                };
                clearTimeout(timeout);
                timeout = setTimeout(later, wait);
            };
        }
    </script>
</body>
</html>
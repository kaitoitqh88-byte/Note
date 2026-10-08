<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/CloudflareAPI.php';

$api = new CloudflareAPI();

// Handle AJAX requests
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'];
    header('Content-Type: application/json');
    
    try {
        switch ($action) {
            case 'check_domains':
                $domains = array_filter(array_map('trim', explode("\n", $_POST['domains'] ?? '')));
                $results = [];
                
                foreach ($domains as $domain) {
                    $zoneId = $api->getZoneIdByDomain($domain);
                    $results[] = [
                        'domain' => $domain,
                        'zone_id' => $zoneId,
                        'status' => $zoneId ? 'found' : 'not_found',
                        'message' => $zoneId ? "Zone ID: {$zoneId}" : 'Zone not found in your Cloudflare account'
                    ];
                }
                
                echo json_encode(['success' => true, 'results' => $results]);
                break;
                
            case 'check_existing_rulesets':
                $domains = array_filter(array_map('trim', explode("\n", $_POST['domains'] ?? '')));
                $results = [];
                
                foreach ($domains as $domain) {
                    $zoneId = $api->getZoneIdByDomain($domain);
                    if ($zoneId) {
                        $rulesets = $api->getRulesets($zoneId);
                        $redirectRulesets = [];
                        
                        if ($rulesets && isset($rulesets['result'])) {
                            foreach ($rulesets['result'] as $ruleset) {
                                if (($ruleset['phase'] ?? '') === 'http_request_dynamic_redirect') {
                                    $redirectRulesets[] = [
                                        'id' => $ruleset['id'],
                                        'name' => $ruleset['name'],
                                        'description' => $ruleset['description'] ?? '',
                                        'rules_count' => count($ruleset['rules'] ?? [])
                                    ];
                                }
                            }
                        }
                        
                        $results[] = [
                            'domain' => $domain,
                            'zone_id' => $zoneId,
                            'rulesets' => $redirectRulesets,
                            'count' => count($redirectRulesets)
                        ];
                    }
                }
                
                echo json_encode(['success' => true, 'results' => $results]);
                break;
                
            case 'create_bulk_redirects':
                $domains = $_POST['domains'] ?? '';
                $targetUrl = $_POST['target_url'] ?? '';
                $statusCode = (int)($_POST['status_code'] ?? 301);
                $preservePath = !empty($_POST['preserve_path']);
                $preserveQuery = !empty($_POST['preserve_query']);
                $deleteOld = !empty($_POST['delete_old']);
                $allIncomingRequests = !empty($_POST['all_incoming_requests']);
                
                if (empty($domains)) {
                    throw new Exception('Domains are required');
                }
                
                if (empty($targetUrl)) {
                    throw new Exception('Target URL is required');
                }
                
                $result = $api->bulkRedirectManager(
                    $domains,
                    $targetUrl,
                    $statusCode,
                    $preservePath,
                    $preserveQuery,
                    $deleteOld,
                    $allIncomingRequests
                );
                
                echo json_encode($result);
                break;
                
            case 'delete_rulesets':
                $domains = array_filter(array_map('trim', preg_split('/[\r\n,;]+/', $_POST['domains'] ?? '', -1, PREG_SPLIT_NO_EMPTY)));
                $results = [];
                
                foreach ($domains as $domain) {
                    $zoneId = $api->getZoneIdByDomain($domain);
                    if ($zoneId) {
                        $deleteResult = $api->findAndDeleteOldRedirectRules($zoneId);
                        $errorText = $deleteResult['error'] ?? null;
                        if (!$errorText && !empty($deleteResult['errors']) && is_array($deleteResult['errors'])) {
                            $messages = array_map(function($item) {
                                if (is_array($item)) {
                                    return ($item['name'] ?? 'ruleset') . ': ' . ($item['error'] ?? 'Unknown error');
                                }
                                return (string)$item;
                            }, $deleteResult['errors']);
                            $errorText = implode(' | ', $messages);
                        }

                        $results[] = [
                            'domain' => $domain,
                            'zone_id' => $zoneId,
                            'success' => $deleteResult['success'],
                            'deleted_count' => $deleteResult['deleted_count'] ?? 0,
                            'deleted_ruleset_count' => $deleteResult['deleted_ruleset_count'] ?? 0,
                            'deleted_page_rule_count' => $deleteResult['deleted_page_rule_count'] ?? 0,
                            'deleted_rulesets' => $deleteResult['deleted_rulesets'] ?? [],
                            'deleted_page_rules' => $deleteResult['deleted_page_rules'] ?? [],
                            'skipped_rulesets' => $deleteResult['skipped_rulesets'] ?? [],
                            'errors' => $deleteResult['errors'] ?? [],
                            'error' => $errorText
                        ];
                    } else {
                        $results[] = [
                            'domain' => $domain,
                            'zone_id' => null,
                            'success' => false,
                            'error' => 'Zone not found'
                        ];
                    }
                }
                
                echo json_encode(['success' => true, 'results' => $results]);
                break;

            case 'process_single_domain':
                $domain = trim($_POST['domain'] ?? '');
                $mode = trim($_POST['mode'] ?? 'check');
                $targetUrl = trim($_POST['target_url'] ?? '');
                $statusCode = (int)($_POST['status_code'] ?? 301);
                $preservePath = !empty($_POST['preserve_path']);
                $preserveQuery = !empty($_POST['preserve_query']);
                $deleteOld = !empty($_POST['delete_old']);
                $allIncomingRequests = !empty($_POST['all_incoming_requests']);

                if ($domain === '') {
                    throw new Exception('Domain is required');
                }

                $zoneId = $api->getZoneIdByDomain($domain);
                if (!$zoneId) {
                    echo json_encode([
                        'success' => false,
                        'domain' => $domain,
                        'error' => 'Zone not found'
                    ]);
                    break;
                }

                if ($mode === 'check') {
                    echo json_encode([
                        'success' => true,
                        'domain' => $domain,
                        'zone_id' => $zoneId,
                        'message' => "Zone ID: {$zoneId}"
                    ]);
                    break;
                }

                if ($mode === 'check_rulesets') {
                    $rulesets = $api->getRulesets($zoneId);
                    $redirectRulesets = [];

                    if ($rulesets && isset($rulesets['result'])) {
                        foreach ($rulesets['result'] as $ruleset) {
                            if (($ruleset['phase'] ?? '') === 'http_request_dynamic_redirect') {
                                $redirectRulesets[] = [
                                    'id' => $ruleset['id'],
                                    'name' => $ruleset['name'],
                                    'description' => $ruleset['description'] ?? '',
                                    'rules_count' => count($ruleset['rules'] ?? [])
                                ];
                            }
                        }
                    }

                    echo json_encode([
                        'success' => true,
                        'domain' => $domain,
                        'zone_id' => $zoneId,
                        'rulesets' => $redirectRulesets,
                        'count' => count($redirectRulesets)
                    ]);
                    break;
                }

                if ($mode === 'create') {
                    if ($targetUrl === '') {
                        throw new Exception('Target URL is required');
                    }

                    $result = $api->bulkRedirectManager(
                        $domain,
                        $targetUrl,
                        $statusCode,
                        $preservePath,
                        $preserveQuery,
                        $deleteOld,
                        $allIncomingRequests
                    );

                    $createdDomain = $result['processed_domains'][0] ?? null;
                    $cachePurge = null;
                    if (!empty($createdDomain['success']) && !empty($createdDomain['new_ruleset_created'])) {
                        try {
                            $purgeResult = $api->purgeCache($zoneId);
                            $cachePurge = [
                                'success' => !empty($purgeResult['success']),
                                'message' => !empty($purgeResult['success'])
                                    ? 'Purge Cache thành công'
                                    : ($purgeResult['errors'][0]['message'] ?? 'Cloudflare không xác nhận Purge Cache thành công')
                            ];
                        } catch (Exception $purgeError) {
                            $cachePurge = [
                                'success' => false,
                                'message' => $purgeError->getMessage()
                            ];
                        }
                    }

                    echo json_encode([
                        'success' => (bool)($result['success'] ?? false),
                        'domain' => $domain,
                        'zone_id' => $zoneId,
                        'cache_purge' => $cachePurge,
                        'result' => $result
                    ]);
                    break;
                }

                if ($mode === 'delete') {
                    $deleteResult = $api->findAndDeleteOldRedirectRules($zoneId);
                    $errorText = $deleteResult['error'] ?? null;
                    if (!$errorText && !empty($deleteResult['errors']) && is_array($deleteResult['errors'])) {
                        $messages = array_map(function($item) {
                            if (is_array($item)) {
                                return ($item['name'] ?? 'ruleset') . ': ' . ($item['error'] ?? 'Unknown error');
                            }
                            return (string)$item;
                        }, $deleteResult['errors']);
                        $errorText = implode(' | ', $messages);
                    }

                    echo json_encode([
                        'success' => (bool)($deleteResult['success'] ?? false),
                        'domain' => $domain,
                        'zone_id' => $zoneId,
                        'deleted_count' => $deleteResult['deleted_count'] ?? 0,
                        'deleted_ruleset_count' => $deleteResult['deleted_ruleset_count'] ?? 0,
                        'deleted_page_rule_count' => $deleteResult['deleted_page_rule_count'] ?? 0,
                        'deleted_rulesets' => $deleteResult['deleted_rulesets'] ?? [],
                        'deleted_page_rules' => $deleteResult['deleted_page_rules'] ?? [],
                        'skipped_rulesets' => $deleteResult['skipped_rulesets'] ?? [],
                        'errors' => $deleteResult['errors'] ?? [],
                        'error' => $errorText
                    ]);
                    break;
                }

                throw new Exception('Invalid mode');
                
            default:
                throw new Exception('Invalid action');
        }
        
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
    
    exit;
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
<link rel="icon" type="image/x-icon" href="assets/favicon.ico">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>[TACTICAL-CF] Bulk Redirect Manager - Route Control System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@300;400;500;700&family=Roboto+Mono:wght@300;400;500;700&display=swap" rel="stylesheet">
    <link href="assets/css/rpg-shooter-theme.css" rel="stylesheet">
    <link href="assets/css/sidebar-navigation.css" rel="stylesheet">
    <style>
        /* Force sidebar-content padding to 0 */
        .sidebar-content {
            padding: 0 !important;
        }
        /* Matrix Theme with Roboto Font */
        * {
            font-family: 'Roboto', sans-serif !important;
        }
        
        body {
            background: #000;
            color: #00ff00;
            font-family: 'Roboto', sans-serif;
            text-shadow: 0 0 5px rgba(0, 255, 0, 0.5);
            overflow-x: hidden;
        }
        
        /* Matrix Animation Background */
        body::before {
            content: '';
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: repeating-linear-gradient(
                90deg,
                transparent,
                transparent 98px,
                rgba(0, 255, 0, 0.03) 100px
            );
            pointer-events: none;
            z-index: -1;
            animation: matrixLines 10s linear infinite;
        }
        
        @keyframes matrixLines {
            0% { transform: translateX(0); }
            100% { transform: translateX(100px); }
        }
        
        /* Sidebar Override - Show by default */
        .sidebar-nav {
            left: 0 !important;
        }
        
        /* Enhanced Sidebar Toggle Button Styling */
            outline: 2px solid rgba(0, 255, 0, 0.5) !important;
            outline-offset: 2px !important;
        }
        
        /* Body with sidebar open - adjust main content */
        body {
            padding-left: 280px;
            transition: padding-left 0.3s ease;
        }
        
        /* Body state when sidebar is hidden */
        body.sidebar-collapsed {
            padding-left: 0;
        }
        
        body.sidebar-collapsed .sidebar-nav {
            left: -280px !important;
        }
        
        /* Responsive Design */
        @media (max-width: 768px) {
            body {
                padding-left: 0 !important;
            }
            
            .sidebar-nav {
                left: -280px !important;
            }
            
            .sidebar-nav.active {
                left: 0 !important;
            }
        }
        
        /* Smooth transitions for all layout changes */
        .hud-container, .redirect-layout {
            transition: margin-left 0.3s ease, padding-left 0.3s ease;
        }
        
        /* Matrix Text Glow */
        h1, h2, h3, h4, h5, h6 {
            color: #00ff00 !important;
            text-shadow: 0 0 10px rgba(0, 255, 0, 0.8), 0 0 20px rgba(0, 255, 0, 0.4);
            font-family: 'Roboto', sans-serif !important;
            font-weight: 500;
        }
        /* Bulk Redirect Layout */
        .redirect-layout {
            min-height: 80vh;
            max-height: none;
            height: auto;
            overflow: visible;
            background: rgba(20, 30, 20, 0.92);
            border-radius: 18px;
            box-shadow: 0 8px 32px 0 rgba(0,255,0,0.10), 0 1.5px 8px 0 rgba(0,255,0,0.08);
            padding: 1.25rem;
            margin-bottom: 2rem;
        }
        
        .redirect-main {
            width: 100%;
            padding: 0.5rem;
            min-height: 60vh;
            max-height: none;
            height: auto;
            overflow: visible;
            background: transparent;
        }
        
        /* Matrix-style form labels */
        .form-check-label {
            color: #00ff00;
            font-family: 'Roboto', sans-serif;
            font-weight: 500;
            text-shadow: 0 0 3px rgba(0, 255, 0, 0.3);
        }
        
        .form-text {
            color: rgba(0, 255, 0, 0.7);
            font-family: 'Roboto Mono', monospace;
            font-size: 0.8rem;
        }
        
        /* Matrix button variations */
        .btn-outline-light {
            border-color: #00ff00;
            color: #00ff00;
            background: transparent;
        }
        
        .btn-outline-light:hover {
            background: #00ff00;
            color: #000;
            box-shadow: 0 0 15px rgba(0, 255, 0, 0.5);
        }
        
        /* Mobile Responsive */
        @media (max-width: 767px) {
            .redirect-main {
                padding: 1rem;
            }
        }
        
        /* Main Title Styling */
        h1 {
            font-size: 1.5rem;
            font-family: 'Roboto', sans-serif;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 2px;
            color: #00ff00;
            text-shadow: 0 0 15px rgba(0, 255, 0, 0.8), 0 0 30px rgba(0, 255, 0, 0.4);
            margin-bottom: 1rem;
            animation: matrixGlow 2s ease-in-out infinite alternate;
        }
        
        @keyframes matrixGlow {
            from { text-shadow: 0 0 15px rgba(0, 255, 0, 0.8), 0 0 30px rgba(0, 255, 0, 0.4); }
            to { text-shadow: 0 0 20px rgba(0, 255, 0, 1), 0 0 40px rgba(0, 255, 0, 0.6); }
        }
        
        /* Action Bar Styling */
        .action-bar {
            background: rgba(0, 0, 0, 0.9);
            border: 1px solid rgba(0, 255, 0, 0.45);
            border-radius: 10px;
            padding: 0.75rem;
            margin-bottom: 1rem;
            display: flex;
            gap: 0.6rem;
            flex-wrap: wrap;
            justify-content: flex-start;
            box-shadow: 0 0 15px rgba(0, 255, 0, 0.15);
        }
        
        .action-btn {
            border: 2px solid #00ff00;
            background: rgba(0, 255, 0, 0.1);
            color: #00ff00;
            font-family: 'Roboto', sans-serif;
            font-weight: 600;
            font-size: 0.9rem;
            padding: 0.75rem 1.5rem;
            border-radius: 8px;
            transition: all 0.3s ease;
            text-transform: uppercase;
            display: flex;
            align-items: center;
            gap: 0.5rem;
            cursor: pointer;
            min-width: 150px;
            justify-content: center;
            text-shadow: 0 0 5px rgba(0, 255, 0, 0.5);
            letter-spacing: 0.5px;
        }

        .redirect-main .action-btn {
            min-width: 0;
            padding: 0.55rem 0.9rem;
            font-size: 0.8rem;
        }
        
        .action-btn:hover {
            background: #00ff00;
            color: #000;
            transform: translateY(-3px);
            box-shadow: 0 8px 25px rgba(0, 255, 0, 0.5);
            text-shadow: none;
        }
        
        .action-btn:disabled {
            opacity: 0.5;
            cursor: not-allowed;
            transform: none;
            box-shadow: none;
        }
        
        .action-btn.processing {
            background: rgba(255, 193, 7, 0.2);
            border-color: #ffc107;
            color: #ffc107;
        }
        
        .btn-check {
            border-color: #17a2b8;
            background: rgba(23, 162, 184, 0.1);
        }
        
        .btn-check:hover {
            background: #17a2b8;
            border-color: #17a2b8;
            color: #fff;
        }
        
        .btn-create {
            border-color: #28a745;
            background: rgba(40, 167, 69, 0.1);
        }
        
        .btn-create:hover {
            background: #28a745;
            border-color: #28a745;
            color: #fff;
        }
        
        .btn-delete {
            border-color: #dc3545;
            background: rgba(220, 53, 69, 0.1);
        }
        
        .btn-delete:hover {
            background: #dc3545;
            border-color: #dc3545;
            color: #fff;
        }
        
        .btn-export {
            border-color: #6f42c1;
            background: rgba(111, 66, 193, 0.1);
        }
        
        .btn-export:hover {
            background: #6f42c1;
            border-color: #6f42c1;
            color: #fff;
        }
        
        /* Input Section Styling */
        .input-section {
            background: rgba(0, 0, 0, 0.8);
            border: 1px solid rgba(0, 255, 0, 0.4);
            border-radius: 12px;
            padding: 1.25rem;
            margin-bottom: 1rem;
            box-shadow: 0 0 12px rgba(0, 255, 0, 0.15);
        }

        .redirect-input-column {
            min-width: 0;
        }

        .redirect-options {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 0.5rem 1rem;
        }

        .redirect-options .form-check {
            margin: 0;
        }

        .redirect-options .form-text {
            margin-left: 1.5rem;
        }

        #redirectPairsInput,
        #domainsInput {
            min-height: 13rem;
            resize: vertical;
        }

        @media (max-width: 767px) {
            .redirect-layout {
                padding: 0.75rem;
            }

            .redirect-main {
                padding: 0.25rem;
            }

            .input-section {
                padding: 0.85rem;
            }

            .redirect-options {
                grid-template-columns: 1fr;
            }
        }
        
        .form-floating > label {
            color: #00ff00;
            font-family: 'Roboto', sans-serif;
            text-shadow: 0 0 5px rgba(0, 255, 0, 0.5);
        }
        
        .form-control {
            background: rgba(0, 0, 0, 0.7);
            border: 1px solid rgba(0, 255, 0, 0.4);
            color: #00ff00;
            font-family: 'Roboto Mono', monospace;
            text-shadow: 0 0 3px rgba(0, 255, 0, 0.3);
        }
        
        .form-control:focus {
            background: rgba(0, 0, 0, 0.9);
            border-color: #00ff00;
            box-shadow: 0 0 0 0.25rem rgba(0, 255, 0, 0.25);
            color: #00ff00;
        }
        
        .form-control::placeholder {
            color: rgba(0, 255, 0, 0.6);
        }
        
        .domain-count {
            background: #00ff00;
            color: #000;
            padding: 0.25rem 0.5rem;
            border-radius: 20px;
            font-weight: 700;
            font-size: 0.8rem;
            margin-left: 0.5rem;
            box-shadow: 0 0 8px rgba(0, 255, 0, 0.6);
        }
        
        /* Progress Section */
        .progress-section {
            background: rgba(0, 0, 0, 0.9);
            border: 1px solid #00ff00;
            border-radius: 12px;
            padding: 1.5rem;
            margin-bottom: 2rem;
            box-shadow: 0 0 15px rgba(0, 255, 0, 0.2);
        }
        
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 1rem;
            margin-top: 1rem;
        }
        
        .stat-card {
            background: rgba(0, 255, 0, 0.05);
            border: 1px solid #00ff00;
            border-radius: 8px;
            padding: 1rem;
            text-align: center;
            box-shadow: 0 0 10px rgba(0, 255, 0, 0.2);
        }
        
        .stat-value {
            font-size: 2rem;
            font-weight: 700;
            color: #00ff00;
            font-family: 'Roboto', sans-serif;
            text-shadow: 0 0 10px rgba(0, 255, 0, 0.8);
        }
        
        .stat-label {
            font-size: 0.85rem;
            color: var(--tactical-text);
            margin-top: 0.5rem;
        }
        
        /* Live Console */
        .live-console {
            background: rgba(0, 0, 0, 0.95);
            border: 2px solid #00ff00;
            border-radius: 12px;
            margin-bottom: 2rem;
            font-family: 'Roboto Mono', monospace;
            box-shadow: 0 0 20px rgba(0, 255, 0, 0.3);
        }
        
        .console-header {
            background: rgba(0, 255, 0, 0.1);
            padding: 1rem;
            border-bottom: 1px solid #00ff00;
            color: #00ff00;
            font-weight: 700;
            text-shadow: 0 0 8px rgba(0, 255, 0, 0.6);
        }
        
        .console-footer {
            background: rgba(0, 255, 0, 0.05);
            padding: 0.75rem 1rem;
            border-top: 1px solid rgba(0, 255, 0, 0.3);
            text-align: center;
        }
        
        .console-footer .btn {
            border-color: #00ff00 !important;
            color: #00ff00 !important;
            background: rgba(0, 255, 0, 0.1) !important;
            transition: all 0.3s ease;
        }
        
        .console-footer .btn:hover {
            background: #00ff00 !important;
            color: #000 !important;
            box-shadow: 0 0 10px rgba(0, 255, 0, 0.5) !important;
            transform: scale(1.05);
        }
        
        #consoleContent {
            padding: 1rem;
            max-height: 300px;
            overflow-y: auto;
            color: #00ff00;
            font-family: 'Roboto Mono', monospace;
        }
        
        .log-entry {
            margin-bottom: 0.5rem;
            padding: 0.25rem 0.5rem;
            border-radius: 4px;
            font-family: 'Roboto Mono', monospace;
            text-shadow: 0 0 3px rgba(0, 255, 0, 0.3);
        }
        
        .log-info {
            color: #00ff00;
        }
        
        .log-success {
            color: #00ff88;
            text-shadow: 0 0 5px rgba(0, 255, 136, 0.5);
        }
        
        .log-error {
            color: #ff4444;
            text-shadow: 0 0 5px rgba(255, 68, 68, 0.5);
        }
        
        .log-warning {
            color: #ffaa00;
            text-shadow: 0 0 5px rgba(255, 170, 0, 0.5);
        }
        
        .log-timestamp {
            color: #6c757d;
            font-weight: 700;
        }
        
        /* Results Table */
        .results-table {
            background: rgba(0, 0, 0, 0.9);
            border: 1px solid #00ff00;
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 0 15px rgba(0, 255, 0, 0.2);
        }
        
        .table {
            color: #00ff00;
            margin: 0;
            font-family: 'Roboto Mono', monospace;
        }
        
        .table th {
            background: rgba(0, 255, 0, 0.1);
            border-color: #00ff00;
            color: #00ff00;
            font-family: 'Roboto', sans-serif;
            font-weight: 700;
            text-shadow: 0 0 8px rgba(0, 255, 0, 0.6);
        }
        
        .table td {
            border-color: rgba(0, 255, 0, 0.2);
        }

        .status-badge {
            padding: 0.5rem 1rem;
            border-radius: 20px;
            font-weight: 700;
            font-size: 0.85rem;
            font-family: 'Roboto', sans-serif;
            text-shadow: 0 0 5px rgba(0, 0, 0, 0.8);
        }
        
        .status-success {
            background: rgba(0, 255, 136, 0.2);
            color: #00ff88;
            border: 1px solid #00ff88;
            box-shadow: 0 0 10px rgba(0, 255, 136, 0.3);
        }
        
        .status-error {
            background: rgba(255, 68, 68, 0.2);
            color: #ff4444;
            border: 1px solid #ff4444;
            box-shadow: 0 0 10px rgba(255, 68, 68, 0.3);
        }
        
        /* Matrix-style form labels */
        .form-check-label {
            color: #00ff00;
            font-family: 'Roboto', sans-serif;
            font-weight: 500;
            text-shadow: 0 0 3px rgba(0, 255, 0, 0.3);
        }
        
        .form-text {
            color: rgba(0, 255, 0, 0.7);
            font-family: 'Roboto Mono', monospace;
            font-size: 0.8rem;
        }
        
        /* Matrix button variations */
        .btn-outline-light {
            border-color: #00ff00;
            color: #00ff00;
            background: transparent;
        }
        
        .btn-outline-light:hover {
            background: #00ff00;
            color: #000;
            box-shadow: 0 0 15px rgba(0, 255, 0, 0.5);
        }
        @media (max-width: 767px) {
            .action-bar {
                flex-direction: column;
                gap: 0.75rem;
            }
            
            .action-btn {
                min-width: auto;
                width: 100%;
            }
            
            .stats-grid {
                grid-template-columns: repeat(2, 1fr);
            }
        }
        
        code {
            padding: 5px;
            font-size: 16px;
            background: rgba(0, 0, 0, 0.1);
            border: 1px solid rgba(0, 0, 0, 0.2);
            border-radius: 4px;
            color: var(--tactical-text);
            font-family: 'Roboto Mono', monospace;
        }
    </style>
</head>
<body>
    <?php 
    // Set current page for navigation highlighting
    $currentPage = 'bulk_redirect_manager';
    include 'includes/main_navigation.php'; 
    ?>

    <div class="container-fluid mt-4 hud-container p-0">
        <div class="redirect-layout">
            <!-- Main Content -->
            <div class="redirect-main">
                <div class="input-section">
                    <div class="row g-3">
                        <div class="col-lg-6 redirect-input-column">
                            <label for="redirectPairsInput" class="form-label">Dán danh sách chuyển hướng</label>
                            <textarea
                                class="form-control"
                                id="redirectPairsInput"
                                rows="8"
                                placeholder="Dán danh sách domain và URL đích tại đây..."
                            ></textarea>
                            <div class="d-flex flex-wrap align-items-center gap-2 mt-2">
                                <button type="button" class="btn btn-outline-primary" id="convertRedirectPairsBtn">
                                    Chuyển vào form 301
                                </button>
                                <small class="text-muted">Có thể dán nhiều nhóm.</small>
                            </div>
                            <small class="text-muted d-block mt-1">
                                Hỗ trợ “domain »»» URL đích”, “domain => URL đích”, “Trỏ sang: URL đích”, “=>> URL đích” hoặc danh sách domain rồi đến mã trạng thái và domain đích.
                            </small>
                            <small class="text-primary d-block mt-1" id="redirectPairsStatus" aria-live="polite"></small>
                        </div>

                        <div class="col-lg-6 redirect-input-column">
                            <label for="domainsInput" class="form-label">Domain nguồn</label>
                            <textarea
                                class="form-control"
                                id="domainsInput"
                                placeholder="Một domain mỗi dòng; được điền tự động khi chuyển danh sách..."
                            ></textarea>
                            <div class="domain-count-wrapper mt-1">
                                <small class="text-muted">Domain count: <span class="domain-count" id="domainCount">0</span></small>
                            </div>

                            <div class="row g-2 mt-1">
                                <div class="col-md-8">
                                    <label for="targetUrlInput" class="form-label small text-muted">URL đích</label>
                                    <input
                                        type="url"
                                        class="form-control"
                                        id="targetUrlInput"
                                        placeholder="https://example.com"
                                    >
                                </div>
                                <div class="col-md-4">
                                    <label for="statusCodeSelect" class="form-label small text-muted">Mã redirect</label>
                                    <select class="form-control" id="statusCodeSelect">
                                        <option value="301">301 - Permanent</option>
                                        <option value="302">302 - Temporary</option>
                                        <option value="307">307 - Temporary (Preserved)</option>
                                        <option value="308">308 - Permanent (Preserved)</option>
                                    </select>
                                </div>
                            </div>

                            <div class="redirect-options mt-3">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" id="preservePathCheck" checked>
                                    <label class="form-check-label" for="preservePathCheck">Giữ nguyên Path</label>
                                </div>
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" id="preserveQueryCheck" checked>
                                    <label class="form-check-label" for="preserveQueryCheck">Giữ nguyên Query parameters</label>
                                </div>
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" id="deleteOldCheck" checked>
                                    <label class="form-check-label" for="deleteOldCheck">Xóa rules cũ và Page Rules trước khi tạo</label>
                                </div>
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" id="allIncomingRequestsCheck" checked>
                                    <label class="form-check-label" for="allIncomingRequestsCheck">All incoming requests</label>
                                    <div class="form-text small">Bật: redirect tất cả request. Tắt: chỉ redirect domain khớp.</div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="action-bar">
                    <button type="button" class="action-btn btn-create" id="createRedirectsBtn">
                        Tạo 301 Redirect
                    </button>
                    <button type="button" class="action-btn btn-check" id="checkDomainsBtn">
                        Kiểm tra Zones
                    </button>
                    <button type="button" class="action-btn btn-check" id="checkRulesetsBtn">
                        Check Rules cũ
                    </button>
                    <button type="button" class="action-btn btn-check" id="checkRule301Btn">
                        Check Rule 301
                    </button>
                    <button type="button" class="action-btn btn-delete" id="deleteRulesetsBtn">
                        Xóa Rules cũ
                    </button>
                    <button type="button" class="action-btn btn-export" id="exportResultsBtn">
                        Xuất kết quả
                    </button>
                </div>
        
        <!-- Progress Section -->
        <div class="progress-section" id="progressSection" style="display: none;">
            <h5>Tiến trình xử lý</h5>
            <div class="progress mb-3">
                <div 
                    class="progress-bar" 
                    id="progressBar" 
                    role="progressbar" 
                    style="width: 0%"
                    aria-valuenow="0" 
                    aria-valuemin="0" 
                    aria-valuemax="100"
                ></div>
            </div>
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-value" id="totalDomainsCount">0</div>
                    <div class="stat-label">Tổng số Domain</div>
                </div>
                <div class="stat-card">
                    <div class="stat-value" id="processedCount">0</div>
                    <div class="stat-label">Đã xử lý</div>
                </div>
                <div class="stat-card">
                    <div class="stat-value" id="successCount">0</div>
                    <div class="stat-label">Thành công</div>
                </div>
                <div class="stat-card">
                    <div class="stat-value" id="errorCount">0</div>
                    <div class="stat-label">Lỗi</div>
                </div>
            </div>
        </div>
        
        <!-- Live Console -->
        <div class="live-console" id="liveConsole">
            <div class="console-header">
                Live Console Log
            </div>
            <div id="consoleContent">
                <div class="log-entry log-info">
                    <span class="log-timestamp">[${new Date().toLocaleTimeString()}]</span>
                    System ready. Chọn chức năng để bắt đầu...
                </div>
            </div>
            <div class="console-footer">
                <button class="btn btn-sm btn-outline-light" id="clearConsoleBtn">
                    Xóa log
                </button>
            </div>
        </div>
        
        <!-- Results Table -->
        <div class="results-table" id="resultsTable" style="display: none;">
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead>
                        <tr>
                            <th>Domain</th>
                            <th>Zone ID</th>
                            <th>Redirect Rulesets xóa</th>
                            <th>Page Rules xóa</th>
                            <th>Rule mới</th>
                            <th>Trạng thái</th>
                            <th>Chi tiết</th>
                            <th>AJAX</th>
                        </tr>
                    </thead>
                    <tbody id="resultsTableBody">
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    
    <script>
        class BulkRedirectManager {
            constructor() {
                this.isProcessing = false;
                this.currentResults = null;
                this.redirectBatches = null;
                this.initializeEventListeners();
                this.updateDomainCount();
            }
            
            initializeEventListeners() {
                // Domain count update
                document.getElementById('domainsInput').addEventListener('input', () => {
                    this.redirectBatches = null;
                    document.getElementById('redirectPairsStatus').textContent = '';
                    this.updateDomainCount();
                });
                document.getElementById('targetUrlInput').addEventListener('input', () => {
                    this.redirectBatches = null;
                    document.getElementById('redirectPairsStatus').textContent = '';
                });
                document.getElementById('redirectPairsInput').addEventListener('input', () => {
                    this.redirectBatches = null;
                    document.getElementById('redirectPairsStatus').textContent = '';
                });
                document.getElementById('convertRedirectPairsBtn').addEventListener('click', () => {
                    this.convertRedirectPairs();
                });
                // Action buttons
                document.getElementById('checkDomainsBtn').addEventListener('click', () => {
                    this.checkDomains();
                });
                document.getElementById('checkRulesetsBtn').addEventListener('click', () => {
                    this.checkExistingRulesets();
                });
                // Check Rule 301 button
                document.getElementById('checkRule301Btn').addEventListener('click', () => {
                    this.checkRule301();
                });
                document.getElementById('createRedirectsBtn').addEventListener('click', () => {
                    this.createBulkRedirects();
                });
                document.getElementById('deleteRulesetsBtn').addEventListener('click', () => {
                    this.deleteRulesets();
                });
                document.getElementById('exportResultsBtn').addEventListener('click', () => {
                    this.exportResults();
                });
                document.getElementById('clearConsoleBtn').addEventListener('click', () => {
                    this.clearConsole();
                });
            }

            // Kiểm tra Rule 301 cho domain (mẫu, có thể mở rộng backend)
            async checkRule301() {
                const domains = this.getDomains();
                if (domains.length === 0) {
                    Swal.fire({
                        icon: 'warning',
                        title: 'Không có domain',
                        text: 'Vui lòng nhập ít nhất một domain để kiểm tra Rule 301.',
                        confirmButtonColor: '#667eea'
                    });
                    return;
                }
                this.setProcessing(true);
                this.log(`Đang kiểm tra Rule 301 cho ${domains.length} domain(s)...`, 'info');
                // TODO: Gọi backend kiểm tra thực tế nếu cần
                setTimeout(() => {
                    this.log('🔍 (Demo) Đã kiểm tra Rule 301 cho tất cả domain.', 'success');
                    this.setProcessing(false);
                }, 1200);
            }
            
            updateDomainCount() {
                const domains = this.getDomains();
                document.getElementById('domainCount').textContent = domains.length;
            }

            convertRedirectPairs() {
                this.redirectBatches = null;
                const input = document.getElementById('redirectPairsInput').value.trim();
                if (!input) {
                    Swal.fire({
                        icon: 'warning',
                        title: 'Chưa có dữ liệu',
                        text: 'Vui lòng dán danh sách domain và URL đích.',
                        confirmButtonColor: '#667eea'
                    });
                    return;
                }

                const batches = [];
                const errors = [];
                let pendingDomains = [];
                let awaitingTargetAfterStatus = false;
                let selectedStatusCode = null;
                const lines = input.split(/\r?\n/);

                const normalizeTarget = (rawTarget, lineNumber) => {
                    const target = /^[a-z][a-z\d+.-]*:\/\//i.test(rawTarget)
                        ? rawTarget
                        : `https://${rawTarget}`;
                    try {
                        const parsedTarget = new URL(target);
                        if (!['http:', 'https:'].includes(parsedTarget.protocol) || !parsedTarget.hostname) {
                            throw new Error('Unsupported protocol');
                        }
                        return parsedTarget.href;
                    } catch (error) {
                        errors.push(`Dòng ${lineNumber}: URL đích không hợp lệ; chỉ hỗ trợ HTTP hoặc HTTPS.`);
                        return null;
                    }
                };

                const normalizeSourceDomain = domain => {
                    if (!domain || /^(?:https?:\/\/|\/)|[\s»=]/i.test(domain)) return null;
                    try {
                        const parsedDomain = new URL(`https://${domain}`);
                        const isValid = Boolean(
                            parsedDomain.hostname &&
                            !parsedDomain.username &&
                            !parsedDomain.password &&
                            !parsedDomain.port &&
                            parsedDomain.pathname === '/' &&
                            !parsedDomain.search &&
                            !parsedDomain.hash
                        );
                        return isValid ? parsedDomain.hostname.toLowerCase() : null;
                    } catch (error) {
                        return null;
                    }
                };

                const addBatch = (domains, rawTarget, lineNumber) => {
                    const target = normalizeTarget(rawTarget, lineNumber);
                    if (target && domains.length) {
                        batches.push({ domains: [...domains], target });
                    }
                };

                lines.forEach((line, index) => {
                    if (!line.trim()) return;

                    if (/^[=\s>]+$/.test(line.trim())) return;
                    if (/^\s*\[[^\]]+\]\s*$/.test(line)) return;

                    if (awaitingTargetAfterStatus) {
                        addBatch(pendingDomains, line.trim(), index + 1);
                        pendingDomains = [];
                        awaitingTargetAfterStatus = false;
                        return;
                    }

                    const statusMarker = line.trim().match(/^(301|302|307|308)$/);
                    if (statusMarker) {
                        if (pendingDomains.length === 0) {
                            errors.push(`Dòng ${index + 1}: cần có domain nguồn trước mã trạng thái ${statusMarker[1]}.`);
                            return;
                        }
                        awaitingTargetAfterStatus = true;
                        selectedStatusCode = statusMarker[1];
                        return;
                    }

                    const groupTarget = line.match(/Trỏ\s*sang\s*:\s*(.+)$/i);

                    const groupArrowTarget = line.match(/^\s*=+\s*>{1,2}\s*(.+?)\s*$/);
                    if (groupArrowTarget) {
                        if (pendingDomains.length === 0) {
                            errors.push(`Dòng ${index + 1}: không có domain nguồn trước dòng “=>>”.`);
                            return;
                        }
                        addBatch(pendingDomains, groupArrowTarget[1], index + 1);
                        pendingDomains = [];
                        return;
                    }

                    if (groupTarget) {
                        if (pendingDomains.length === 0) {
                            errors.push(`Dòng ${index + 1}: không có domain nguồn trước dòng “Trỏ sang”.`);
                            return;
                        }
                        addBatch(pendingDomains, groupTarget[1].trim(), index + 1);
                        pendingDomains = [];
                        return;
                    }

                    const arrowParts = line.split('=>');
                    if (arrowParts.length > 1) {
                        const sourceDomain = normalizeSourceDomain(arrowParts[0].trim());
                        const rawTarget = arrowParts.slice(1).join('=>').trim();
                        if (arrowParts.length !== 2 || !sourceDomain || !rawTarget) {
                            errors.push(`Dòng ${index + 1}: định dạng “domain => URL đích” không hợp lệ.`);
                            return;
                        }
                        const sourceDomains = [...pendingDomains, sourceDomain];
                        addBatch(sourceDomains, rawTarget, index + 1);
                        pendingDomains = [];
                        return;
                    }

                    const parts = line.split('»»»');
                    if (parts.length > 1) {
                        if (parts.length !== 2 || !parts[0].trim() || !parts[1].trim()) {
                            errors.push(`Dòng ${index + 1}: định dạng “domain »»» URL đích” không hợp lệ.`);
                            return;
                        }
                        if (pendingDomains.length) {
                            errors.push(`Dòng ${index + 1}: hãy kết thúc nhóm domain trước bằng “Trỏ sang: URL đích”.`);
                            return;
                        }
                        const sourceDomain = normalizeSourceDomain(parts[0].trim());
                        if (!sourceDomain) {
                            errors.push(`Dòng ${index + 1}: domain nguồn không hợp lệ.`);
                            return;
                        }
                        addBatch([sourceDomain], parts[1].trim(), index + 1);
                        return;
                    }

                    const domain = normalizeSourceDomain(line.trim());
                    if (!domain) {
                        errors.push(`Dòng ${index + 1}: domain nguồn không hợp lệ.`);
                        return;
                    }
                    pendingDomains.push(domain);
                });

                if (pendingDomains.length) {
                    errors.push(awaitingTargetAfterStatus
                        ? 'Thiếu domain đích sau mã trạng thái.'
                        : 'Nhóm domain cuối chưa có dòng “Trỏ sang: URL đích”.');
                }
                if (batches.length === 0 && errors.length === 0) {
                    errors.push('Danh sách không có nhóm chuyển hướng hợp lệ.');
                }

                if (errors.length > 0) {
                    Swal.fire({
                        icon: 'error',
                        title: 'Không thể chuyển danh sách',
                        text: errors.join('\n'),
                        confirmButtonColor: '#667eea'
                    });
                    return;
                }

                this.redirectBatches = batches;
                document.getElementById('domainsInput').value = batches[0].domains.join('\n');
                document.getElementById('targetUrlInput').value = batches[0].target;
                document.getElementById('statusCodeSelect').value = selectedStatusCode || '301';
                this.updateDomainCount();
                const totalDomains = batches.reduce((total, batch) => total + batch.domains.length, 0);
                document.getElementById('redirectPairsStatus').textContent =
                    `Đã nạp ${batches.length} nhóm, ${totalDomains} domain. Nhấn “Tạo 301 Redirect” để chạy tất cả nhóm.`;

                Swal.fire({
                    icon: 'success',
                    title: 'Đã chuyển vào form 301',
                    text: `Đã nạp ${batches.length} nhóm, tổng cộng ${totalDomains} domain nguồn.`,
                    confirmButtonColor: '#667eea'
                });
            }

            getDomains() {
                const text = document.getElementById('domainsInput').value.trim();
                return text ? text.split('\n').map(d => d.trim()).filter(d => d) : [];
            }

            escapeHtml(value) {
                return String(value).replace(/[&<>"']/g, character => ({
                    '&': '&amp;',
                    '<': '&lt;',
                    '>': '&gt;',
                    '"': '&quot;',
                    "'": '&#39;'
                })[character]);
            }
            
            log(message, type = 'info') {
                const timestamp = new Date().toLocaleTimeString();
                const consoleContent = document.getElementById('consoleContent');
                const entry = document.createElement('div');
                entry.className = `log-entry log-${type}`;
                entry.innerHTML = `<span class="log-timestamp">[${timestamp}]</span> ${message}`;
                consoleContent.appendChild(entry);
                consoleContent.scrollTop = consoleContent.scrollHeight;
            }
            
            clearConsole() {
                const consoleContent = document.getElementById('consoleContent');
                consoleContent.innerHTML = `
                    <div class="log-entry log-info">
                        <span class="log-timestamp">[${new Date().toLocaleTimeString()}]</span>
                        Console cleared. System ready.
                    </div>
                `;
            }
            
            setProcessing(processing) {
                this.isProcessing = processing;
                const buttons = document.querySelectorAll('.action-btn');
                buttons.forEach(btn => {
                    btn.disabled = processing;
                    if (processing) {
                        btn.classList.add('processing');
                    } else {
                        btn.classList.remove('processing');
                    }
                });
            }
            
            showProgress(show = true) {
                document.getElementById('progressSection').style.display = show ? 'block' : 'none';
            }
            
            updateProgress(current, total, success = 0, errors = 0) {
                const percentage = total > 0 ? Math.round((current / total) * 100) : 0;
                const progressBar = document.getElementById('progressBar');
                progressBar.style.width = percentage + '%';
                progressBar.setAttribute('aria-valuenow', percentage);
                
                document.getElementById('totalDomainsCount').textContent = total;
                document.getElementById('processedCount').textContent = current;
                document.getElementById('successCount').textContent = success;
                document.getElementById('errorCount').textContent = errors;
            }
            
            async makeRequest(action, data) {
                const formData = new FormData();
                formData.append('action', action);
                
                Object.keys(data).forEach(key => {
                    formData.append(key, data[key]);
                });
                
                const response = await fetch(window.location.href, {
                    method: 'POST',
                    body: formData
                });

                const rawText = await response.text();
                let parsed;

                try {
                    parsed = JSON.parse(rawText);
                } catch (parseError) {
                    throw new Error(`Phản hồi không hợp lệ từ server (${response.status}): ${rawText.slice(0, 200)}`);
                }

                if (!response.ok) {
                    throw new Error(parsed.error || `HTTP ${response.status}`);
                }

                return parsed;
            }

            async requestSingleDomain(mode, domain, extraData = {}) {
                return this.makeRequest('process_single_domain', {
                    mode,
                    domain,
                    ...extraData
                });
            }

            async checkDomains() {
                const domains = this.getDomains();
                
                if (domains.length === 0) {
                    Swal.fire({
                        icon: 'warning',
                        title: 'Không có domain',
                        text: 'Vui lòng nhập ít nhất một domain để kiểm tra.',
                        confirmButtonColor: '#667eea'
                    });
                    return;
                }
                
                this.setProcessing(true);
                this.log(`Đang kiểm tra ${domains.length} domain(s)...`, 'info');
                this.showProgress(true);
                this.updateProgress(0, domains.length);
                
                try {
                    const result = await this.makeRequest('check_domains', {
                        domains: domains.join('\n')
                    });
                    
                    if (result.success) {
                        let foundCount = 0;
                        let notFoundCount = 0;
                        
                        this.displayResultsTable(result.results.map(r => ({
                            domain: r.domain,
                            zone_id: r.zone_id || 'N/A',
                            old_rulesets_deleted: 'N/А',
                            old_page_rules_deleted: 'N/A',
                            new_ruleset_created: 'N/A',
                            success: r.status === 'found',
                            message: r.message
                        })));
                        
                        result.results.forEach(r => {
                            if (r.status === 'found') {
                                this.log(`✅ ${r.domain} → Zone ID: ${r.zone_id}`, 'success');
                                foundCount++;
                            } else {
                                this.log(`❌ ${r.domain} → Zone không tìm thấy`, 'error');
                                notFoundCount++;
                            }
                        });
                        
                        this.updateProgress(domains.length, domains.length, foundCount, notFoundCount);
                        this.log(`Hoàn thành kiểm tra. Tìm thấy: ${foundCount}, Không tìm thấy: ${notFoundCount}`, 'info');
                        
                        this.currentResults = result.results;
                    } else {
                        this.log(`❌ Lỗi: ${result.error}`, 'error');
                    }
                } catch (error) {
                    this.log(`❌ Lỗi kết nối: ${error.message}`, 'error');
                } finally {
                    this.setProcessing(false);
                }
            }
            
            async checkExistingRulesets() {
                const domains = this.getDomains();
                
                if (domains.length === 0) {
                    Swal.fire({
                        icon: 'warning',
                        title: 'Không có domain',
                        text: 'Vui lòng nhập ít nhất một domain để kiểm tra.',
                        confirmButtonColor: '#667eea'
                    });
                    return;
                }
                
                this.setProcessing(true);
                this.log(`Đang kiểm tra redirect rules hiện có cho ${domains.length} domain(s)...`, 'info');
                this.showProgress(true);
                this.updateProgress(0, domains.length);
                
                try {
                    const result = await this.makeRequest('check_existing_rulesets', {
                        domains: domains.join('\n')
                    });
                    
                    if (result.success) {
                        let totalRulesets = 0;
                        
                        result.results.forEach(r => {
                            totalRulesets += r.count;
                            if (r.count > 0) {
                                this.log(`🔍 ${r.domain} có ${r.count} redirect ruleset(s):`, 'warning');
                                r.rulesets.forEach(ruleset => {
                                    this.log(`   → ${ruleset.name} (ID: ${ruleset.id}) - ${ruleset.rules_count} rule(s)`, 'info');
                                });
                            } else {
                                this.log(`✅ ${r.domain} không có redirect ruleset nào`, 'success');
                            }
                        });
                        
                        this.updateProgress(domains.length, domains.length, result.results.length - totalRulesets, 0);
                        this.log(`Hoàn thành kiểm tra. Tổng cộng: ${totalRulesets} redirect ruleset(s) tìm thấy`, 'info');
                        
                        this.currentResults = result.results;
                    } else {
                        this.log(`❌ Lỗi: ${result.error}`, 'error');
                    }
                } catch (error) {
                    this.log(`❌ Lỗi kết nối: ${error.message}`, 'error');
                } finally {
                    this.setProcessing(false);
                }
            }
            
            async createBulkRedirects() {
                const domains = this.getDomains();
                const targetUrl = document.getElementById('targetUrlInput').value.trim();
                const batches = this.redirectBatches || [{ domains, target: targetUrl }];
                const allIncomingRequests = document.getElementById('allIncomingRequestsCheck').checked;
                
                const totalDomains = batches.reduce((total, batch) => total + batch.domains.length, 0);
                if (totalDomains === 0) {
                    Swal.fire({
                        icon: 'warning',
                        title: 'Không có domain',
                        text: 'Vui lòng nhập ít nhất một domain để tạo redirect.',
                        confirmButtonColor: '#667eea'
                    });
                    return;
                }
                
                if (!this.redirectBatches && !targetUrl) {
                    Swal.fire({
                        icon: 'warning',
                        title: 'Thiếu URL đích',
                        text: 'Vui lòng nhập URL đích.',
                        confirmButtonColor: '#667eea'
                    });
                    return;
                }
                
                // Confirmation dialog
                const confirm = await Swal.fire({
                    title: 'Xác nhận tạo Redirect',
                    html: `
                        <div class="text-start">
                            <p><strong>Số nhóm:</strong> ${batches.length}</p>
                            <p><strong>Tổng domain:</strong> ${totalDomains}</p>
                            <div class="text-start border rounded p-2 mb-3" style="max-height: 40vh; overflow-y: auto;">
                                ${batches.map((batch, index) => `
                                    <div class="mb-3">
                                        <strong>Nhóm ${index + 1} (${batch.domains.length} domain)</strong><br>
                                        Đích: <code>${this.escapeHtml(batch.target)}</code>
                                        <ul class="mb-0">
                                            ${batch.domains.map(domain => `<li><code>${this.escapeHtml(domain)}</code></li>`).join('')}
                                        </ul>
                                    </div>
                                `).join('')}
                            </div>
                            <p><strong>All incoming requests:</strong> ${allIncomingRequests ? 'Có (expression: true)' : 'Không (domain matching)'}</p>
                            <p><strong>Mã trạng thái:</strong> ${document.getElementById('statusCodeSelect').value}</p>
                            <p><strong>Giữ nguyên path:</strong> ${document.getElementById('preservePathCheck').checked ? 'Có' : 'Không'}</p>
                            <p><strong>Giữ nguyên query:</strong> ${document.getElementById('preserveQueryCheck').checked ? 'Có' : 'Không'}</p>
                            <p><strong>Xóa rules cũ:</strong> ${document.getElementById('deleteOldCheck').checked ? 'Có' : 'Không'}</p>
                        </div>
                    `,
                    icon: 'question',
                    showCancelButton: true,
                    confirmButtonText: 'Bắt đầu tạo',
                    cancelButtonText: 'Hủy',
                    confirmButtonColor: '#667eea',
                    cancelButtonColor: '#d33'
                });
                
                if (!confirm.isConfirmed) return;
                
                this.setProcessing(true);
                this.log(`🚀 Bắt đầu tạo ${batches.length} nhóm redirect cho ${totalDomains} domain(s)...`, 'info');
                this.showProgress(true);
                this.updateProgress(0, totalDomains);
                
                const startTime = Date.now();
                
                try {
                    this.log('⚡ Chế độ AJAX: xử lý từng domain theo từng request...', 'info');

                    const processedDomains = [];
                    const failedDomains = [];
                    let successCount = 0;
                    let failedCount = 0;
                    let totalOldDeleted = 0;
                    let totalOldPageRulesDeleted = 0;
                    let totalNewCreated = 0;
                    let processedCount = 0;

                    for (let batchIndex = 0; batchIndex < batches.length; batchIndex++) {
                        const batch = batches[batchIndex];
                        this.log(`📦 Nhóm ${batchIndex + 1}/${batches.length} → ${this.escapeHtml(batch.target)} (${batch.domains.length} domain)`, 'info');

                        for (const domain of batch.domains) {

                            try {
                                const response = await this.requestSingleDomain('create', domain, {
                                    target_url: batch.target,
                                    status_code: document.getElementById('statusCodeSelect').value,
                                    preserve_path: document.getElementById('preservePathCheck').checked ? '1' : '',
                                    preserve_query: document.getElementById('preserveQueryCheck').checked ? '1' : '',
                                    delete_old: document.getElementById('deleteOldCheck').checked ? '1' : '',
                                    all_incoming_requests: allIncomingRequests ? '1' : ''
                                });

                                const payload = response.result || {};
                                const payloadDomain = Array.isArray(payload.processed_domains) && payload.processed_domains.length
                                    ? payload.processed_domains[0]
                                    : null;

                                const isSuccess = Boolean(payloadDomain?.success ?? response.success);
                                const oldDeleted = Number(payloadDomain?.old_rulesets_deleted ?? payload.total_old_rulesets_deleted ?? 0);
                                const oldPageRulesDeleted = Number(payloadDomain?.old_page_rules_deleted ?? payload.total_old_page_rules_deleted ?? 0);
                                const newRuleId = payloadDomain?.new_ruleset_id || payload.new_ruleset_id || 'Không';
                                const newCreated = Boolean(payloadDomain?.new_ruleset_created || (newRuleId && newRuleId !== 'Không'));
                                const apiErrors = Array.isArray(payload.errors)
                                    ? payload.errors.map(error => error.error || error.message || JSON.stringify(error)).join(' | ')
                                    : '';
                                const message = payloadDomain?.error || response.error || payload.error || apiErrors || (isSuccess ? 'Thành công' : 'Cloudflare không trả về lý do lỗi.');
                                const cachePurge = response.cache_purge;
                                const resultMessage = cachePurge
                                    ? `${batch.target} — ${message}; Purge Cache ${cachePurge.success ? 'thành công' : `thất bại: ${cachePurge.message}`}`
                                    : `${batch.target} — ${message}`;

                                processedDomains.push({
                                    domain,
                                    zone_id: response.zone_id || payloadDomain?.zone_id || 'N/A',
                                    old_rulesets_deleted: oldDeleted,
                                    old_page_rules_deleted: oldPageRulesDeleted,
                                    new_ruleset_created: newCreated ? newRuleId : 'Không',
                                    success: isSuccess,
                                    message: resultMessage
                                });

                                if (isSuccess) {
                                    successCount++;
                                    totalOldDeleted += oldDeleted;
                                    totalOldPageRulesDeleted += oldPageRulesDeleted;
                                    if (newCreated) {
                                        totalNewCreated++;
                                    }
                                    this.log(`✅ ${this.escapeHtml(domain)} → ${this.escapeHtml(batch.target)}`, 'success');
                                    if (cachePurge?.success) {
                                        this.log(`   🧹 Purge Cache thành công cho ${this.escapeHtml(domain)}`, 'success');
                                    } else if (cachePurge) {
                                        this.log(`   ⚠️ Tạo 301 thành công nhưng Purge Cache thất bại: ${this.escapeHtml(cachePurge.message)}`, 'warning');
                                    }
                                    if (oldDeleted > 0) {
                                        this.log(`   🗑️ Xóa ${oldDeleted} ruleset(s) cũ`, 'info');
                                    }
                                    if (oldPageRulesDeleted > 0) {
                                        this.log(`   🗑️ Xóa ${oldPageRulesDeleted} Page Rule(s) cũ`, 'info');
                                    }
                                    if (newCreated) {
                                        this.log(`   🆕 Tạo ruleset mới: ${newRuleId}`, 'success');
                                    }
                                } else {
                                    failedCount++;
                                    failedDomains.push({ domain, target: batch.target, error: message });
                                    this.log(`❌ ${domain} → Lỗi: ${message}`, 'error');
                                }
                            } catch (domainError) {
                                failedCount++;
                                failedDomains.push({ domain, target: batch.target, error: domainError.message });
                                processedDomains.push({
                                    domain,
                                    zone_id: 'N/A',
                                    old_rulesets_deleted: 0,
                                    old_page_rules_deleted: 0,
                                    new_ruleset_created: 'Không',
                                    success: false,
                                    message: `${batch.target} — ${domainError.message}`
                                });
                                this.log(`❌ ${domain} → Lỗi kết nối: ${domainError.message}`, 'error');
                            }

                            processedCount++;
                            this.updateProgress(processedCount, totalDomains, successCount, failedCount);
                        }
                    }

                    this.displayResultsTable(processedDomains);
                    this.currentResults = processedDomains;

                    const endTime = Date.now();
                    const duration = ((endTime - startTime) / 1000).toFixed(2);

                    this.log('📊 Tổng kết:', 'info');
                    this.log(`   • Tổng nhóm: ${batches.length}`, 'info');
                    this.log(`   • Tổng domain: ${totalDomains}`, 'info');
                    this.log(`   • Thành công: ${successCount}`, 'success');
                    this.log(`   • Thất bại: ${failedCount}`, failedCount > 0 ? 'error' : 'info');
                    this.log(`   • Xóa rulesets cũ: ${totalOldDeleted}`, 'info');
                    this.log(`   • Xóa Page Rules cũ: ${totalOldPageRulesDeleted}`, 'info');
                    this.log(`   • Tạo rulesets mới: ${totalNewCreated}`, 'info');
                    this.log(`   • Thời gian xử lý: ${duration}s`, 'info');

                    const failureDetails = failedDomains.length
                        ? `
                            <div class="text-start mt-3 p-2 border rounded" style="max-height: 35vh; overflow-y: auto;">
                                <strong>Chi tiết lỗi:</strong>
                                <ul class="mb-0">
                                    ${failedDomains.map(item => `<li class="mb-2"><strong>${this.escapeHtml(item.domain)}</strong> → ${this.escapeHtml(item.target)}<br>${this.escapeHtml(item.error)}</li>`).join('')}
                                </ul>
                            </div>
                        `
                        : '';

                    Swal.fire({
                        icon: successCount > 0 ? 'success' : 'warning',
                        title: 'Hoàn thành!',
                        html: `
                            <div class="text-start">
                                <p>✅ Thành công: <strong>${successCount}</strong> domain(s)</p>
                                <p>❌ Thất bại: <strong>${failedCount}</strong> domain(s)</p>
                                <p>⏱️ Thời gian: <strong>${duration}s</strong></p>
                                ${failureDetails}
                            </div>
                        `,
                        confirmButtonColor: '#667eea'
                    });
                } catch (error) {
                    this.log(`❌ Lỗi kết nối: ${error.message}`, 'error');
                } finally {
                    this.setProcessing(false);
                }
            }
            
            async deleteRulesets() {
                const domains = this.getDomains();
                
                if (domains.length === 0) {
                    Swal.fire({
                        icon: 'warning',
                        title: 'Không có domain',
                        text: 'Vui lòng nhập ít nhất một domain để xóa rules.',
                        confirmButtonColor: '#667eea'
                    });
                    return;
                }
                
                const confirm = await Swal.fire({
                    title: 'Xác nhận xóa',
                    text: `Bạn có chắc muốn xóa tất cả redirect rulesets và Page Rules cho ${domains.length} domain(s)?`,
                    icon: 'warning',
                    showCancelButton: true,
                    confirmButtonText: 'Xóa hết',
                    cancelButtonText: 'Hủy',
                    confirmButtonColor: '#d33',
                    cancelButtonColor: '#6c757d'
                });
                
                if (!confirm.isConfirmed) return;
                
                this.setProcessing(true);
                this.log(`🗑️ Đang xóa redirect rulesets và Page Rules cho ${domains.length} domain(s)...`, 'warning');
                this.showProgress(true);
                this.updateProgress(0, domains.length);
                
                try {
                    const result = await this.makeRequest('delete_rulesets', {
                        domains: domains.join('\n')
                    });
                    
                    if (result.success) {
                        let totalDeleted = 0;
                        let totalPageRulesDeleted = 0;
                        let successCount = 0;
                        let errorCount = 0;
                        
                        result.results.forEach(r => {
                            if (r.success) {
                                successCount++;
                                totalDeleted += r.deleted_ruleset_count || 0;
                                totalPageRulesDeleted += r.deleted_page_rule_count || 0;
                                if (r.deleted_count > 0) {
                                    this.log(`✅ ${r.domain} → Xóa ${r.deleted_ruleset_count || 0} redirect ruleset(s) và ${r.deleted_page_rule_count || 0} Page Rule(s)`, 'success');
                                    r.deleted_rulesets.forEach(rs => {
                                        this.log(`   🗑️ Đã xóa: ${rs.name} (${rs.id})`, 'info');
                                    });
                                    (r.deleted_page_rules || []).forEach(rule => {
                                        const target = rule.targets?.[0]?.constraint?.value || rule.id;
                                        this.log(`   🗑️ Đã xóa Page Rule: ${target} (${rule.id})`, 'info');
                                    });
                                } else {
                                    const skipped = (r.skipped_rulesets || []).length;
                                    if (skipped > 0) {
                                        this.log(`ℹ️ ${r.domain} → Không xóa được ${skipped} ruleset bảo vệ, đã bỏ qua`, 'warning');
                                    } else {
                                        this.log(`ℹ️ ${r.domain} → Không có ruleset nào để xóa`, 'info');
                                    }
                                }
                            } else {
                                errorCount++;
                                totalDeleted += r.deleted_ruleset_count || 0;
                                totalPageRulesDeleted += r.deleted_page_rule_count || 0;
                                if ((r.deleted_ruleset_count || 0) + (r.deleted_page_rule_count || 0) > 0) {
                                    this.log(`⚠️ ${r.domain} → Đã xóa một phần: ${r.deleted_ruleset_count || 0} redirect ruleset(s), ${r.deleted_page_rule_count || 0} Page Rule(s)`, 'warning');
                                }
                                const detailErrors = Array.isArray(r.errors) && r.errors.length
                                    ? r.errors.map(e => e.error || JSON.stringify(e)).join(' | ')
                                    : '';
                                const errorMessage = r.error || detailErrors || 'Unknown error';
                                this.log(`❌ ${r.domain} → Lỗi: ${errorMessage}`, 'error');
                            }
                        });
                        
                        this.updateProgress(domains.length, domains.length, successCount, errorCount);
                        this.log(`Hoàn thành xóa. Tổng cộng xóa ${totalDeleted} ruleset(s) và ${totalPageRulesDeleted} Page Rule(s)`, 'info');
                        
                        this.currentResults = result.results;
                    } else {
                        this.log(`❌ Lỗi: ${result.error}`, 'error');
                    }
                } catch (error) {
                    this.log(`❌ Lỗi kết nối: ${error.message}`, 'error');
                } finally {
                    this.setProcessing(false);
                }
            }

            displayResultsTable(results) {
                const table = document.getElementById('resultsTable');
                const tbody = document.getElementById('resultsTableBody');
                
                tbody.innerHTML = '';
                
                results.forEach(result => {
                    const row = document.createElement('tr');
                    const escapedDomain = String(result.domain || '').replace(/'/g, "\\'");
                    
                    const statusClass = result.success ? 'status-success' : 'status-error';
                    const statusText = result.success ? 'Thành công' : 'Thất bại';
                    const escapedMessage = this.escapeHtml(result.message || '');
                    
                    row.innerHTML = `
                        <td><strong>${result.domain}</strong></td>
                        <td><code>${result.zone_id}</code></td>
                        <td><span class="badge bg-info">${result.old_rulesets_deleted ?? 0}</span></td>
                        <td><span class="badge bg-info">${result.old_page_rules_deleted ?? 0}</span></td>
                        <td><span class="badge ${result.new_ruleset_created !== 'N/A' && result.new_ruleset_created !== 'Không' ? 'bg-success' : 'bg-secondary'}">${result.new_ruleset_created}</span></td>
                        <td><span class="status-badge ${statusClass}">${statusText}</span></td>
                        <td><small class="text-muted">${escapedMessage}</small></td>
                        <td>
                            <div class="d-flex flex-wrap gap-1 justify-content-center">
                                <button type="button" class="btn btn-sm btn-outline-info" onclick="window.bulkRedirectManager.runSingleDomainAction('check', '${escapedDomain}')">Kiểm tra</button>
                                <button type="button" class="btn btn-sm btn-outline-success" onclick="window.bulkRedirectManager.runSingleDomainAction('create', '${escapedDomain}')">Redirect</button>
                                <button type="button" class="btn btn-sm btn-outline-danger" onclick="window.bulkRedirectManager.runSingleDomainAction('delete', '${escapedDomain}')">Xóa</button>
                            </div>
                        </td>
                    `;
                    
                    tbody.appendChild(row);
                });
                
                table.style.display = 'block';
            }

            async runSingleDomainAction(mode, domain) {
                if (!domain) {
                    Swal.fire({
                        icon: 'warning',
                        title: 'Thiếu domain',
                        text: 'Không xác định được domain để xử lý.',
                        confirmButtonColor: '#667eea'
                    });
                    return;
                }

                const extraData = {
                    target_url: document.getElementById('targetUrlInput').value.trim(),
                    status_code: document.getElementById('statusCodeSelect').value,
                    preserve_path: document.getElementById('preservePathCheck').checked ? '1' : '',
                    preserve_query: document.getElementById('preserveQueryCheck').checked ? '1' : '',
                    delete_old: document.getElementById('deleteOldCheck').checked ? '1' : '',
                    all_incoming_requests: document.getElementById('allIncomingRequestsCheck').checked ? '1' : ''
                };

                if (mode === 'create' && !extraData.target_url) {
                    Swal.fire({
                        icon: 'warning',
                        title: 'Thiếu URL đích',
                        text: 'Vui lòng nhập URL đích trước khi tạo redirect cho từng domain.',
                        confirmButtonColor: '#667eea'
                    });
                    return;
                }

                this.setProcessing(true);
                this.showProgress(true);
                this.updateProgress(0, 1, 0, 0);

                this.log(`⚡ AJAX ${mode} cho ${domain}`, 'info');

                try {
                    const result = await this.requestSingleDomain(mode, domain, extraData);

                    if (mode === 'check') {
                        if (result.success) {
                            this.updateProgress(1, 1, 1, 0);
                            this.log(`✅ ${domain} → Zone ID: ${result.zone_id}`, 'success');
                            Swal.fire({
                                icon: 'success',
                                title: 'Kiểm tra thành công',
                                text: `${domain} → ${result.zone_id}`,
                                confirmButtonColor: '#667eea'
                            });
                        } else {
                            this.updateProgress(1, 1, 0, 1);
                            this.log(`❌ ${domain} → ${result.error || 'Zone không tìm thấy'}`, 'error');
                            Swal.fire({
                                icon: 'error',
                                title: 'Kiểm tra thất bại',
                                text: result.error || 'Zone không tìm thấy',
                                confirmButtonColor: '#667eea'
                            });
                        }
                        return;
                    }

                    if (mode === 'delete') {
                        if (result.success) {
                            this.updateProgress(1, 1, 1, 0);
                            this.log(`🗑️ ${domain} → Đã xóa ${result.deleted_ruleset_count || 0} redirect ruleset(s) và ${result.deleted_page_rule_count || 0} Page Rule(s)`, 'success');
                        } else {
                            this.updateProgress(1, 1, 0, 1);
                            this.log(`❌ ${domain} → ${result.error || 'Xóa thất bại'}`, 'error');
                        }
                        Swal.fire({
                            icon: result.success ? 'success' : 'error',
                            title: result.success ? 'Xóa hoàn tất' : 'Xóa thất bại',
                            text: result.success
                                ? `${domain} → ${result.deleted_ruleset_count || 0} redirect ruleset(s), ${result.deleted_page_rule_count || 0} Page Rule(s)`
                                : (result.error || 'Không rõ lỗi'),
                            confirmButtonColor: '#667eea'
                        });
                        return;
                    }

                    if (mode === 'create') {
                        const payload = result.result || {};
                        if (result.success || payload.success) {
                            this.updateProgress(1, 1, 1, 0);
                            this.log(`✅ ${domain} → Tạo redirect thành công`, 'success');
                            Swal.fire({
                                icon: 'success',
                                title: 'Tạo redirect thành công',
                                text: domain,
                                confirmButtonColor: '#667eea'
                            });
                        } else {
                            this.updateProgress(1, 1, 0, 1);
                            const errorText = payload.error || result.error || 'Tạo redirect thất bại';
                            this.log(`❌ ${domain} → ${errorText}`, 'error');
                            Swal.fire({
                                icon: 'error',
                                title: 'Tạo redirect thất bại',
                                text: errorText,
                                confirmButtonColor: '#667eea'
                            });
                        }
                    }
                } catch (error) {
                    this.updateProgress(1, 1, 0, 1);
                    this.log(`❌ ${domain} → ${error.message}`, 'error');
                    Swal.fire({
                        icon: 'error',
                        title: 'Lỗi AJAX',
                        text: error.message,
                        confirmButtonColor: '#667eea'
                    });
                } finally {
                    setTimeout(() => {
                        this.setProcessing(false);
                    }, 250);
                }
            }
            
            exportResults() {
                if (!this.currentResults) {
                    Swal.fire({
                        icon: 'info',
                        title: 'Không có dữ liệu',
                        text: 'Chưa có kết quả nào để xuất.',
                        confirmButtonColor: '#667eea'
                    });
                    return;
                }
                
                const csvContent = this.generateCSV(this.currentResults);
                const blob = new Blob([csvContent], { type: 'text/csv;charset=utf-8;' });
                const link = document.createElement('a');
                
                if (link.download !== undefined) {
                    const url = URL.createObjectURL(blob);
                    link.setAttribute('href', url);
                    link.setAttribute('download', `bulk_redirect_results_${new Date().getTime()}.csv`);
                    link.style.visibility = 'hidden';
                    document.body.appendChild(link);
                    link.click();
                    document.body.removeChild(link);
                    
                    this.log('📄 Xuất file CSV thành công', 'success');
                }
            }
            
            generateCSV(results) {
                const headers = ['Domain', 'Zone ID', 'Old Redirect Rulesets Deleted', 'Old Page Rules Deleted', 'New Ruleset Created', 'Success', 'Message'];
                const rows = results.map(r => [
                    r.domain || '',
                    r.zone_id || '',
                    r.old_rulesets_deleted || 0,
                    r.old_page_rules_deleted || 0,
                    r.new_ruleset_created || 'N/A',
                    r.success ? 'Yes' : 'No',
                    (r.message || r.error || '').replace(/"/g, '""')
                ]);
                
                const csvContent = [headers, ...rows]
                    .map(row => row.map(field => `"${field}"`).join(','))
                    .join('\n');
                
                return csvContent;
            }
        }

        // Menu Toggle Functionality
        function initializeMenuToggle() {
            const sidebarToggle = document.getElementById('sidebarToggle');
            
            if (sidebarToggle) {
                // Remove any existing event listeners first
                sidebarToggle.removeEventListener('click', arguments.callee);
                
                // Override existing toggle functionality
                sidebarToggle.addEventListener('click', function(e) {
                    e.preventDefault();
                    e.stopPropagation();
                    
                    // Toggle collapsed state on body
                    document.body.classList.toggle('sidebar-collapsed');
                    
                    // Update toggle button text with better icons
                    const isCollapsed = document.body.classList.contains('sidebar-collapsed');
                    this.innerHTML = isCollapsed ? '☰' : '✖';
                    this.setAttribute('title', isCollapsed ? 'Hiện Menu' : 'Ẩn Menu');
                    
                    // Add matrix effect on toggle
                    this.style.boxShadow = '0 0 20px rgba(0, 255, 0, 0.8)';
                    this.style.background = 'rgba(0, 255, 0, 0.3)';
                    setTimeout(() => {
                        this.style.boxShadow = '0 0 15px rgba(0, 255, 0, 0.6)';
                        this.style.background = 'rgba(0, 255, 0, 0.1)';
                    }, 300);
                    
                    // Log action
                    console.log('📱 Menu toggle:', isCollapsed ? 'Hidden' : 'Shown');
                });
                
                // Keyboard shortcut (Ctrl + B or Ctrl + M)
                document.addEventListener('keydown', function(e) {
                    if ((e.ctrlKey && e.key === 'b') || (e.ctrlKey && e.key === 'm')) {
                        e.preventDefault();
                        sidebarToggle.click();
                    }
                });
                
                // Initialize button appearance
                sidebarToggle.innerHTML = '✖';
                sidebarToggle.setAttribute('title', 'Ẩn Menu (Ctrl+B)');
                sidebarToggle.style.fontSize = '1.2rem';
                sidebarToggle.style.color = '#00ff00';
                sidebarToggle.style.border = '2px solid #00ff00';
                sidebarToggle.style.background = 'rgba(0, 255, 0, 0.1)';
                
                // Add hover effects
                sidebarToggle.addEventListener('mouseenter', function() {
                    this.style.transform = 'scale(1.1) rotate(90deg)';
                    this.style.boxShadow = '0 0 15px rgba(0, 255, 0, 0.6)';
                });
                
                sidebarToggle.addEventListener('mouseleave', function() {
                    this.style.transform = 'scale(1) rotate(0deg)';
                    this.style.boxShadow = '';
                });
                
                // Initial log
                console.log('🚀 Menu toggle initialized. Press Ctrl+B to toggle sidebar.');
            } else {
                console.error('❌ Sidebar toggle button not found!');
            }
        }

        // Initialize the application
        document.addEventListener('DOMContentLoaded', () => {
            // Small delay to ensure all DOM elements are ready
            setTimeout(() => {
                // Initialize menu toggle first
                initializeMenuToggle();
                // Then initialize main app
                window.bulkRedirectManager = new BulkRedirectManager();
            }, 100);
        }); 
    </script>
    
    <!-- Tactical Gaming Interface -->
    <script src="assets/js/tactical-interface.js"></script>
    
</body>
</html>
<?php
// Lấy danh sách site và ID từ aaPanel (API v2)
// if (
//     $_SERVER['REQUEST_METHOD'] === 'POST' &&
//     isset($_POST['api_url'], $_POST['api_key'], $_POST['get_site_list'])
// ) {
//     $api_url = trim($_POST['api_url']);
//     $api_key = trim($_POST['api_key']);
//     $now = time();
//     $token = md5($now . md5($api_key));
//     $postData = [
//         'request_time' => $now,
//         'request_token' => $token
//     ];
//     $endpoint = rtrim($api_url, '/') . '/v2/site?action=get_site_list';
//     $ch = curl_init($endpoint);
//     curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
//     curl_setopt($ch, CURLOPT_POST, true);
//     curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($postData));
//     curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
//     curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
//     curl_setopt($ch, CURLOPT_HTTPHEADER, ['x-http-token: ' . $api_key]);
//     $response = curl_exec($ch);
//     curl_close($ch);
//     $data = json_decode($response, true);
//     header('Content-Type: application/json; charset=utf-8');
//     // Xuất bảng domain và s_id nếu có
//     $table = [];
//     if (isset($data['data']) && is_array($data['data'])) {
//         foreach ($data['data'] as $site) {
//             $table[] = [
//                 'domain' => $site['domain'] ?? '',
//                 's_id' => $site['id'] ?? $site['s_id'] ?? ''
//             ];
//         }
//     }
//     echo json_encode([
//         'raw_response' => $response,
//         'json' => $data,
//         'site_table' => $table
//     ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
//     exit;
// }



// 1. Kiểm tra API Key hợp lệ
if (
    $_SERVER['REQUEST_METHOD'] === 'POST' &&
    isset($_POST['api_url'], $_POST['api_key'], $_POST['test_api_key'])
) {
    $api_url = trim($_POST['api_url']);
    $api_key = trim($_POST['api_key']);
    $now = time();
    $token = md5($now . md5($api_key));
    $postData = [
        'request_time' => $now,
        'request_token' => $token
    ];
    $endpoint = rtrim($api_url, '/') . '/system?action=GetSystemTotal';
    $ch = curl_init($endpoint);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($postData));
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
    $response = curl_exec($ch);
    curl_close($ch);
    $data = json_decode($response, true);
    header('Content-Type: application/json; charset=utf-8');
    $isValid = false;
    $msg = '';
    // Nếu trả về có các trường hệ thống thì coi là hợp lệ
    if (
        (isset($data['status']) && $data['status'] === true) ||
        (isset($data['memTotal']) && isset($data['cpuNum']) && isset($data['version']))
    ) {
        $isValid = true;
        $msg = 'API Key hợp lệ!';
    } elseif (isset($data['msg'])) {
        $msg = $data['msg'];
    } elseif (isset($data['error'])) {
        $msg = $data['error'];
    } elseif (isset($data['status']) && $data['status'] === false) {
        $msg = 'API Key không hợp lệ!';
    } else {
        $msg = 'Không xác định được trạng thái API Key!';
    }
    echo json_encode([
        'raw_response' => $response,
        'json' => $data,
        'valid' => $isValid,
        'message' => $msg
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    exit;
}


// 2. Lấy danh sách site và ID từ aaPanel (API v2)
if (
    $_SERVER['REQUEST_METHOD'] === 'POST' &&
    isset($_POST['api_url'], $_POST['api_key'], $_POST['get_site_list'])
) {
    $api_url = trim($_POST['api_url']);
    $api_key = trim($_POST['api_key']);
    $now = time();
    $token = md5($now . md5($api_key));
    $postData = [
        'request_time' => $now,
        'request_token' => $token
    ];
    $endpoint = rtrim($api_url, '/') . '/v2/site?action=get_site_list';
    $ch = curl_init($endpoint);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($postData));
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['x-http-token: ' . $api_key]);
    $response = curl_exec($ch);
    curl_close($ch);
    $data = json_decode($response, true);
    header('Content-Type: application/json; charset=utf-8');
    // Xuất bảng domain và s_id nếu có
    $table = [];
    // Kết hợp cả hai nguồn 'data' và 'message' để lấy domain và s_id
    $table = [];
    $seen = [];
    $sources = [];
    if (isset($data['data']) && is_array($data['data'])) {
        $sources[] = $data['data'];
    }
    if (isset($data['message']) && is_array($data['message'])) {
        $sources[] = $data['message'];
    }
    foreach ($sources as $source) {
        foreach ($source as $site) {
            $domain = $site['domain'] ?? $site['name'] ?? '';
            $s_id = $site['id'] ?? $site['s_id'] ?? '';
            $key = $domain . '|' . $s_id;
            if ($domain && $s_id && !isset($seen[$key])) {
                $table[] = [
                    'domain' => $domain,
                    's_id' => $s_id
                ];
                $seen[$key] = true;
            }
        }
    }
    echo json_encode([
        'raw_response' => $response,
        'json' => $data,
        'site_table' => $table
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    exit;
}


// Chuẩn hóa domain để so khớp ổn định (bỏ protocol, path, port, www)
function normalizeDomainForLookup($domain) {
    $domain = trim((string)$domain);
    if ($domain === '') {
        return '';
    }
    if (!preg_match('#^https?://#i', $domain)) {
        $domain = 'http://' . $domain;
    }
    $host = parse_url($domain, PHP_URL_HOST);
    if (!$host) {
        return '';
    }
    $host = strtolower(trim($host));
    $host = preg_replace('/\.+$/', '', $host);
    if (strpos($host, 'www.') === 0) {
        $host = substr($host, 4);
    }
    return $host;
}

// Tách danh sách domain từ textarea: hỗ trợ xuống dòng, dấu phẩy, chấm phẩy, khoảng trắng
function parseDomainListInput($input) {
    $parts = preg_split('/[\r\n,;\s]+/', (string)$input);
    $domains = [];
    $seen = [];
    foreach ($parts as $part) {
        $normalized = normalizeDomainForLookup($part);
        if ($normalized !== '' && !isset($seen[$normalized])) {
            $domains[] = $normalized;
            $seen[$normalized] = true;
        }
    }
    return $domains;
}

// Lấy danh sách site từ aaPanel và trả về bảng domain/s_id
function fetchAaPanelSiteTable($api_url, $api_key) {
    $api_url = rtrim($api_url, '/');
    $endpoint = $api_url . '/v2/site?action=get_site_list';
    $now = time();
    $token = md5($now . md5($api_key));
    $postData = [
        'request_time' => $now,
        'request_token' => $token
    ];
    $ch = curl_init($endpoint);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($postData));
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['x-http-token: ' . $api_key]);
    $response = curl_exec($ch);
    curl_close($ch);
    $data = json_decode($response, true);

    $table = [];
    $seen = [];
    $sources = [];
    if (isset($data['data']) && is_array($data['data'])) {
        $sources[] = $data['data'];
    }
    if (isset($data['message']) && is_array($data['message'])) {
        $sources[] = $data['message'];
    }
    foreach ($sources as $source) {
        foreach ($source as $site) {
            $domain = $site['domain'] ?? $site['name'] ?? '';
            $s_id = $site['id'] ?? $site['s_id'] ?? '';
            $key = $domain . '|' . $s_id;
            if ($domain && $s_id && !isset($seen[$key])) {
                $table[] = [
                    'domain' => $domain,
                    's_id' => $s_id
                ];
                $seen[$key] = true;
            }
        }
    }

    return [
        'raw_response' => $response,
        'json' => $data,
        'site_table' => $table
    ];
}


// 3. Reset hàng loạt theo danh sách domain + mật khẩu mới
if (
    $_SERVER['REQUEST_METHOD'] === 'POST' &&
    isset($_POST['api_url'], $_POST['api_key']) &&
    (
        isset($_POST['reset_by_domains']) ||
        isset($_POST['domains']) ||
        isset($_POST['domain_list'])
    )
) {
    $api_url = trim($_POST['api_url']);
    $api_key = trim($_POST['api_key']);
    $domainInput = trim($_POST['domains'] ?? $_POST['domain_list'] ?? '');
    $newPassword = trim($_POST['new_password'] ?? $_POST['pass'] ?? '');
    $wpUser = trim($_POST['user'] ?? 'admin');

    header('Content-Type: application/json; charset=utf-8');

    if ($domainInput === '') {
        echo json_encode([
            'status' => 'error',
            'message' => 'Vui lòng nhập danh sách domains.'
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        exit;
    }
    if ($newPassword === '') {
        echo json_encode([
            'status' => 'error',
            'message' => 'Vui lòng nhập mật khẩu mới.'
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        exit;
    }

    $domains = parseDomainListInput($domainInput);
    if (empty($domains)) {
        echo json_encode([
            'status' => 'error',
            'message' => 'Không có domain hợp lệ sau khi chuẩn hóa input.'
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        exit;
    }

    $siteResult = fetchAaPanelSiteTable($api_url, $api_key);
    $siteTable = $siteResult['site_table'] ?? [];
    if (empty($siteTable)) {
        echo json_encode([
            'status' => 'error',
            'message' => 'Không lấy được danh sách site từ aaPanel.',
            'site_list_raw_response' => $siteResult['raw_response'] ?? '',
            'site_list_json' => $siteResult['json'] ?? []
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        exit;
    }

    $domainToSite = [];
    foreach ($siteTable as $site) {
        $siteDomainsRaw = (string)($site['domain'] ?? '');
        $siteDomains = preg_split('/[\r\n,;\s]+/', $siteDomainsRaw);
        foreach ($siteDomains as $singleDomain) {
            $normalized = normalizeDomainForLookup($singleDomain);
            if ($normalized !== '' && !isset($domainToSite[$normalized])) {
                $domainToSite[$normalized] = [
                    'domain' => $singleDomain,
                    's_id' => $site['s_id']
                ];
            }
        }
    }

    $results = [];
    $successCount = 0;
    $errorCount = 0;
    $notFoundCount = 0;

    foreach ($domains as $domain) {
        if (!isset($domainToSite[$domain])) {
            $notFoundCount++;
            $results[] = [
                'domain' => $domain,
                'status' => 'not_found',
                'message' => 'Không tìm thấy domain trong danh sách site aaPanel.'
            ];
            continue;
        }

        $siteInfo = $domainToSite[$domain];
        $s_id = $siteInfo['s_id'];
        $resetResult = changeWordPressPasswordWithApiKey($api_url, $api_key, $s_id, $wpUser, $newPassword);

        if (!empty($resetResult['success'])) {
            $successCount++;
            $results[] = [
                'domain' => $domain,
                'matched_site_domain' => $siteInfo['domain'],
                's_id' => $s_id,
                'status' => 'success',
                'message' => $resetResult['note'] ?? 'Đổi mật khẩu thành công.'
            ];
        } else {
            $errorCount++;
            $results[] = [
                'domain' => $domain,
                'matched_site_domain' => $siteInfo['domain'],
                's_id' => $s_id,
                'status' => 'error',
                'message' => $resetResult['error'] ?? 'Lỗi không xác định',
                'api_response' => $resetResult['response'] ?? []
            ];
        }
    }

    echo json_encode([
        'status' => ($errorCount === 0 ? 'success' : 'partial'),
        'summary' => [
            'total_input_domains' => count($domains),
            'success' => $successCount,
            'error' => $errorCount,
            'not_found' => $notFoundCount
        ],
        'results' => $results
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    exit;
}


// 3. Hàm đổi mật khẩu WP qua API Key
function changeWordPressPasswordWithApiKey($api_url, $api_key, $s_id, $wp_user, $wp_pass) {
    $api_url = rtrim($api_url, '/');
    $endpoint = $api_url . '/v2/site?action=save_wp_configurations';
    $now = time();
    $token = md5($now . md5($api_key));
    $postData = [
        'request_time' => $now,
        'request_token' => $token,
        'language' => 'vi',
        's_id' => $s_id, 
        'admin_password' => $wp_pass
    ];
    $ch = curl_init($endpoint);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    $postFields = http_build_query($postData);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $postFields);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
    $headers = [
        'x-http-token: ' . $api_key,
        'Content-Type: application/x-www-form-urlencoded'
    ];
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    $response = curl_exec($ch);
    curl_close($ch);
    $data = json_decode($response, true);
    // Lưu lại thông tin request để debug
    $requestInfo = [
        'endpoint' => $endpoint,
        'headers' => $headers,
        'post_fields' => $postFields,
        'post_data_array' => $postData
    ];
    // Nhận diện thành công nếu status=true, hoặc msg chứa 'success', hoặc message.result chứa 'success' hoặc 'Update successfully'
    $isSuccess = false;
    $successMsg = '';
    if (isset($data['status']) && $data['status'] === true) {
        $isSuccess = true;
        $successMsg = 'Đổi mật khẩu thành công (API xác nhận status=true)!';
    } elseif (isset($data['msg']) && stripos($data['msg'], 'success') !== false) {
        $isSuccess = true;
        $successMsg = 'Đổi mật khẩu thành công (API trả về msg)!';
    } elseif (isset($data['message']['result']) && (stripos($data['message']['result'], 'success') !== false || stripos($data['message']['result'], 'Update successfully') !== false)) {
        $isSuccess = true;
        $successMsg = 'Đổi mật khẩu thành công (API trả về message.result)!';
    }
    if ($isSuccess) {
        return [
            'success' => true,
            'data' => $data,
            'note' => $successMsg . ' Tuy nhiên, hãy kiểm tra lại đăng nhập thực tế để xác nhận.',
            'raw_response' => $response,
            'request_info' => $requestInfo
        ];
    }
    return [
        'success' => false,
        'error' => $data['msg'] ?? $data['error'] ?? 'Lỗi không xác định',
        'response' => $data,
        'raw_response' => $response,
        'request_info' => $requestInfo
    ];
}



// 4. Nếu nhận POST từ UI thì xử lý từng tài khoản (API Key only)
if (
    $_SERVER['REQUEST_METHOD'] === 'POST' &&
    isset($_POST['api_url'], $_POST['api_key'], $_POST['s_id'], $_POST['user'], $_POST['pass'])
) {
    $api_url = trim($_POST['api_url']);
    $api_key = trim($_POST['api_key']);
    $s_id    = trim($_POST['s_id']);
    $user    = trim($_POST['user']);
    $pass    = trim($_POST['pass']);
    $result = changeWordPressPasswordWithApiKey($api_url, $api_key, $s_id, $user, $pass);
    $output = [];
    $output['s_id'] = $s_id;
    if ($result['success']) {
        $output['status'] = 'success';
        $output['message'] = $result['note'] ?? 'Đổi mật khẩu thành công!';
        $output['result'] = $result['data'] ?? [];
    } else {
        $output['status'] = 'error';
        $output['message'] = $result['error'] ?? 'Lỗi không xác định';
        $output['result'] = $result['response'] ?? [];
    }
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($output, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    exit;
}


// 5. Nếu chạy CLI hoặc không có POST thì dùng cấu hình mẫu (API Key only)
$api_url = 'http://IP_CUA_BAN:8888'; // Thay bằng IP và Port aaPanel
$api_key = 'YOUR_API_KEY';
// --- DANH SÁCH TÀI KHOẢN CẦN RESET (chạy CLI) ---
$accounts = [
    ['s_id' => 123, 'user' => 'admin', 'pass' => 'NewPass2026!'],
    ['s_id' => 456, 'user' => 'webmaster', 'pass' => 'Strong@2026'],
];
if (php_sapi_name() === 'cli') {
    foreach ($accounts as $acc) {
        echo "[*] Processing s_id={$acc['s_id']}... ";
        $result = changeWordPressPasswordWithApiKey($api_url, $api_key, $acc['s_id'], $acc['user'], $acc['pass']);
        if ($result['success']) {
            echo "Đổi mật khẩu thành công!\n";
            print_r($result['data'] ?? []);
        } else {
            echo "Lỗi: " . ($result['error'] ?? 'Không xác định') . "\n";
            print_r($result['response'] ?? []);
        }
    }
}

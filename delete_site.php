<?php
/**
 * delete_site.php
 *
 * Chức năng: Xóa site/website trên aaPanel từ server quản trị.
 *
 * Flow:
 *  - Bảo vệ bằng API Secret Key Session (APISecretKeyManager)
 *  - Nhận tham số POST:
 *      action=delete_site
 *      identifier=<panel_url hoặc ip> (dùng để định danh VPS aaPanel)
 *      site_id=<id site>   (ưu tiên) hoặc
 *      domain=<domain>    (fallback, nếu aaPanel API hỗ trợ)
 *  - Gọi aaPanel API endpoint để xóa website
 */

require_once 'config.php';
require_once 'APISecretKeyManager.php';
require_once 'APIKeyAuthInterface.php';

session_start();

$keyManager = new APISecretKeyManager();
$authResult = $keyManager->validateSession();

if (!$authResult['valid']) {
    header('HTTP/1.0 403 Forbidden');
    header('Content-Type: application/json; charset=UTF-8');
    echo json_encode([
        'success' => false,
        'message' => 'Unauthorized'
    ]);
    exit;
}

$permissions = $authResult['permissions'] ?? [];
// Cho phép admin hoặc vps (tuỳ bạn); nếu cần cứng hơn thì chỉnh lại.
if (!in_array('admin', $permissions, true) && !in_array('vps', $permissions, true)) {
    header('HTTP/1.0 403 Forbidden');
    header('Content-Type: application/json; charset=UTF-8');
    echo json_encode([
        'success' => false,
        'message' => 'Forbidden: missing permission'
    ]);
    exit;
}

header('Content-Type: application/json; charset=UTF-8');

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    echo json_encode([
        'success' => false,
        'message' => 'Method not allowed. Use POST.'
    ]);
    exit;
}

$action = $_POST['action'] ?? '';
if ($action !== 'delete_site') {
    echo json_encode([
        'success' => false,
        'message' => 'Invalid action'
    ]);
    exit;
}

$identifier = trim($_POST['identifier'] ?? '');
$siteId = trim($_POST['site_id'] ?? '');
$domain = trim($_POST['domain'] ?? '');

if ($identifier === '') {
    echo json_encode([
        'success' => false,
        'message' => 'Missing identifier (panel_url or ip)'
    ]);
    exit;
}

// Nếu identifier là IP dạng x.x.x.x, mặc định panel port 7800.
$panelUrl = $identifier;
if (filter_var($identifier, FILTER_VALIDATE_IP)) {
    $panelUrl = 'http://' . $identifier . ':7800';
}

/**
 * aaPanel API client (tối giản)
 */
class AaPanelClient
{
    private string $baseUrl;
    private int $timeout;

    public function __construct(string $baseUrl, int $timeout = 30)
    {
        $this->baseUrl = rtrim($baseUrl, '/');
        $this->timeout = $timeout;
    }

    private function makeRequest(string $endpoint, array $data = [], string $method = 'POST'): array
    {
        $url = $this->baseUrl . '/api/' . ltrim($endpoint, '/');
        $data['timestamp'] = time();

        // aaPanel có thể dùng auth theo api_key/api_secret. Ở tool này, ta gọi theo kiểu:
        //  - nếu aaPanel sử dụng token/signature khác bạn cần map lại.
        //  - Các file khác trong repo (aapanel_manager.php) có class aaPanelAPI với signature.
        // Ở đây dùng signature tương tự nếu bạn truyền api_key/api_secret qua config/ENV.

        // Tìm api key/secret mặc định từ config nếu có.
        $apiKey = defined('AAPANEL_API_KEY') ? AAPANEL_API_KEY : ($_ENV['AAPANEL_API_KEY'] ?? '');
        $apiSecret = defined('AAPANEL_API_SECRET') ? AAPANEL_API_SECRET : ($_ENV['AAPANEL_API_SECRET'] ?? '');

        if ($apiKey !== '' && $apiSecret !== '') {
            $data['api_key'] = $apiKey;
            $data['signature'] = $this->generateSignature($data, (string)$apiSecret);
        }

        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => $this->timeout,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => false,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'User-Agent: delete-site-aaPanel/1.0'
            ]
        ]);

        if (strtoupper($method) === 'POST') {
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        }

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($error) {
            throw new Exception('cURL Error: ' . $error);
        }

        if ($httpCode !== 200) {
            throw new Exception('HTTP Error: ' . $httpCode);
        }

        $decoded = json_decode((string)$response, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new Exception('Invalid JSON response: ' . json_last_error_msg());
        }

        return $decoded;
    }

    private function generateSignature(array $data, string $apiSecret): string
    {
        ksort($data);
        $string = '';
        foreach ($data as $key => $value) {
            $string .= $key . '=' . $value . '&';
        }
        $string = rtrim($string, '&');
        return md5($string . $apiSecret);
    }

    public function deleteSite(?string $siteId, ?string $domain): array
    {
        // endpoint cần map theo aaPanel version. Một số bản dùng:
        //  - sites/delete
        // hoặc:
        //  - sites/del
        //  - websites/delete
        //
        // Ở đây ta thử sites/delete với site_id.

        if ($siteId !== null && $siteId !== '') {
            return $this->makeRequest('sites/delete', [
                'site_id' => (int)$siteId
            ]);
        }

        if ($domain !== null && $domain !== '') {
            // fallback: xóa theo domain (nếu aaPanel hỗ trợ)
            return $this->makeRequest('sites/delete', [
                'domain' => $domain
            ]);
        }

        throw new Exception('Missing site_id or domain');
    }
}

try {
    $client = new AaPanelClient($panelUrl);
    $result = $client->deleteSite($siteId !== '' ? $siteId : null, $domain !== '' ? $domain : null);

    // aaPanel thường trả về {code:0,msg:"ok"} hoặc {success:true}. Ta normalize nhẹ.
    $success = false;
    if (is_array($result)) {
        if (isset($result['success'])) {
            $success = (bool)$result['success'];
        } elseif (isset($result['code'])) {
            $success = ((int)$result['code'] === 0);
        } elseif (isset($result['status'])) {
            $success = strtolower((string)$result['status']) === 'success';
        }
    }

    echo json_encode([
        'success' => $success,
        'data' => $result
    ]);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}


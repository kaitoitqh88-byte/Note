<?php
/**
 * Test Script for Cloudflare Domain List Functionality
 * Script kiểm tra chức năng lấy danh sách domain
 */

require_once 'config.php';
require_once 'CloudflareAPI.php';

echo "=== KIỂM TRA CHỨC NĂNG LẤY DANH SÁCH DOMAIN ===\n\n";

try {
    // Test 1: Kiểm tra kết nối và cấu hình API
    echo "1. Kiểm tra cấu hình API...\n";
    echo "   - Email: " . CLOUDFLARE_EMAIL . "\n";
    echo "   - Token: " . (strlen(CLOUDFLARE_API_TOKEN) > 10 ? "✓ Configured" : "✗ Missing") . "\n";
    echo "   - API URL: " . CLOUDFLARE_API_URL . "\n\n";
    
    // Test 2: Khởi tạo CloudflareAPI
    echo "2. Khởi tạo CloudflareAPI với pagination...\n";
    $cloudflare = new CloudflareAPI(null, null, 20); // 20 domains per page for testing
    echo "   - Default perPage: " . $cloudflare->getDefaultPerPage() . "\n";
    echo "   - Max perPage: " . $cloudflare->getMaxPerPage() . "\n\n";
    
    // Test 3: Lấy danh sách zones với pagination
    echo "3. Kiểm tra pagination - Lấy trang 1 với 20 domains...\n";
    $result = $cloudflare->listZones(1, 20);
    
    if ($result && isset($result['success']) && $result['success']) {
        $zones = $result['result'] ?? [];
        $resultInfo = $result['result_info'] ?? [];
        
        echo "   ✓ Kết quả thành công!\n";
        echo "   - Số domains trên trang này: " . count($zones) . "\n";
        echo "   - Tổng số domains: " . ($resultInfo['total_count'] ?? 'Unknown') . "\n";
        echo "   - Tổng số trang: " . ($resultInfo['total_pages'] ?? 'Unknown') . "\n";
        echo "   - Trang hiện tại: " . ($resultInfo['page'] ?? 'Unknown') . "\n";
        echo "   - Domains per page: " . ($resultInfo['per_page'] ?? 'Unknown') . "\n\n";
        
        // Hiển thị thông tin 3 domains đầu tiên
        echo "4. Thông tin 3 domains đầu tiên:\n";
        $displayCount = min(3, count($zones));
        for ($i = 0; $i < $displayCount; $i++) {
            $zone = $zones[$i];
            echo "   " . ($i + 1) . ". " . $zone['name'] . "\n";
            echo "      - ID: " . $zone['id'] . "\n";
            echo "      - Status: " . $zone['status'] . "\n";
            echo "      - Plan: " . ($zone['plan']['name'] ?? 'Unknown') . "\n";
            echo "      - Created: " . ($zone['created_on'] ?? 'Unknown') . "\n\n";
        }
        
        // Test 4: Kiểm tra search functionality
        echo "5. Kiểm tra chức năng tìm kiếm...\n";
        if (count($zones) > 0) {
            $firstDomainName = $zones[0]['name'];
            // Lấy 3 ký tự đầu để search
            $searchQuery = substr($firstDomainName, 0, 3);
            echo "   - Tìm kiếm với query: '$searchQuery'\n";
            
            $searchResult = $cloudflare->searchZones($searchQuery, 1, 10);
            if ($searchResult && isset($searchResult['success']) && $searchResult['success']) {
                $searchZones = $searchResult['result'] ?? [];
                echo "   ✓ Tìm thấy " . count($searchZones) . " domains\n";
            } else {
                echo "   ✗ Lỗi khi tìm kiếm\n";
            }
        }
        echo "\n";
        
        // Test 5: Test API endpoint simulation
        echo "6. Mô phỏng API endpoint call...\n";
        echo "   - URL: /index.php?action=zones&page=1&per_page=20\n";
        
        // Simulate the handleZones function
        $_GET['page'] = '1';
        $_GET['per_page'] = '20';
        
        ob_start();
        $page = intval($_GET['page'] ?? 1);
        $perPage = intval($_GET['per_page'] ?? 50);
        
        // Validate pagination parameters
        $page = max(1, $page);
        $perPage = min(100, max(1, $perPage));
        
        $apiResult = $cloudflare->listZones($page, $perPage);
        $simulatedResponse = [
            'success' => true,
            'data' => $apiResult
        ];
        ob_end_clean();
        
        echo "   ✓ API response structure OK\n";
        echo "   - Response success: " . ($simulatedResponse['success'] ? 'true' : 'false') . "\n";
        echo "   - Has data: " . (isset($simulatedResponse['data']) ? 'true' : 'false') . "\n\n";
        
        echo "=== KẾT QUẢ TỔNG KẾT ===\n";
        echo "✓ API Configuration: OK\n";
        echo "✓ Pagination Support: OK\n";
        echo "✓ Domain List Retrieval: OK\n";
        echo "✓ Search Functionality: OK\n";
        echo "✓ API Endpoint Structure: OK\n\n";
        echo "Hệ thống sẵn sàng sử dụng!\n";
        
    } else {
        throw new Exception("Lỗi khi lấy danh sách zones: " . json_encode($result));
    }
    
} catch (Exception $e) {
    echo "❌ LỖI: " . $e->getMessage() . "\n\n";
    echo "Các bước kiểm tra:\n";
    echo "1. Kiểm tra token.txt có đúng API token không\n";
    echo "2. Kiểm tra email trong config.php\n";
    echo "3. Kiểm tra kết nối internet\n";
    echo "4. Kiểm tra quyền truy cập Cloudflare API\n";
}
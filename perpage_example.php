<?php
/**
 * Ví dụ sử dụng cấu hình perPage trong CloudflareAPI
 */

require_once 'config.php';
require_once 'CloudflareAPI.php';

try {
    // Khởi tạo với cấu hình perPage mặc định
    $cloudflare = new CloudflareAPI(null, null, 75); // 75 domains per page
    
    echo "=== CẤU HÌNH PERPAGE ===\n";
    echo "Default perPage: " . $cloudflare->getDefaultPerPage() . "\n";
    echo "Max perPage: " . $cloudflare->getMaxPerPage() . "\n\n";
    
    // Thay đổi cấu hình động
    $cloudflare->setDefaultPerPage(25);
    echo "Sau khi thay đổi: " . $cloudflare->getDefaultPerPage() . "\n\n";
    
    // Sử dụng các method với perPage khác nhau
    echo "=== SỬ DỤNG CÁC METHODS ===\n";
    
    // 1. Lấy zones với setting mặc định
    echo "1. Lấy zones với perPage mặc định...\n";
    $zones1 = $cloudflare->getZones(); // Sử dụng default
    echo "   Kết quả: " . count($zones1['result'] ?? []) . " zones\n";
    
    // 2. Lấy zones với perPage tùy chỉnh
    echo "2. Lấy zones với perPage = 10...\n";
    $zones2 = $cloudflare->getZones(10); // Tùy chỉnh
    echo "   Kết quả: " . count($zones2['result'] ?? []) . " zones\n";
    
    // 3. Tìm kiếm với perPage tùy chỉnh
    echo "3. Tìm kiếm với perPage = 5...\n";
    $search = $cloudflare->searchZones('', 1, 5);
    echo "   Kết quả: " . count($search['result'] ?? []) . " zones\n";
    
    // 4. Lấy tất cả zones với phân trang tự động
    echo "4. Lấy tất cả zones với phân trang tự động...\n";
    $allZones = $cloudflare->getAllZonesPaginated(200); // Max 200 zones
    echo "   Tổng zones: " . $allZones['result_info']['count'] . "\n";
    echo "   Pages fetched: " . $allZones['result_info']['pages_fetched'] . "\n";
    echo "   Truncated: " . ($allZones['result_info']['truncated'] ? 'Yes' : 'No') . "\n";
    
} catch (Exception $e) {
    echo "Lỗi: " . $e->getMessage() . "\n";
}

echo "\n=== HƯỚNG DẪN SỬ DỤNG ===\n";
echo "1. Khởi tạo với perPage mặc định:\n";
echo "   \$api = new CloudflareAPI(null, null, 50);\n\n";

echo "2. Thay đổi setting động:\n";
echo "   \$api->setDefaultPerPage(75);\n\n";

echo "3. Sử dụng method với perPage tùy chỉnh:\n";
echo "   \$zones = \$api->getZones(25);\n";
echo "   \$search = \$api->searchZones('domain', 1, 10);\n\n";

echo "4. Lấy tất cả zones (auto pagination):\n";
echo "   \$all = \$api->getAllZonesPaginated(500);\n\n";

echo "=== GIÁ TRỊ GIỚI HẠN ===\n";
echo "- Min: 1\n";
echo "- Max: 100 (giới hạn Cloudflare API)\n";
echo "- Default: 50 (có thể tùy chỉnh)\n";
echo "- Recommended: 25-75 tùy theo nhu cầu\n";
?>
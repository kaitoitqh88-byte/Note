<?php
/**
 * Test Domain List Parsing
 */
require_once 'config.php';
require_once 'CloudflareAPI.php';

function testDomainListParsing($domainListText) {
    echo "<h3>Testing Domain List Parsing</h3>\n";
    echo "<p>Input:</p>\n";
    echo "<pre>" . htmlspecialchars($domainListText) . "</pre>\n";
    
    // Parse domain list
    $domains = array_filter(array_map('trim', explode("\n", $domainListText)));
    
    echo "<p>Parsed domains (" . count($domains) . " domains):</p>\n";
    echo "<ul>\n";
    foreach ($domains as $domain) {
        echo "<li>" . htmlspecialchars($domain) . "</li>\n";
    }
    echo "</ul>\n";
    
    return $domains;
}

// Test data
$testDomainList = "example1.com
example2.com

example3.com
  example4.com  
invalid..domain
valid-domain.com";

?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Test DNS Bulk Update</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
    <div class="container mt-4">
        <div class="row">
            <div class="col-12">
                <div class="card">
                    <div class="card-header">
                        <h5><i class="fas fa-test-tube"></i> Test DNS Bulk Update</h5>
                    </div>
                    <div class="card-body">
                        <?php testDomainListParsing($testDomainList); ?>
                        
                        <hr>
                        
                        <h4>Function Status:</h4>
                        <div class="alert alert-success">
                            <strong>✅ DNS Bulk Update được cập nhật thành công!</strong>
                            <ul class="mb-0 mt-2">
                                <li>Thay đổi từ chọn zones thành nhập danh sách domain</li>
                                <li>Chỉ cần nhập 1 IP cho cả @ và www records</li>
                                <li>Tự động tìm domain trong Cloudflare account</li>
                                <li>Tạo/cập nhật @ và www records tự động</li>
                            </ul>
                        </div>
                        
                        <h4>Hướng dẫn sử dụng:</h4>
                        <ol>
                            <li>Truy cập <a href="dns_bulk_update.php" class="btn btn-primary btn-sm">DNS Bulk Update</a></li>
                            <li>Nhập danh sách domains vào textarea (mỗi domain một dòng)</li>
                            <li>Nhập IP address sẽ được áp dụng cho cả @ và www</li>
                            <li>Chọn loại record (A/AAAA/CNAME)</li>
                            <li>Click "Cập nhật DNS" để thực hiện</li>
                        </ol>
                        
                        <div class="alert alert-info">
                            <strong>Lưu ý:</strong> Chỉ các domain có trong Cloudflare account của bạn mới được cập nhật DNS.
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
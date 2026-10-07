<?php
/**
 * Domain Redirect Handler - Entry Point
 * Xử lý redirect request từ các domain được cấu hình
 * 
 * Cách sử dụng:
 * 1. Đặt file này ở root của domain cần redirect
 * 2. Cấu hình .htaccess để route tất cả request về file này
 * 3. Hoặc include file này trong index.php của domain
 */

require_once 'RedirectHandler.php';

// Khởi tạo redirect handler
$redirectHandler = new RedirectHandler();

// Lấy thông tin request hiện tại
$requestDomain = $_SERVER['HTTP_HOST'] ?? '';
$requestPath = $_SERVER['REQUEST_URI'] ?? '/';
$requestProtocol = ((!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') 
                    || $_SERVER['SERVER_PORT'] == 443) ? 'https' : 'http';

// Xử lý redirect
if ($redirectHandler->processRedirect($requestDomain, $requestPath, $requestProtocol)) {
    // Redirect đã được thực hiện
    exit;
}

// Nếu không có redirect rule nào khớp, tiếp tục với nội dung bình thường
// Bạn có thể redirect về trang 404 hoặc hiển thị nội dung mặc định

// Example: Redirect to a default domain if no rules match
// header('Location: https://default-domain.com' . $requestPath);
// exit;

// Or display a 404 page
http_response_code(404);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Domain Not Found</title>
    <style>
        body {
            font-family: 'Roboto', sans-serif;
            text-align: center;
            padding: 50px;
            background-color: #f5f5f5;
        }
        .container {
            max-width: 600px;
            margin: 0 auto;
            background: white;
            padding: 40px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        .icon {
            font-size: 4em;
            color: #ddd;
            margin-bottom: 20px;
        }
        h1 {
            color: #333;
            margin-bottom: 20px;
        }
        p {
            color: #666;
            line-height: 1.6;
        }
        .domain {
            font-family: monospace;
            background: #f8f9fa;
            padding: 10px;
            border-radius: 5px;
            margin: 20px 0;
            color: #333;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="icon">🌐</div>
        <h1>Domain Not Found</h1>
        <p>The domain you are trying to access is not configured for redirection.</p>
        
        <div class="domain">
            Requested: <?= htmlspecialchars($requestDomain . $requestPath) ?>
        </div>
        
        <p>
            If you believe this is an error, please contact the administrator.
        </p>
        
        <p>
            <strong>Debug Information:</strong><br>
            Domain: <?= htmlspecialchars($requestDomain) ?><br>
            Path: <?= htmlspecialchars($requestPath) ?><br>
            Protocol: <?= htmlspecialchars($requestProtocol) ?>
        </p>
    </div>
</body>
</html>
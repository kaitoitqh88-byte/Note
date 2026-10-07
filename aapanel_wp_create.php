<?php
// aaPanel WordPress Auto-Creation Tool
// Form for aaPanel URL, Username, Password, and Start button

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $aapanelUrl = trim($_POST['aapanel_url'] ?? '');
    $username = trim($_POST['username'] ?? '');
    $password = trim($_POST['password'] ?? '');
    $result = '';

    // Validate input
    if (!$aapanelUrl || !$username || !$password) {
        $result = 'Vui lòng nhập đầy đủ thông tin.';
    } else {
        // Here you would trigger the automation (API call, shell script, etc.)
        // For demo: just show the input
        $result = 'Đã nhận thông tin:<br>';
        $result .= 'aaPanel URL: ' . htmlspecialchars($aapanelUrl) . '<br>';
        $result .= 'Username: ' . htmlspecialchars($username) . '<br>';
        $result .= 'Password: ' . htmlspecialchars($password) . '<br>';
        $result .= '<b>Quy trình tạo WordPress sẽ được kích hoạt ở đây!</b>';
    }
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tạo WordPress trên aaPanel</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-dark text-light">
<div class="container mt-5" style="max-width: 500px;">
    <h2 class="mb-4 text-success">Tạo WordPress trên aaPanel</h2>
    <form method="post">
        <div class="mb-3">
            <label for="aapanel_url" class="form-label">aaPanel URL</label>
            <input type="text" class="form-control" id="aapanel_url" name="aapanel_url" placeholder="Nhập IP hoặc tên miền aaPanel" required>
        </div>
        <div class="mb-3">
            <label for="username" class="form-label">Username</label>
            <input type="text" class="form-control" id="username" name="username" placeholder="Nhập username" required>
        </div>
        <div class="mb-3">
            <label for="password" class="form-label">Password</label>
            <input type="password" class="form-control" id="password" name="password" placeholder="Nhập password" required>
        </div>
        <button type="submit" class="btn btn-success w-100">START CREATE WORDPRESS</button>
    </form>
    <?php if (!empty($result)): ?>
        <div class="alert alert-info mt-4"> <?= $result ?> </div>
    <?php endif; ?>
</div>
</body>
</html>

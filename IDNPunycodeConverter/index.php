<?php
// IDN Punycode Converter
// Công cụ chuyển đổi tên miền quốc tế (IDN) sang Punycode và ngược lại

$results = [];
$errors = [];
$input = '';
$conversion_type = 'to_punycode';
$total_domains = 0;
$successful_conversions = 0;

if ($_POST) {
    $input = trim($_POST['input'] ?? '');
    $conversion_type = $_POST['conversion_type'] ?? 'to_punycode';
    
    if (empty($input)) {
        $errors[] = 'Vui lòng nhập tên miền cần chuyển đổi';
    } else {
        // Tách các domain bằng dấu xuống dòng, tab, hoặc dấu phẩy
        $domains = preg_split('/[\r\n\t,]+/', $input, -1, PREG_SPLIT_NO_EMPTY);
        $domains = array_map('trim', $domains);
        $domains = array_filter($domains); // Loại bỏ các phần tử rỗng
        
        $total_domains = count($domains);
        
        if (empty($domains)) {
            $errors[] = 'Không tìm thấy tên miền hợp lệ';
        } else {
            foreach ($domains as $index => $domain) {
                $domain = trim($domain);
                if (empty($domain)) continue;
                
                try {
                    if ($conversion_type === 'to_punycode') {
                        // Chuyển đổi từ Unicode sang Punycode
                        $converted = idn_to_ascii($domain, IDNA_DEFAULT, INTL_IDNA_VARIANT_UTS46);
                        if ($converted === false) {
                            $errors[] = 'Domain "' . htmlspecialchars($domain) . '": Không thể chuyển đổi sang Punycode';
                        } else {
                            $results[] = [
                                'original' => $domain,
                                'converted' => $converted,
                                'index' => $index + 1
                            ];
                            $successful_conversions++;
                        }
                    } else {
                        // Chuyển đổi từ Punycode sang Unicode
                        $converted = idn_to_utf8($domain, IDNA_DEFAULT, INTL_IDNA_VARIANT_UTS46);
                        if ($converted === false) {
                            $errors[] = 'Domain "' . htmlspecialchars($domain) . '": Không thể chuyển đổi từ Punycode';
                        } else {
                            $results[] = [
                                'original' => $domain,
                                'converted' => $converted,
                                'index' => $index + 1
                            ];
                            $successful_conversions++;
                        }
                    }
                } catch (Exception $e) {
                    $errors[] = 'Domain "' . htmlspecialchars($domain) . '": ' . $e->getMessage();
                }
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>IDN Punycode Converter</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/common.css">
    <link rel="stylesheet" href="style.css">
    <link rel="stylesheet" href="style_matrix.css">
    <style>
        /* Toàn bộ nút, tiêu đề, border, icon, kết quả màu xanh lá */
        body, .container, .card, .results, .results-summary, .results-output, .results-actions, .copy-all-btn, .select-all-btn, .card-header, .example-item, .format-radio label, .results-textarea {
            color: #00cc44 !important;
        }
        .card, .results, .results-summary, .results-output, .results-actions, .card-header, .example-item, .results-textarea {
            border-color: #00cc44 !important;
        }
        .copy-all-btn, .select-all-btn, .btn-primary, .btn {
            background: #00cc44 !important;
            border-color: #00cc44 !important;
            color: #fff !important;
        }
        .copy-all-btn:hover, .select-all-btn:hover, .btn-primary:hover, .btn:hover {
            background: #009933 !important;
            border-color: #009933 !important;
        }
        .results-textarea {
            color: #00cc44 !important;
            background: #111 !important;
            border: 1.5px solid #00cc44 !important;
            font-weight: 600;
        }
        .card-header.bg-info {
            background: #00cc44 !important;
            color: #fff !important;
        }
        .fas, .fa, .far, .fab {
            color: #00cc44 !important;
        }
        /* Đổi màu tiêu đề IDN Punycode Converter thành xanh lá */
        .header h1,
        .display-5.fw-bold.text-primary {
            color: #00cc44 !important;
        }
    </style>
</head>
<body>
    <!-- Navigation -->
    <?php 
    $currentPage = 'idn-converter'; // Set active page for navigation
    include '../includes/main_navigation.php'; 
    ?>

    <div class="container mt-4">
        <div class="header text-center mb-4">
            <h1 class="display-5 fw-bold text-primary">
                <i class="fas fa-language me-3"></i>IDN Punycode Converter
            </h1>
            <p class="lead text-muted">Công cụ chuyển đổi tên miền quốc tế (IDN) sang Punycode và ngược lại</p>
        </div>
        
        <div class="content">
            <div class="card shadow-sm">
                <div class="card-body">
                    <form method="POST">
                        <div class="form-group mb-3">
                            <label for="input" class="form-label fw-semibold">
                                <i class="fas fa-edit me-2"></i>Nhập tên miền cần chuyển đổi (một domain mỗi dòng):
                            </label>
                            <textarea id="input" 
                                      name="input" 
                                      class="form-control"
                                      rows="6"
                                      placeholder="Ví dụ:&#10;việt-nam.com&#10;example.中国&#10;xn--fsq.xn--fiqs8s&#10;&#10;Bạn có thể nhập nhiều domain, mỗi domain trên một dòng&#10;hoặc cách nhau bằng dấu phẩy"
                                      required><?php echo htmlspecialchars($input); ?></textarea>
                        </div>
                
                        <div class="form-group mb-4">
                            <label class="form-label fw-semibold">
                                <i class="fas fa-exchange-alt me-2"></i>Loại chuyển đổi:
                            </label>
                            <div class="radio-group">
                                <div class="form-check form-check-inline">
                                    <input class="form-check-input" type="radio" 
                                           name="conversion_type" 
                                           id="to_punycode"
                                           value="to_punycode" 
                                           <?php echo ($conversion_type === 'to_punycode') ? 'checked' : ''; ?>>
                                    <label class="form-check-label" for="to_punycode">
                                        <i class="fas fa-arrow-right me-1 text-primary"></i>
                                        Unicode → Punycode
                                    </label>
                                </div>
                                <div class="form-check form-check-inline">
                                    <input class="form-check-input" type="radio" 
                                           name="conversion_type" 
                                           id="to_unicode"
                                           value="to_unicode" 
                                           <?php echo ($conversion_type === 'to_unicode') ? 'checked' : ''; ?>>
                                    <label class="form-check-label" for="to_unicode">
                                        <i class="fas fa-arrow-left me-1 text-success"></i>
                                        Punycode → Unicode
                                    </label>
                                </div>
                            </div>
                        </div>
                
                        <div class="text-center">
                            <button type="submit" class="btn btn-primary btn-lg px-5">
                                <i class="fas fa-sync-alt me-2"></i>Chuyển đổi
                            </button>
                        </div>
                    </form>
                </div>
            </div>
            
            <?php if (!empty($errors)): ?>
                <div class="alert alert-danger mt-4">
                    <h6 class="alert-heading">
                        <i class="fas fa-exclamation-triangle me-2"></i>Lỗi chuyển đổi:
                    </h6>
                    <ul class="mb-0">
                        <?php foreach ($errors as $error): ?>
                            <li><?php echo htmlspecialchars($error); ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>
            
            <?php if (!empty($results)): ?>
                <div class="results">
                    <div class="results-summary">
                        <h3>Kết quả chuyển đổi</h3>
                        <p>Đã xử lý <?php echo $successful_conversions; ?>/<?php echo $total_domains; ?> domain thành công</p>
                    </div>
                    
                    <div class="format-options">
                        <div class="format-radio">
                            <label class="format-option">
                                <input type="radio" name="output_format" value="converted_only" checked>
                                <span>Chỉ kết quả</span>
                            </label>
                            <label class="format-option">
                                <input type="radio" name="output_format" value="paired">
                                <span>Gốc → Chuyển đổi</span>
                            </label>
                            <label class="format-option">
                                <input type="radio" name="output_format" value="numbered">
                                <span>Có đánh số</span>
                            </label>
                        </div>
                    </div>
                    
                    <textarea id="results-output" class="results-textarea" readonly><?php 
                        // Mặc định hiển thị chỉ kết quả đã chuyển đổi
                        foreach ($results as $result) {
                            echo htmlspecialchars($result['converted']) . "\n";
                        }
                    ?></textarea>
                    
                    <div class="results-actions">
                        <button type="button" class="copy-all-btn" onclick="copyTextarea()">
                            📋 Sao chép toàn bộ
                        </button>
                        <button type="button" class="select-all-btn" onclick="selectAllText()">
                            📝 Chọn tất cả
                        </button>
                    </div>
                </div>
                
            <?php endif; ?>
            
            <div class="card mt-4">
                <div class="card-header bg-info text-white">
                    <h6 class="card-title mb-0">
                        <i class="fas fa-info-circle me-2"></i>Hướng dẫn sử dụng
                    </h6>
                </div>
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <div class="example-item">
                                <strong><i class="fas fa-arrow-right me-1 text-primary"></i>Chuyển đổi từ Unicode sang Punycode:</strong><br>
                                <code>việt-nam.com → xn--vit-nam-0xa.com</code>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="example-item">
                                <strong><i class="fas fa-arrow-left me-1 text-success"></i>Chuyển đổi từ Punycode sang Unicode:</strong><br>
                                <code>xn--vit-nam-0xa.com → việt-nam.com</code>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="example-item">
                                <strong><i class="fas fa-list me-1 text-warning"></i>Chuyển đổi nhiều domain (mỗi domain một dòng):</strong><br>
                                việt-nam.com<br>
                                example.中国<br>
                                ドメイン.テスト<br>
                                тест.рф
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="example-item">
                                <strong><i class="fas fa-comma me-1 text-secondary"></i>Hoặc cách nhau bằng dấu phẩy:</strong><br>
                                việt-nam.com, example.中国, ドメイン.テスト
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="script.js"></script>
</body>
</html>
 
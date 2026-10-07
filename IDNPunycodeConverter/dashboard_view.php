<?php
// IDN Punycode Converter Dashboard Integration
// Giao diện tích hợp với dashboard chính

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
        $domains = array_filter($domains);
        
        $total_domains = count($domains);
        
        if (empty($domains)) {
            $errors[] = 'Không tìm thấy tên miền hợp lệ';
        } else {
            foreach ($domains as $index => $domain) {
                $domain = trim($domain);
                if (empty($domain)) continue;
                
                try {
                    if ($conversion_type === 'to_punycode') {
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
    <title>IDN Punycode Converter - Cloudflare Manager</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="../assets/css/common.css" rel="stylesheet">
    <link href="../assets/css/idn-converter.css" rel="stylesheet">
</head>
<body>
    <!-- Navigation -->
    <?php 
    $currentPage = 'idn-converter'; // Set active page for navigation
    include '../includes/navigation.php'; 
    ?>

    <div class="container mt-4">
        <div class="row">
            <div class="col-12">
                <!-- Page Header -->
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <div>
                        <h2><i class="fas fa-exchange-alt text-primary"></i> IDN Punycode Converter</h2>
                        <p class="text-muted mb-0">Công cụ chuyển đổi tên miền quốc tế (IDN) sang Punycode và ngược lại</p>
                    </div>
                </div>

                <!-- Converter Form -->
                <div class="card converter-card mb-4">
                    <div class="card-body">
                        <form method="POST" action="/?action=idn-converter">
                            <div class="row">
                                <div class="col-lg-8">
                                    <div class="mb-3">
                                        <label for="input" class="form-label fw-bold">
                                            <i class="fas fa-globe"></i> Nhập tên miền cần chuyển đổi
                                        </label>
                                        <textarea class="form-control" 
                                                  id="input" 
                                                  name="input" 
                                                  rows="6"
                                                  placeholder="Ví dụ:&#10;việt-nam.com&#10;example.中国&#10;xn--fsq.xn--fiqs8s&#10;&#10;Bạn có thể nhập nhiều domain, mỗi domain trên một dòng&#10;hoặc cách nhau bằng dấu phẩy"
                                                  required><?php echo htmlspecialchars($input); ?></textarea>
                                        <div class="form-text">Nhập một hoặc nhiều tên miền, mỗi dòng một domain hoặc cách nhau bằng dấu phẩy</div>
                                    </div>
                                </div>
                                <div class="col-lg-4">
                                    <div class="mb-3">
                                        <label class="form-label fw-bold">
                                            <i class="fas fa-cogs"></i> Loại chuyển đổi
                                        </label>
                                        <div class="card bg-light p-3">
                                            <div class="form-check custom-radio">
                                                <input class="form-check-input" 
                                                       type="radio" 
                                                       name="conversion_type" 
                                                       value="to_punycode" 
                                                       id="to_punycode"
                                                       <?php echo ($conversion_type === 'to_punycode') ? 'checked' : ''; ?>>
                                                <label class="form-check-label" for="to_punycode">
                                                    <i class="fas fa-arrow-right text-primary"></i>
                                                    <strong>Unicode → Punycode</strong>
                                                    <small class="d-block text-muted">việt-nam.com → xn--vit-nam-...</small>
                                                </label>
                                            </div>
                                            <div class="form-check custom-radio">
                                                <input class="form-check-input" 
                                                       type="radio" 
                                                       name="conversion_type" 
                                                       value="to_unicode" 
                                                       id="to_unicode"
                                                       <?php echo ($conversion_type === 'to_unicode') ? 'checked' : ''; ?>>
                                                <label class="form-check-label" for="to_unicode">
                                                    <i class="fas fa-arrow-left text-success"></i>
                                                    <strong>Punycode → Unicode</strong>
                                                    <small class="d-block text-muted">xn--vit-nam-... → việt-nam.com</small>
                                                </label>
                                            </div>
                                        </div>
                                    </div>
                                    <button type="submit" class="btn btn-primary btn-lg w-100">
                                        <i class="fas fa-exchange-alt"></i> Chuyển đổi ngay
                                    </button>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- Error Messages -->
                <?php if (!empty($errors)): ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <i class="fas fa-exclamation-triangle"></i>
                    <strong>Có lỗi xảy ra:</strong>
                    <ul class="mb-0 mt-2">
                        <?php foreach ($errors as $error): ?>
                            <li><?php echo htmlspecialchars($error); ?></li>
                        <?php endforeach; ?>
                    </ul>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
                <?php endif; ?>

                <!-- Results -->
                <?php if (!empty($results)): ?>
                <div class="card converter-card">
                    <div class="card-header results-summary text-center py-3">
                        <h4 class="mb-0">
                            <i class="fas fa-check-circle"></i>
                            Kết quả chuyển đổi: <?php echo $successful_conversions; ?>/<?php echo $total_domains; ?> domain thành công
                        </h4>
                    </div>
                    <div class="card-body">
                        <!-- Format Options -->
                        <div class="row mb-3">
                            <div class="col-12">
                                <div class="d-flex flex-wrap align-items-center gap-3">
                                    <span class="fw-bold">Định dạng hiển thị:</span>
                                    <div class="form-check">
                                        <input class="form-check-input" type="radio" name="output_format" value="converted_only" id="converted_only" checked>
                                        <label class="form-check-label" for="converted_only">Chỉ kết quả</label>
                                    </div>
                                    <div class="form-check">
                                        <input class="form-check-input" type="radio" name="output_format" value="paired" id="paired">
                                        <label class="form-check-label" for="paired">Gốc → Chuyển đổi</label>
                                    </div>
                                    <div class="form-check">
                                        <input class="form-check-input" type="radio" name="output_format" value="numbered" id="numbered">
                                        <label class="form-check-label" for="numbered">Có đánh số</label>
                                    </div>
                                    <button class="btn copy-btn btn-sm" onclick="copyAllResults()">
                                        <i class="fas fa-copy"></i> Copy tất cả
                                    </button>
                                </div>
                            </div>
                        </div>

                        <!-- Results Display -->
                        <div id="results-display">
                            <?php foreach ($results as $result): ?>
                            <div class="result-item p-3 mb-2 rounded d-flex justify-content-between align-items-center">
                                <div class="flex-grow-1">
                                    <span class="result-text font-monospace">
                                        <span class="original d-none"><?php echo htmlspecialchars($result['original']); ?></span>
                                        <span class="converted"><?php echo htmlspecialchars($result['converted']); ?></span>
                                        <span class="paired d-none"><?php echo htmlspecialchars($result['original']) . ' → ' . htmlspecialchars($result['converted']); ?></span>
                                        <span class="numbered d-none"><?php echo $result['index'] . '. ' . htmlspecialchars($result['converted']); ?></span>
                                    </span>
                                </div>
                                <button class="btn copy-btn btn-sm ms-2" onclick="copyText(this)" data-text="<?php echo htmlspecialchars($result['converted']); ?>">
                                    <i class="fas fa-copy"></i>
                                </button>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Scripts -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="../assets/js/common.js"></script>
    <script>
        // Results data for JavaScript processing
        const resultsData = <?php echo !empty($results) ? json_encode($results) : '[]'; ?>;
    </script>
    <script src="../assets/js/idn-converter.js"></script>
</body>
</html>
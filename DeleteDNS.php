<?php
/**
 * Delete DNS Records - Xóa toàn bộ DNS records theo danh sách domain
 */
require_once 'config.php';
require_once 'CloudflareAPI.php';

$cloudflareAPI = new CloudflareAPI();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');
    if (ob_get_level()) {
        ob_clean();
    }

    try {
        $action = $_POST['action'] ?? '';

        switch ($action) {
            case 'bulk_delete_dns_chunk':
                $domains = $_POST['domains'] ?? [];
                if (!is_array($domains)) {
                    $domains = preg_split('/[\r\n,;]+/', (string) $domains, -1, PREG_SPLIT_NO_EMPTY);
                }

                $results = $cloudflareAPI->batchDeleteDNSRecordsByDomains($domains);
                echo json_encode($results);
                break;

            default:
                throw new Exception('Invalid action');
        }
    } catch (Exception $e) {
        echo json_encode([
            'success' => false,
            'error' => $e->getMessage()
        ]);
    }

    exit;
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Delete DNS - Xóa DNS Records</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="assets/css/rpg-shooter-theme.css" rel="stylesheet">
    <link href="assets/css/sidebar-navigation.css" rel="stylesheet">
    <style>
        .delete-hero {
            background: linear-gradient(135deg, rgba(220, 53, 69, 0.18), rgba(13, 110, 253, 0.12));
            border: 1px solid rgba(255, 255, 255, 0.08);
        }
        .delete-badge {
            background: rgba(220, 53, 69, 0.12);
            color: #dc3545;
            border: 1px solid rgba(220, 53, 69, 0.25);
        }
        .result-item {
            border: 1px solid rgba(255, 255, 255, 0.08);
            border-radius: 12px;
            padding: 16px;
            margin-bottom: 12px;
            background: rgba(255, 255, 255, 0.04);
        }
        .result-item.success {
            border-left: 4px solid #198754;
        }
        .result-item.error {
            border-left: 4px solid #dc3545;
        }
        .progress-container {
            display: none;
        }
    </style>
</head>
<body>
    <?php
    $currentPage = 'delete-dns';
    include 'includes/main_navigation.php';
    ?>

    <div class="main-wrapper">
        <div class="container-fluid my-4">
            <div class="row mb-4">
                <div class="col-12">
                    <div class="d-flex justify-content-between align-items-center flex-wrap gap-3 delete-hero p-4 rounded-4">
                        <div>
                            <div class="delete-badge badge mb-2 px-3 py-2">Danger Zone</div>
                            <h1 class="mb-2"><i class="fas fa-trash me-2"></i>Delete DNS</h1>
                            <p class="text-muted mb-0">Xóa toàn bộ DNS records của các domain có trong Cloudflare account.</p>
                        </div>
                        <div>
                            <a href="dns_bulk_update.php" class="btn btn-outline-light">
                                <i class="fas fa-sync-alt me-2"></i>Quay lại cập nhật DNS
                            </a>
                        </div>
                    </div>
                </div>
            </div>

            <div class="row mb-4">
                <div class="col-12">
                    <div class="card">
                        <div class="card-header">
                            <h5 class="mb-0"><i class="fas fa-cog me-2"></i>Cấu hình xóa DNS</h5>
                        </div>
                        <div class="card-body">
                            <form id="deleteDnsForm">
                                <div class="row g-3 align-items-end">
                                    <div class="col-12 col-md-9">
                                        <label class="form-label">Danh sách domain</label>
                                        <textarea class="form-control" id="domainList" rows="8" placeholder="example1.com&#10;example2.com&#10;example3.com"></textarea>
                                        <div class="form-text mt-2">
                                            <i class="fas fa-info-circle me-1"></i>
                                            Mỗi domain một dòng. Hệ thống sẽ xóa toàn bộ DNS records của zone tương ứng.
                                        </div>
                                    </div>
                                    <div class="col-12 col-md-3">
                                        <button type="button" class="btn btn-danger w-100" id="deleteBtn" disabled>
                                            <i class="fas fa-trash me-2"></i>Xóa DNS Records
                                        </button>
                                        <button type="button" class="btn btn-outline-secondary w-100 mt-2" onclick="clearDomainList()">
                                            <i class="fas fa-eraser me-2"></i>Xóa danh sách
                                        </button>
                                    </div>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </div>

            <div class="row mb-4">
                <div class="col-12">
                    <div class="card progress-container alert alert-danger mb-0" id="progressContainer">
                        <h5 id="progressTitle"><i class="fas fa-trash fa-spin me-2"></i>Đang xóa DNS records...</h5>
                        <div class="progress mb-2">
                            <div class="progress-bar progress-bar-striped progress-bar-animated bg-danger" id="progressBar" style="width: 0%"></div>
                        </div>
                        <div id="progressText">0 / 0 domains đã xử lý</div>
                    </div>
                </div>
            </div>

            <div class="row">
                <div class="col-12">
                    <div class="card">
                        <div class="card-header d-flex justify-content-between align-items-center">
                            <h5 class="mb-0"><i class="fas fa-list me-2"></i>Danh sách Domains</h5>
                            <div class="selection-counter" id="domainCounter" style="display:none;">
                                <i class="fas fa-check-circle me-2"></i><span id="domainCount">0</span> domain đã nhập
                            </div>
                        </div>
                        <div class="card-body">
                            <div class="form-text mb-3">
                                <i class="fas fa-exclamation-triangle me-1"></i>
                                Hành động này sẽ xóa toàn bộ DNS records của từng domain được tìm thấy trong account.
                            </div>
                            <div id="resultsContainer" style="display:none;">
                                <div class="mb-3" id="resultsSummary"></div>
                                <div id="resultsContent"></div>
                                <div class="mt-3">
                                    <button class="btn btn-primary" onclick="location.reload()">
                                        <i class="fas fa-redo me-2"></i>Xóa mới
                                    </button>
                                </div>
                            </div>
                            <div id="emptyState" class="text-muted">
                                Chưa có kết quả xử lý.
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        const DELETE_CHUNK_SIZE = 8;

        document.addEventListener('DOMContentLoaded', function() {
            document.getElementById('domainList').addEventListener('input', function() {
                updateDomainCounter();
                validateForm();
            });
            document.getElementById('deleteBtn').addEventListener('click', startBulkDelete);
            validateForm();
        });

        function updateDomainCounter() {
            const domainList = document.getElementById('domainList').value.trim();
            const domains = parseUniqueDomains(domainList);
            const domainCount = domains.length;

            const counter = document.getElementById('domainCounter');
            const countSpan = document.getElementById('domainCount');

            if (domainCount > 0) {
                counter.style.display = 'block';
                countSpan.textContent = domainCount;
            } else {
                counter.style.display = 'none';
            }
        }

        function clearDomainList() {
            document.getElementById('domainList').value = '';
            updateDomainCounter();
            validateForm();
        }

        function validateForm() {
            const domainList = document.getElementById('domainList').value.trim();
            document.getElementById('deleteBtn').disabled = !domainList.length;
        }

        function startBulkDelete() {
            const domainList = document.getElementById('domainList').value.trim();
            const domains = parseUniqueDomains(domainList);
            if (!domains.length) {
                return;
            }

            if (!confirm('Bạn có chắc muốn xóa toàn bộ DNS records cho các domain đã nhập?')) {
                return;
            }

            runChunkedDelete(domains);
        }

        async function runChunkedDelete(domains) {
            showProgress();

            const total = domains.length;
            const chunks = chunkArray(domains, DELETE_CHUNK_SIZE);
            const mergedResults = [];
            let processed = 0;

            try {
                for (let i = 0; i < chunks.length; i++) {
                    const chunk = chunks[i];
                    const data = await sendDeleteChunk(chunk);

                    if (!data || (!data.success && !data.results)) {
                        throw new Error(data?.error || 'Unknown error');
                    }

                    const chunkResults = data.results || [];
                    mergedResults.push(...chunkResults);
                    processed += chunk.length;
                    updateProgress(processed, total, i + 1, chunks.length);
                }

                hideProgress();
                displayDeleteResults(mergedResults, {
                    domains_total: total,
                    domains_successful: mergedResults.filter(item => item.success).length,
                    domains_failed: mergedResults.filter(item => !item.success).length
                });
            } catch (error) {
                hideProgress();
                if (mergedResults.length) {
                    displayDeleteResults(mergedResults, {
                        domains_total: total,
                        domains_successful: mergedResults.filter(item => item.success).length,
                        domains_failed: mergedResults.filter(item => !item.success).length
                    });
                }
                showError('Lỗi xóa: ' + error.message);
            }
        }

        function sendDeleteChunk(domainsChunk) {
            const formData = new FormData();
            formData.append('action', 'bulk_delete_dns_chunk');
            domainsChunk.forEach(domain => formData.append('domains[]', domain));

            return fetch('', {
                method: 'POST',
                body: formData
            }).then(response => response.json());
        }

        function parseUniqueDomains(text) {
            if (!text) {
                return [];
            }

            const raw = text
                .split(/\r?\n|,|;/)
                .map(domain => domain.trim().toLowerCase())
                .filter(Boolean);

            return [...new Set(raw)];
        }

        function chunkArray(items, size) {
            const chunks = [];
            for (let i = 0; i < items.length; i += size) {
                chunks.push(items.slice(i, i + size));
            }
            return chunks;
        }

        function updateProgress(processed, total, currentChunk, totalChunks) {
            const percent = total > 0 ? Math.round((processed / total) * 100) : 0;
            document.getElementById('progressBar').style.width = percent + '%';
            document.getElementById('progressText').textContent = `${processed} / ${total} domains đã xử lý (lô ${currentChunk}/${totalChunks})`;
        }

        function showProgress() {
            document.getElementById('progressContainer').style.display = 'block';
            document.getElementById('deleteBtn').disabled = true;
            document.getElementById('progressText').textContent = 'Đang xử lý...';
            document.getElementById('progressBar').style.width = '0%';
        }

        function hideProgress() {
            document.getElementById('progressContainer').style.display = 'none';
            validateForm();
        }

        function displayDeleteResults(results, summary) {
            const container = document.getElementById('resultsContainer');
            const content = document.getElementById('resultsContent');
            const emptyState = document.getElementById('emptyState');
            const summaryBox = document.getElementById('resultsSummary');

            let successCount = 0;
            let errorCount = 0;
            let deletedRecords = 0;

            const resultsHtml = results.map(result => {
                const status = result.success ? 'success' : 'error';
                const statusIcon = result.success ? 'fa-check-circle' : 'fa-times-circle';
                const statusText = result.success ? 'Đã xóa' : 'Có lỗi';

                if (result.success) {
                    successCount++;
                } else {
                    errorCount++;
                }
                deletedRecords += Number(result.deleted_count || 0);

                return `
                    <div class="result-item ${status}">
                        <div class="d-flex justify-content-between align-items-start gap-3">
                            <div class="flex-grow-1">
                                <h6 class="mb-2">
                                    <i class="fas ${statusIcon} me-2"></i>
                                    ${result.domain}
                                </h6>
                                <div class="row g-2">
                                    <div class="col-md-4">
                                        <small class="text-muted d-block">Zone</small>
                                        <div>${result.zone_name || 'Không tìm thấy'}</div>
                                    </div>
                                    <div class="col-md-4">
                                        <small class="text-muted d-block">Tổng records</small>
                                        <span class="badge bg-primary">${result.total_records || 0}</span>
                                    </div>
                                    <div class="col-md-4">
                                        <small class="text-muted d-block">Đã xóa</small>
                                        <span class="badge bg-success">${result.deleted_count || 0}</span>
                                    </div>
                                </div>
                                ${result.errors && result.errors.length ? `
                                    <div class="mt-2 text-danger small">
                                        <i class="fas fa-exclamation-triangle me-1"></i>
                                        ${result.errors.join('<br>')}
                                    </div>
                                ` : ''}
                            </div>
                            <span class="badge bg-${status === 'success' ? 'success' : 'danger'}">${statusText}</span>
                        </div>
                    </div>
                `;
            }).join('');

            const totalDomains = summary?.domains_total ?? results.length;

            summaryBox.innerHTML = `
                <div class="row g-3">
                    <div class="col-md-4">
                        <div class="card text-center border-success">
                            <div class="card-body">
                                <h5 class="text-success mb-1">${successCount}</h5>
                                <small>Domain thành công</small>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="card text-center border-danger">
                            <div class="card-body">
                                <h5 class="text-danger mb-1">${errorCount}</h5>
                                <small>Domain lỗi</small>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="card text-center border-primary">
                            <div class="card-body">
                                <h5 class="text-primary mb-1">${deletedRecords}</h5>
                                <small>Tổng records đã xóa</small>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="alert alert-info mt-3 mb-0">
                    <strong>${totalDomains}</strong> domain đã xử lý.
                </div>
            `;

            content.innerHTML = resultsHtml;
            emptyState.style.display = 'none';
            container.style.display = 'block';
            container.scrollIntoView({ behavior: 'smooth' });
        }

        function showError(message) {
            const alertDiv = document.createElement('div');
            alertDiv.className = 'alert alert-danger alert-dismissible fade show';
            alertDiv.innerHTML = `
                <i class="fas fa-exclamation-triangle me-2"></i>
                ${message}
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            `;
            document.querySelector('.container-fluid').insertBefore(alertDiv, document.querySelector('.container-fluid').firstChild);
            setTimeout(() => alertDiv.remove(), 5000);
        }
    </script>c
</body>
</html>

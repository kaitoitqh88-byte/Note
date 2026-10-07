<?php
require_once 'config.php';
require_once 'CloudflareAPI.php';


// var_dump(CLOUDFLARE_API_TOKEN);

// Khởi tạo đối tượng API Cloudflare
$cloudflare = new CloudflareAPI(null, null, 75);

// Định nghĩa đường dẫn file cache các Zone
function getZonesCachePath()
{
	return __DIR__ . '/IDNPunycodeConverter/zones_cache.json';
}

function getVpsDomainsMapPath()
{
	return __DIR__ . '/IDNPunycodeConverter/domain_ip_1.json';
}

function loadVpsDomainsMap()
{
	$path = getVpsDomainsMapPath();
	if (!file_exists($path))
		return [];
	$data = json_decode((string) file_get_contents($path), true);
	return is_array($data) ? $data : [];
}

function saveVpsDomainsMap(array $records)
{
	$path = getVpsDomainsMapPath();
	$dir = dirname($path);
	if (!is_dir($dir)) {
		mkdir($dir, 0777, true);
	}

	// Cực kỳ quan trọng: array_values để reset lại toàn bộ các key từ 0, 1, 2...
	// Tránh việc array_filter tạo ra mảng khuyết key biến thành Object JSON
	$pureArray = array_values($records);

	$json = json_encode($pureArray, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
	if ($json === false) {
		throw new RuntimeException('Không thể mã hóa dữ liệu: ' . json_last_error_msg());
	}

	if (file_put_contents($path, $json . PHP_EOL, LOCK_EX) === false) {
		throw new RuntimeException('Không thể ghi file cấu hình tại đường dẫn: ' . $path);
	}
}

// -------------------------------------------------------------
// XỬ LÝ AJAX TỪ TRÌNH DUYỆT
// -------------------------------------------------------------

// AJAX 1: Tìm kiếm tức thì dựa trên dữ liệu file JSON nội bộ
if (isset($_POST['ajax']) && $_POST['ajax'] === 'search_local' && isset($_POST['ip'])) {
	header('Content-Type: application/json');
	$ip = trim($_POST['ip']);
	$allRecords = loadVpsDomainsMap();
	$found = [];

	foreach ($allRecords as $record) {
		if ((string) ($record['content'] ?? '') === $ip) {
			$found[] = [
				'zone_id' => $record['zone_id'] ?? '',
				'zone_name' => $record['zone_name'] ?? '',
				'domain' => $record['name'] ?? '',
				'type' => $record['type'] ?? 'A'
			];
		}
	}
	echo json_encode(['success' => true, 'domains' => $found]);
	exit;
}

// AJAX 2: Đồng bộ tải bản ghi DNS của một Zone cụ thể
if (isset($_POST['ajax']) && $_POST['ajax'] === 'sync_zone' && isset($_POST['zone_id'])) {
	header('Content-Type: application/json');
	$zoneId = $_POST['zone_id'];
	$ipAjax = $_POST['ip'] ?? "";
	// echo($zoneId);
	try {
		$dnsResp = $cloudflare->getDNSRecords($zoneId, ['type' => 'A'], false);
		// echo (json_encode($dnsResp));
		$dnsRecords = $dnsResp['result'] ?? [];
		$cleanRecords = [];
		foreach ($dnsRecords as $record) {
			// echo json_encode($record);
			if (($record['type'] ?? '') === 'A') {
				$cleanRecords[] = [
					'id' => $record['id'] ?? '',
					'name' => $record['name'] ?? '',
					'type' => $record['type'] ?? 'A',
					'content' => $record['content'] ?? '',
					'zone_id' => $zoneId,
					'zone_name' => $record['zone_name'] ?? ''
				];
			}
		}

		// Đọc database hiện tại từ file lên
		$allRecords = loadVpsDomainsMap();

		// Loại bỏ các bản ghi cũ thuộc chính zoneId này để tránh trùng lặp dữ liệu cũ/mới
		if (!empty($allRecords)) {
			$allRecords = array_filter($allRecords, function ($r) use ($zoneId) {
				return ($r['zone_id'] ?? '') !== $zoneId;
			});
		}

		// Gộp mảng mới vào mảng cũ
		$merged = array_merge($allRecords, $cleanRecords);

		// Tiến hành lưu file với mảng đã được dọn sạch
		// var_dump($merged);
		saveVpsDomainsMap($merged);

		echo json_encode([
			'success' => true,
			'record_count' => count($cleanRecords),
			'total_in_file' => count($merged)
		]);
	} catch (Exception $e) {
		// Trả lỗi chi tiết ra màn hình console hoặc log nếu không ghi được file
		echo json_encode(['success' => false, 'error' => $e->getMessage()]);
	}
	exit;
}

// -------------------------------------------------------------
// LOGIC CACHE $ZONES TRONG 60 PHÚT
// -------------------------------------------------------------
$ZONES_LIMIT = 3000;
$zones = [];
$error = null;
$cacheFile = getZonesCachePath();
$cacheDuration = 3600; // 60 phút = 3600 giây
$isFromCache = false;

try {
	// Kiểm tra xem file cache có tồn tại và còn trong hạn 60 phút không
	if (file_exists($cacheFile) && (time() - filemtime($cacheFile) < $cacheDuration)) {
		$cacheData = json_decode((string) file_get_contents($cacheFile), true);
		if (is_array($cacheData) && isset($cacheData['data'])) {
			$zones = $cacheData['data'];
			$isFromCache = true;
		}
	}

	// Nếu không có cache hoặc cache quá 60 phút, gọi API Cloudflare để cập nhật mới
	if (empty($zones)) {
		$zonesResp = $cloudflare->getAllZonesPaginated($ZONES_LIMIT, false); // Tắt cache nội bộ của class để tự quản lý thời gian
		$zones = $zonesResp['result'] ?? [];

		if (!empty($zones)) {
			$dir = dirname($cacheFile);
			if (!is_dir($dir))
				mkdir($dir, 0777, true);
			// Đóng gói cấu trúc kèm cấu trúc thời gian để lưu trữ
			$dataToCache = [
				'timestamp' => time(),
				'count' => count($zones),
				'data' => $zones
			];
			file_put_contents($cacheFile, json_encode($dataToCache, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE), LOCK_EX);
		}
	}
} catch (Exception $e) {
	$error = $e->getMessage();
}
?>
<!DOCTYPE html>
<html lang="vi">

<head>
	<meta charset="UTF-8">
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
	<title>Đồng bộ & Tìm kiếm Domain</title>
	<link href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.1.3/css/bootstrap.min.css" rel="stylesheet">
</head>

<body class="bg-light">
	<div class="container mt-5">
		<div class="card shadow-sm mb-4">
			<div class="card-body">
				<h2 class="card-title mb-4">⚙️ Hệ thống quản lý & Quét danh sách Domain</h2>

				<div class="alert alert-info d-flex align-items-center justify-content-between py-3">
					<div>
						<strong>Quét tất cả dữ liệu:</strong> Tải cấu hình IP (DNS bản ghi A) của toàn bộ
						<strong><?php echo count($zones); ?></strong> Domain trên Cloudflare lưu về VPS.
						<br>
						<small class="text-muted">
							Status:
							<?php echo $isFromCache ? '⚡ Đang lấy danh sách từ tệp JSON (Cache 60 phút)' : '🌐 Vừa cập nhật danh sách mới từ Cloudflare API'; ?>
						</small>
					</div>
					<button type="button" class="btn btn-primary fw-bold" id="syncBtn">🔄 Bắt đầu quét & Lưu dữ
						liệu</button>
				</div>

				<div class="mb-3" id="syncProgressContainer" style="display:none;">
					<label class="form-label fw-bold" id="syncStatus">Đang xử lý...</label>
					<div class="progress" style="height: 25px;">
						<div class="progress-bar progress-bar-striped progress-bar-animated bg-success"
							id="syncProgressBar" style="width: 0%">0%</div>
					</div>
				</div>

				<hr>

				<form id="searchForm" class="row g-3 mt-2" onsubmit="return false;">
					<div class="col-md-8">
						<label class="form-label fw-bold">Nhập địa chỉ IP cần truy vấn miền:</label>
						<input type="text" id="ipInput" class="form-control form-control-lg"
							placeholder="Ví dụ: 103.213.216.144" required>
					</div>
					<div class="col-md-4 d-flex align-items-end">
						<button type="button" class="btn bg-dark text-white btn-lg w-100 fw-bold" id="searchBtn">🔍 Tra
							cứu tức thì (Local Search)</button>
					</div>
				</form>
			</div>
		</div>

		<div id="resultsArea"></div>

		<?php if ($error): ?>
			<div class="alert alert-danger shadow-sm"><strong>Lỗi Cloudflare API:</strong>
				<?php echo htmlspecialchars($error); ?></div>
		<?php endif; ?>
	</div>

	<script>
		const ZONE_IDS = <?php echo json_encode(array_map(fn($z) => $z['id'], $zones)); ?>;
		const ZONE_NAMES = <?php echo json_encode(array_column($zones, 'name', 'id')); ?>;

		document.addEventListener('DOMContentLoaded', function () {
			const ipInput = document.getElementById('ipInput');
			const searchBtn = document.getElementById('searchBtn');
			const syncBtn = document.getElementById('syncBtn');
			const resultsArea = document.getElementById('resultsArea');

			const syncProgressContainer = document.getElementById('syncProgressContainer');
			const syncProgressBar = document.getElementById('syncProgressBar');
			const syncStatus = document.getElementById('syncStatus');

			let isSyncing = false;

			// 1. TÌM KIẾM TỪ FILE LOCAL JSON
			searchBtn.addEventListener('click', function () {
				const ip = ipInput.value.trim();
				if (!ip) {
					alert('Vui lòng điền IP cần tìm kiếm!');
					return;
				}

				resultsArea.innerHTML = '<div class="text-center my-4"><div class="spinner-border text-dark"></div><p class="mt-2">Đọc dữ liệu database file nội bộ...</p></div>';

				fetch(window.location.pathname, {
					method: 'POST',
					headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
					body: new URLSearchParams({ ajax: 'search_local', ip: ip })
				})
					.then(res => res.json())
					.then(data => {
						if (data.success) {
							renderResultsTable(data.domains, ip);
						} else {
							resultsArea.innerHTML = '<div class="alert alert-danger">Có lỗi phát sinh trong quá trình truy vấn tệp local.</div>';
						}
					})
					.catch(() => {
						resultsArea.innerHTML = '<div class="alert alert-danger">Lỗi kết nối máy chủ nội bộ.</div>';
					});
			});

			// 2. CHẠY VÒNG LẶP ĐỒNG BỘ TUẦN TỰ TOÀN BỘ DOMAIN TỪ CLOUDFLARE
			syncBtn.addEventListener('click', function () {
				if (isSyncing) return;
				if (!ZONE_IDS.length) {
					alert('Không tìm thấy domain nào trên tài khoản Cloudflare này!');
					return;
				}
				if (!confirm('Hệ thống sẽ tải toàn bộ cấu hình DNS của ' + ZONE_IDS.length + ' domain từ Cloudflare. Quá trình này có thể mất vài phút, bạn có muốn bắt đầu?')) return;

				isSyncing = true;
				syncBtn.disabled = true;
				syncProgressContainer.style.display = 'block';

				let currentIndex = 0;

				function syncNextZone() {
					if (currentIndex >= ZONE_IDS.length) {
						isSyncing = false;
						syncBtn.disabled = false;
						syncStatus.innerHTML = `✅ <strong>Hoàn thành!</strong> Đã đồng bộ cấu hình của tất cả <strong>${ZONE_IDS.length}</strong> domain.`;
						alert('Tải dữ liệu cấu hình các domain và bản ghi DNS thành công!');
						return;
					}

					const zoneId = ZONE_IDS[currentIndex];
					const zoneName = ZONE_NAMES[zoneId] || zoneId;

					const percent = Math.floor((currentIndex / ZONE_IDS.length) * 100);
					syncProgressBar.style.width = percent + '%';
					syncProgressBar.innerHTML = percent + '%';
					syncStatus.innerHTML = `🔄 [${currentIndex + 1}/${ZONE_IDS.length}] Đang quét bản ghi DNS miền: <span class="text-primary fw-bold font-monospace">${zoneName}</span>...`;

					fetch(window.location.pathname, {
						method: 'POST',
						headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
						body: new URLSearchParams({ ajax: 'sync_zone', zone_id: zoneId })
					})
						.then(res => res.json())
						.catch(err => console.error('Lỗi phân tích cú pháp zone: ' + zoneName, err))
						.finally(() => {
							currentIndex++;
							setTimeout(syncNextZone, 100);
						});
				}

				syncNextZone();
			});

			function renderResultsTable(domains, ip) {
				if (domains.length === 0) {
					resultsArea.innerHTML = `<div class="alert alert-warning shadow-sm">Không tìm thấy domain nào cấu hình trỏ về IP: <strong>${ip}</strong>.</div>`;
					return;
				}

				let html = `<div class="card shadow-sm"><div class="card-header bg-dark text-white d-flex justify-content-between align-items-center">`;
				html += `<strong>Kết quả tìm thấy cho IP: ${ip}</strong>`;
				html += `<span class="badge bg-success">${domains.length} Tên miền khớp</span></div>`;
				html += '<div class="card-body p-0"><div class="table-responsive"><table class="table table-striped table-hover mb-0 align-middle">';
				html += '<thead class="table-light"><tr><th class="ps-3">Tên miền (Domain)</th><th>Zone quản trị gốc</th><th>Mã Cloudflare Zone ID</th><th class="text-center">Loại</th></tr></thead><tbody>';

				domains.forEach(row => {
					html += '<tr>';
					html += `<td class="ps-3"><span class="text-primary fw-bold fs-6">${row.domain}</span></td>`;
					html += `<td>${row.zone_name || '<i class="text-muted">Ẩn/Không rõ</i>'}</td>`;
					html += `<td><small class="text-muted font-monospace">${row.zone_id}</small></td>`;
					html += `<td class="text-center"><span class="badge bg-secondary">${row.type}</span></td>`;
					html += '</tr>';
				});

				html += '</tbody></table></div></div></div>';
				resultsArea.innerHTML = html;
			}
		});
	</script>
</body>

</html>
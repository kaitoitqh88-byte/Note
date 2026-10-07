<?php
require_once 'config.php';
require_once 'CloudflareAPI.php';

$cloudflare = new CloudflareAPI(null, null, 75);

// AJAX handler: tìm theo IP từng zone
if (
	isset($_POST['ajax']) && $_POST['ajax'] === 'find_by_ip' && isset($_POST['zone_id']) && isset($_POST['ip'])
) {
	header('Content-Type: application/json');
	$zoneId = $_POST['zone_id'];
	$ipAjax = $_POST['ip'];
	// echo($zoneId);
	try {
		$dnsResp = $cloudflare->getDNSRecords($zoneId, ['type' => 'A'], true);
		$dnsRecords = $dnsResp['result'] ?? [];
		$found = [];
		foreach ($dnsRecords as $record) {

			if ($record['content'] === $ipAjax) {
				$found[] = [
					'zone_id' => $zoneId,
					'zone_name' => $record['zone_name'] ?? '',
					'domain' => $record['name'],
					'type' => $record['type'],
					'dns_record' => $record // thêm toàn bộ thông tin bản ghi DNS
				];
			}
		}
		echo json_encode(['success' => true, 'domains' => $found, 'dns_records' => $dnsRecords]);
	} catch (Exception $e) {
		echo json_encode(['success' => false, 'error' => $e->getMessage()]);
	}
	exit;
}


// Định nghĩa đường dẫn file cache các Zone
function getZonesCachePath()
{
	return __DIR__ . '/IDNPunycodeConverter/zones_cache.json';
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
	<title>Tìm domain trỏ về IP</title>
	<link href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.1.3/css/bootstrap.min.css" rel="stylesheet">
</head>

<body>
	<div class="container mt-5">
		<h2 class="mb-4">🔎 Tìm tất cả domain có DNS trỏ về IP</h2>
		<form id="findByIpForm" class="row g-3 mb-4" onsubmit="return false;">
			<div class="col-md-6">
				<input type="text" name="ip" id="ipInput" class="form-control"
					placeholder="Nhập IP cần tìm (vd: 1.2.3.4)" required>
			</div>
			<div class="col-md-2 d-flex gap-2">
				<button type="button" class="btn btn-primary" id="startBtn">Bắt đầu tìm</button>
				<button type="button" class="btn btn-secondary" id="stopBtn" disabled>Tạm dừng</button>
			</div>
		</form>
		<div class="mb-2" id="progressBar"></div>
		<div class="mb-2" id="apiLog"></div>
		<div id="findByIpResults"></div>
		<?php if ($error): ?>
			<div class="alert alert-danger">Lỗi: <?php echo htmlspecialchars($error); ?></div>
		<?php endif; ?>
	</div>
	<style>
		/* Giới hạn chiều rộng tối đa của vùng kết quả và căn giữa */
		#findByIpResults {
			max-width: 900px;
			width: 90%;
			margin-left: auto;
			margin-right: auto;
		}

		/* Đảm bảo vùng kết quả chiếm 90% chiều rộng container */
		#findByIpResults {

			margin-left: auto;
			margin-right: auto;
		}

		/* Đảm bảo kết quả không bị che bởi menu cố định */
		#findByIpResults {
			position: relative;
			z-index: 20;
		}

		#findByIpResults .card {
			z-index: 21;
			position: relative;
		}

		/* Đảm bảo bảng kết quả luôn vừa với container, không bị tràn ngang */
		#findByIpResults .table-responsive {
			width: 100%;
			min-width: 0;
		}

		#findByIpResults table {
			width: 100%;
			min-width: 0;
			table-layout: auto;
		}
	</style>
	<script>
		const ZONE_IDS = <?php echo json_encode(array_map(fn($z) => $z['id'], $zones)); ?>;
		const ZONE_NAMES = <?php echo json_encode(array_column($zones, 'name', 'id')); ?>;
		const apiLogDiv = document.getElementById('apiLog');
		let apiLog = [];
		document.addEventListener('DOMContentLoaded', function () {
			const ipInput = document.getElementById('ipInput');
			const resultsDiv = document.getElementById('findByIpResults');
			const startBtn = document.getElementById('startBtn');
			const stopBtn = document.getElementById('stopBtn');
			const progressBar = document.getElementById('progressBar');
			let running = false;
			let current = 0;
			let foundDomains = [];
			let errors = 0;

			startBtn.addEventListener('click', function () {
				const ip = ipInput.value.trim();
				if (!ip) return;
				if (!ZONE_IDS.length) {
					resultsDiv.innerHTML = '<div class="alert alert-danger">Không có zone nào để kiểm tra.</div>';
					return;
				}
				running = true;
				startBtn.disabled = true;
				stopBtn.disabled = false;
				foundDomains = [];
				errors = 0;
				current = 0;
				resultsDiv.innerHTML = '<div class="alert alert-info">Đang tìm kiếm...</div>';
				progressBar.innerHTML = progressHtml(0, ZONE_IDS.length);
				apiLog = [];
				apiLogDiv.innerHTML = '';
				searchNext(ip);
			});

			stopBtn.addEventListener('click', function () {
				running = false;
				startBtn.disabled = false;
				stopBtn.disabled = true;
			});

			// Thay đổi searchNext để lưu dns_records vào log
			function searchNext(ip) {
				if (!running || current >= ZONE_IDS.length) {
					showResults(foundDomains, errors);
					startBtn.disabled = false;
					stopBtn.disabled = true;
					progressBar.innerHTML = '';
					return;
				}
				const zoneId = ZONE_IDS[current];
				// Log API call (tạm thời chưa có dns_records)
				apiLog.push({
					endpoint: window.location.pathname,
					method: 'POST',
					params: { ajax: 'find_by_ip', zone_id: zoneId, ip: ip },
					zone: ZONE_NAMES[zoneId] || zoneId,
					dns_records: null
				});
				const logIdx = apiLog.length - 1;
				renderApiLog();
				fetch(window.location.pathname, {
					method: 'POST',
					headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
					body: new URLSearchParams({ ajax: 'find_by_ip', zone_id: zoneId, ip: ip })
				})
					.then(res => res.json())
					.then(data => {
						if (data.success && data.domains.length > 0) {
							data.domains.forEach(d => {
								if (!d.zone_name && ZONE_NAMES[d.zone_id]) d.zone_name = ZONE_NAMES[d.zone_id];
							});
							foundDomains = foundDomains.concat(data.domains);
							updateResults(foundDomains, errors); // cập nhật kết quả ngay khi có domain mới
						}
						// Lưu dns_records vào log
						if (data.dns_records) {
							apiLog[logIdx].dns_records = data.dns_records;
							renderApiLog();
						}
					})
					.catch(() => {
						errors++;
					})
					.finally(() => {
						current++;
						progressBar.innerHTML = progressHtml(current, ZONE_IDS.length);
						setTimeout(() => searchNext(ip), 200);
					});
			}

			function progressHtml(done, total) {
				return `<div class="progress" style="height: 24px;">
			<div class="progress-bar" role="progressbar" style="width: ${Math.floor(100 * done / total)}%">${done} / ${total} zone</div>
		</div>`;
			}

			function updateResults(domains, errors) {
				if (domains.length === 0) {
					resultsDiv.innerHTML = '<div class="alert alert-info">Đang tìm kiếm... (chưa có domain nào khớp)</div>';
				} else {
					let html = '<div class="card mt-4"><div class="card-header"><strong>Kết quả tạm thời: ' + domains.length + ' domain</strong></div>';
					html += '<div class="card-body p-0"><div class="table-responsive"><table class="table table-bordered table-hover mb-0">';
					html += '<thead class="table-light"><tr><th>Domain</th><th>Tên zone</th><th>Zone ID</th><th>Loại</th></tr></thead><tbody>';
					domains.forEach(row => {
						html += '<tr>';
						html += '<td><span class="text-primary fw-bold">' + row.domain + '</span></td>';
						html += '<td>' + (row.zone_name || '') + '</td>';
						html += '<td>' + (row.zone_id || '') + '</td>';
						html += '<td>' + row.type + '</td>';
						html += '</tr>';
					});
					html += '</tbody></table></div></div></div>';
					if (errors > 0) html += '<div class="alert alert-warning mt-2">Có ' + errors + ' zone bị lỗi khi truy vấn.</div>';
					resultsDiv.innerHTML = html;
				}
			}
			function showResults(domains, errors) {
				if (domains.length === 0) {
					resultsDiv.innerHTML = '<div class="alert alert-warning">Không tìm thấy domain nào trỏ về IP này trên tất cả zone.' + (errors > 0 ? ' (' + errors + ' lỗi truy vấn)' : '') + '</div>';
				} else {
					let html = '<div class="card mt-4"><div class="card-header"><strong>Kết quả tìm thấy: ' + domains.length + ' domain</strong></div>';
					html += '<div class="card-body p-0"><div class="table-responsive"><table class="table table-bordered table-hover mb-0">';
					html += '<thead class="table-light"><tr><th>Domain</th><th>Tên zone</th><th>Zone ID</th><th>Loại</th></tr></thead><tbody>';
					domains.forEach(row => {
						html += '<tr>';
						html += '<td><span class="text-primary fw-bold">' + row.domain + '</span></td>';
						html += '<td>' + (row.zone_name || '') + '</td>';
						html += '<td>' + (row.zone_id || '') + '</td>';
						html += '<td>' + row.type + '</td>';
						html += '</tr>';
					});
					html += '</tbody></table></div></div></div>';
					if (errors > 0) html += '<div class="alert alert-warning mt-2">Có ' + errors + ' zone bị lỗi khi truy vấn.</div>';
					resultsDiv.innerHTML = html;
				}
			}
			function renderApiLog() {
				if (!apiLog.length) {
					apiLogDiv.innerHTML = '';
					return;
				}
				let html = '<div class="card"><div class="card-header py-2 px-3"><strong>API đã gọi gần nhất</strong></div>';
				html += '<div class="card-body p-0"><div class="table-responsive"><table class="table table-sm table-bordered mb-0">';
				html += '<thead class="table-light"><tr><th>#</th><th>Endpoint</th><th>Method</th><th>Zone</th><th>Params</th><th>DNS (IP)</th><th>DNS của domain</th></tr></thead><tbody>';
				let showLog = apiLog.slice(-10); // chỉ hiển thị 10 lần gần nhất
				showLog.forEach((log, idx) => {
					let dnsValue = log.params && log.params.ip ? log.params.ip : '';
					let dnsRecords = log.dns_records ? `<details><summary>Xem</summary><pre style='white-space:pre-wrap;max-width:400px;'>${JSON.stringify(log.dns_records, null, 2)}</pre></details>` : '';
					html += `<tr><td>${apiLog.length - 10 + idx + 1}</td><td>${log.endpoint}</td><td>${log.method}</td><td>${log.zone}</td><td><code>${JSON.stringify(log.params)}</code></td><td>${dnsValue}</td><td>${dnsRecords}</td></tr>`;
				});
				html += '</tbody></table></div></div></div>';
				apiLogDiv.innerHTML = html;
			}
		});
	</script>
</body>

</html>
<?php
// ===== Xử lý AJAX SSH sqlite3 lên đầu file =====
$vps_file = __DIR__ . '/../vps.json';
$vps_list = [];
if (file_exists($vps_file)) {
    $vps_list = json_decode(file_get_contents($vps_file), true);
    if (!is_array($vps_list)) $vps_list = [];
}

$vps_domain_count_map = [];
$vps_domains_map_file = __DIR__ . '/vps_domains_map.json';
if (file_exists($vps_domains_map_file)) {
  $saved_map = json_decode(file_get_contents($vps_domains_map_file), true);
  if (is_array($saved_map) && isset($saved_map['by_vps']) && is_array($saved_map['by_vps'])) {
    foreach ($saved_map['by_vps'] as $entry) {
      $ip = (string)($entry['ip'] ?? '');
      if ($ip === '') {
        continue;
      }
      $vps_domain_count_map[$ip] = (int)($entry['domain_count'] ?? 0);
    }
  }
}

function fetch_domains_from_vps($vps) {
  if (!$vps || empty($vps['ip']) || empty($vps['username']) || empty($vps['password'])) {
    return [];
  }

  require_once __DIR__ . '/../vendor/autoload.php';

  try {
    $ssh = new \phpseclib3\Net\SSH2($vps['ip']);
    if (!$ssh->login($vps['username'], $vps['password'])) {
      return [];
    }

    $output = $ssh->exec('sqlite3 /www/server/panel/data/default.db "SELECT name FROM sites;"');
    $domains = array_filter(array_map('trim', explode("\n", $output)));
    return array_values($domains);
  } catch (Exception $e) {
    return [];
  }
}

function save_vps_domains_to_json($by_vps) {
  $save_file = __DIR__ . '/vps_domains_map.json';
  $payload = [
    'generated_at' => date('c'),
    'total_vps' => count($by_vps),
    'by_vps' => $by_vps,
  ];

  $json = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
  if ($json === false) {
    return [
      'success' => false,
      'file' => $save_file,
      'error' => 'Không thể encode JSON.',
    ];
  }

  $written = @file_put_contents($save_file, $json);
  if ($written === false) {
    return [
      'success' => false,
      'file' => $save_file,
      'error' => 'Không thể ghi file JSON.',
    ];
  }

  return [
    'success' => true,
    'file' => $save_file,
    'bytes' => $written,
  ];
}

if (isset($_POST['ssh_sqlite3']) && isset($_POST['vps_idx'])) {
    header('Content-Type: application/json');
    $idx = intval($_POST['vps_idx']);
    $vps = $vps_list[$idx] ?? null;
  echo json_encode(fetch_domains_from_vps($vps), JSON_UNESCAPED_UNICODE);
  exit;
}

if (isset($_POST['save_domains_json'])) {
  header('Content-Type: application/json');

  $selected = $_POST['vps_indices'] ?? '';
  $selected_indices = [];
  if (is_string($selected) && $selected !== '') {
    $selected_indices = array_filter(array_map('intval', explode(',', $selected)), static function ($v) {
      return $v >= 0;
    });
  }

  if (empty($selected_indices)) {
    $selected_indices = array_keys($vps_list);
  }

  $by_vps = [];
  $total_domains = 0;

  foreach ($selected_indices as $idx) {
    $vps = $vps_list[$idx] ?? null;
    if (!$vps || empty($vps['ip'])) {
      continue;
    }

    $domains = fetch_domains_from_vps($vps);
    $domain_count = count($domains);
    $total_domains += $domain_count;

    $by_vps[] = [
      'vps_idx' => $idx,
      'ip' => (string)$vps['ip'],
      'info' => $vps['info'] ?? '',
      'team' => $vps['team'] ?? '',
      'domain_count' => $domain_count,
      'domains' => $domains,
    ];
  }

  $save_result = save_vps_domains_to_json($by_vps);
  echo json_encode([
    'total_vps' => count($by_vps),
    'total_domains' => $total_domains,
    'saved_json' => $save_result,
  ], JSON_UNESCAPED_UNICODE);
  exit;
}

if (isset($_POST['save_domains_json_data'])) {
  header('Content-Type: application/json');
  $by_vps_raw = $_POST['by_vps'] ?? '';
  $by_vps = json_decode($by_vps_raw, true);
  if (!is_array($by_vps)) {
    echo json_encode(['success' => false, 'error' => 'Dữ liệu không hợp lệ'], JSON_UNESCAPED_UNICODE);
    exit;
  }
  echo json_encode(save_vps_domains_to_json($by_vps), JSON_UNESCAPED_UNICODE);
  exit;
}

if (isset($_POST['map_domains'])) {
  header('Content-Type: application/json');

  $selected = $_POST['vps_indices'] ?? '';
  $selected_indices = [];
  if (is_string($selected) && $selected !== '') {
    $selected_indices = array_filter(array_map('intval', explode(',', $selected)), static function ($v) {
      return $v >= 0;
    });
    }

  if (empty($selected_indices)) {
    $selected_indices = array_keys($vps_list);
  }

  $by_vps = [];
  $domain_map = [];

  foreach ($selected_indices as $idx) {
    $vps = $vps_list[$idx] ?? null;
    if (!$vps || empty($vps['ip'])) {
      continue;
    }

    $domains = fetch_domains_from_vps($vps);
    $ip = (string)$vps['ip'];
    $by_vps[] = [
      'vps_idx' => $idx,
      'ip' => $ip,
      'info' => $vps['info'] ?? '',
      'team' => $vps['team'] ?? '',
      'domain_count' => count($domains),
      'domains' => $domains,
    ];

    foreach ($domains as $domain) {
      if (!isset($domain_map[$domain])) {
        $domain_map[$domain] = [];
      }
      $domain_map[$domain][] = $ip;
    }
  }

  ksort($domain_map);
  $flat_map = [];
  foreach ($domain_map as $domain => $ips) {
    $flat_map[] = [
      'domain' => $domain,
      'vps_ips' => array_values(array_unique($ips)),
    ];
  }

  $save_result = save_vps_domains_to_json($by_vps);

  echo json_encode([
    'total_vps' => count($by_vps),
    'total_unique_domains' => count($flat_map),
    'by_vps' => $by_vps,
    'domain_map' => $flat_map,
    'saved_json' => $save_result,
  ], JSON_UNESCAPED_UNICODE);
  exit;
}
?>
<style>
body {
  background: #101c10;
  font-family: 'Segoe UI', 'Roboto', Arial, sans-serif;
  min-height: 100vh;
  color: #00ff88;
}
.flex-main {
    display: flex;
    gap: 32px;
    align-items: flex-start;
    justify-content: center;
}
.flex-vpslist {
    flex: 0 0 370px;
    min-width: 320px;
    max-width: 420px;
}
.flex-result {
    flex: 1 1 0;
    min-width: 320px;
    max-width: 700px;
}
.card-modern {
  border-radius: 1.25rem;
  background: transparent;
  border: 2.5px solid #00ff88;
  box-shadow: none;
}
.card-modern .card-header {
  border-radius: 1.25rem 1.25rem 0 0;
  background: transparent;
  color: #00ff88;
  font-weight: 600;
  font-size: 1.2rem;
  letter-spacing: 1px;
  border-bottom: 2px solid #00ff88;
  box-shadow: none;
}
.table-modern {
  background: transparent;
  border-radius: 0.75rem;
  overflow: hidden;
  border: 2px solid #00ff88;
}
.table-modern th, .table-modern td {
  border: 1.5px solid #00ff88 !important;
  vertical-align: middle;
  color: #00ff88;
  background: transparent;
}
.table-modern th {
  background: transparent;
  color: #00ff88;
  font-weight: 500;
  border-bottom: 2px solid #00ff88 !important;
}
.table-modern tr {
  transition: background 0.2s;
}
.table-modern tr:hover {
  background: rgba(0,255,136,0.04);
}
.btn-modern {
  border-radius: 0.7rem;
  background: transparent;
  color: #00ff88;
  font-weight: 600;
  font-size: 1.1rem;
  border: 2px solid #00ff88;
  box-shadow: none;
  transition: background 0.2s, color 0.2s, border 0.2s;
}
.btn-modern:hover {
  background: #101c10;
  color: #00ff88;
  border-color: #00ff88;
  box-shadow: 0 0 0 2px #00ff8844;
}
.text-info {
    color: #00ff88 !important;
}
.form-control, .form-select {
  border-radius: 0.7rem;
  background: transparent;
  color: #00ff88;
  border: 2px solid #00ff88;
  box-shadow: none;
  transition: border 0.2s, box-shadow 0.2s;
}
.form-control:focus, .form-select:focus {
  border-color: #00ff88;
  box-shadow: 0 0 0 2px #00ff8844;
  background: #101c10;
  color: #00ff88;
}
pre.domain-list {
  background: transparent;
  color: #00ff88;
  padding: 12px 16px;
  border-radius: 0.7rem;
  border: 2px solid #00ff88;
  max-height: 350px;
  overflow: auto;
  font-size: 1.08em;
  margin-bottom: 0;
}
.domain-summary {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(160px, 1fr));
  gap: 12px;
  margin-bottom: 16px;
}
.domain-summary-item {
  border: 2px solid #00ff88;
  border-radius: 0.7rem;
  padding: 10px 14px;
  background: rgba(0, 255, 136, 0.03);
}
.domain-summary-label {
  display: block;
  font-size: 0.92rem;
  opacity: 0.85;
}
.domain-summary-value {
  display: block;
  font-size: 1.35rem;
  font-weight: 700;
  line-height: 1.2;
}
.domain-count-badge {
  display: inline-block;
  min-width: 56px;
  padding: 4px 10px;
  border: 2px solid #00ff88;
  border-radius: 999px;
  font-weight: 700;
  color: #00ff88;
  background: rgba(0, 255, 136, 0.04);
}
@media (max-width: 1100px) {
    .flex-main { flex-direction: column; gap: 0; }
    .flex-vpslist, .flex-result { max-width: 100%; min-width: 0; }
}
</style>
<div class="container mt-5">
  <div class="flex-main">
    <div class="flex-vpslist">
      <div class="card card-modern mb-4">
        <div class="card-header">
          <i class="fas fa-server me-2"></i>Danh sách VPS
        </div>
        <div class="card-body">
          <div class="mb-3">
            <label class="form-label">Chọn VPS:</label>
            <select id="ce963" class="form-select form-select-sm" style="max-width:350px;display:inline-block">
              <option value="">-- Chọn VPS --</option>
              <?php foreach ($vps_list as $i => $vps): ?>
                <option value="<?php echo $i; ?>"><?php echo htmlspecialchars($vps['ip'] . (isset($vps['info']) ? ' - ' . $vps['info'] : '')); ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="d-flex flex-wrap gap-2 mb-3">
            <button type="button" class="btn btn-modern" onclick="selectAllVps()">
              <i class="fas fa-check-square"></i> Chọn tất cả
            </button>
            <button type="button" class="btn btn-modern" onclick="unselectAllVps()">
              <i class="fas fa-square"></i> Bỏ chọn tất cả
            </button>
          </div>
          <div class="table-responsive mb-3">
            <table class="table table-modern table-bordered align-middle text-center" style="min-width:320px">
              <thead>
                <tr>
                  <th></th>
                  <th>IP</th>
                  <th>Số domain</th>
                  <th>Thao tác</th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($vps_list as $i => $vps): ?>
                  <tr>
                    <td><input type="checkbox" class="vps-check" name="vps_selected[]" value="<?php echo $i; ?>"></td>
                    <td class="text-info fw-bold"><?php echo htmlspecialchars($vps['ip']); ?></td>
                    <td>
                      <span class="domain-count-badge" id="domain-count-<?php echo $i; ?>"><?php echo (int)($vps_domain_count_map[$vps['ip']] ?? 0); ?></span>
                    </td>
                    <td>
                      <button type="button" class="btn btn-modern" data-vps='<?php echo htmlspecialchars(json_encode($vps), ENT_QUOTES, 'UTF-8'); ?>' onclick="runSSHSqlite3(this, <?php echo $i; ?>)"><i class="fas fa-database"></i> Lấy domain</button>
                    </td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        </div>
      </div>
    </div>
    <div class="flex-result">
      <div class="card card-modern">
        <div class="card-header">
          <i class="fas fa-list me-2"></i>Kết quả domain
        </div>
        <div class="card-body">
          <div class="d-flex flex-wrap gap-2 mb-3">
            <button type="button" class="btn btn-modern" onclick="mapDomainsBySelectedVps()">
              <i class="fas fa-project-diagram"></i> Map domain theo VPS đã chọn
            </button>
            <button type="button" class="btn btn-modern" onclick="mapDomainsByAllVps()">
              <i class="fas fa-layer-group"></i> Map domain theo tất cả VPS
            </button>
            <button type="button" class="btn btn-modern" onclick="saveDomainsJsonBySelectedVps()">
              <i class="fas fa-save"></i> Lưu danh sách domain vào JSON
            </button>
          </div>
          <div id="domainResultsAPI" class="mt-2"></div>
        </div>
      </div>
    </div>
  </div>
</div>
<script>
const vpsData = <?php echo json_encode($vps_list, JSON_UNESCAPED_UNICODE); ?>;

function updateVpsDomainCount(idx, count) {
  const target = document.getElementById(`domain-count-${idx}`);
  if (target) {
    target.textContent = Number.isFinite(count) ? count : 0;
  }
}

function countUniqueDomains(domains) {
  if (!Array.isArray(domains)) {
    return 0;
  }

  return new Set(
    domains
      .map(domain => String(domain || '').trim())
      .filter(Boolean)
  ).size;
}

function renderDomainSummary(items) {
  if (!Array.isArray(items) || !items.length) {
    return '';
  }

  const totalDomains = items.reduce((sum, item) => sum + (item.domain_count || 0), 0);
  const uniqueDomains = countUniqueDomains(
    items.flatMap(item => Array.isArray(item.domains) ? item.domains : [])
  );

  return `
    <div class='domain-summary'>
      <div class='domain-summary-item'>
        <span class='domain-summary-label'>Tổng VPS</span>
        <span class='domain-summary-value'>${items.length}</span>
      </div>
      <div class='domain-summary-item'>
        <span class='domain-summary-label'>Tổng domain</span>
        <span class='domain-summary-value'>${totalDomains}</span>
      </div>
      <div class='domain-summary-item'>
        <span class='domain-summary-label'>Domain unique</span>
        <span class='domain-summary-value'>${uniqueDomains}</span>
      </div>
    </div>`;
}

document.addEventListener('DOMContentLoaded', function() {
    const ce963 = document.getElementById('ce963');
    if (ce963) {
        ce963.addEventListener('change', function() {
            const idx = ce963.value;
            document.querySelectorAll('.vps-check').forEach(cb => cb.checked = false);
            if (idx === '') return;
            const cb = document.querySelector('.vps-check[value="' + idx + '"]');
            if (cb) {
                cb.checked = true;
                // Gọi SSH lấy domain luôn
              const btn = cb.closest('tr').querySelector('button[data-vps]');
              if (btn) runSSHSqlite3(btn, idx);
            }
        });
    }
});

      function getSelectedVpsIndices() {
        return Array.from(document.querySelectorAll('.vps-check:checked')).map(cb => cb.value);
      }

      function getAllVpsIndices() {
        return Array.from(document.querySelectorAll('.vps-check')).map(cb => cb.value);
      }

      function selectAllVps() {
        document.querySelectorAll('.vps-check').forEach(cb => {
          cb.checked = true;
        });
      }

      function unselectAllVps() {
        document.querySelectorAll('.vps-check').forEach(cb => {
          cb.checked = false;
        });
      }

      function runSSHSqlite3(button, idx) {
        let vps = {};
        try {
          vps = JSON.parse(button.getAttribute('data-vps') || '{}');
        } catch (e) {
          vps = {};
        }

    const resultsDiv = document.getElementById('domainResultsAPI');
        resultsDiv.innerHTML = `<div class='alert alert-info'>Đang lấy domain qua SSH cho VPS ${vps.ip || idx}...</div>`;
    fetch(window.location.href, {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: 'ssh_sqlite3=1&vps_idx=' + encodeURIComponent(idx)
    })
    .then(r => r.json())
    .then(data => {
        const domains = Array.isArray(data) ? data : [];
        updateVpsDomainCount(Number(idx), domains.length);
        let html = `<div class='mb-3'>`;
      html += renderDomainSummary([{
        ip: vps.ip || '',
        domain_count: domains.length,
        domains: domains
      }]);
      html += `<div><b>IP:</b> <span class='text-info'>${vps.ip || ''}</span></div>`;
        html += `<div><b>Thông tin:</b> <span>${vps.info ? vps.info : ''}</span></div>`;
        html += `<div><b>Danh sách domain:</b></div>`;
        if (Array.isArray(data)) {
            if (data.length) {
                html += `<pre class="domain-list">`;
                html += data.map(d => d).join("\n");
                html += '</pre>';
            } else {
                html += '<span class="text-warning">Không có domain nào.</span>';
            }
        } else {
            html += '<span class="text-danger">Lỗi không xác định hoặc không lấy được dữ liệu.</span>';
        }
        html += '</div>';
        resultsDiv.innerHTML = html;
    })
    .catch(err => {
        resultsDiv.innerHTML = '<div class="alert alert-danger">Lỗi AJAX: ' + err + '</div>';
    });
}

  function mapDomainsBySelectedVps() {
    const selected = getSelectedVpsIndices();
    if (!selected.length) {
      alert('Vui lòng chọn ít nhất 1 VPS để map domain.');
      return;
    }
    mapDomainsByVps(selected);
  }

  function mapDomainsByAllVps() {
    mapDomainsByVps(getAllVpsIndices());
  }

  function saveDomainsJsonBySelectedVps() {
    const selected = getSelectedVpsIndices();
    if (!selected.length) {
      alert('Vui lòng chọn ít nhất 1 VPS để lưu JSON.');
      return;
    }
    collectVpsDomainsOneByOne(selected, true);
  }

  async function collectVpsDomainsOneByOne(indices, saveOnly) {
    const resultsDiv = document.getElementById('domainResultsAPI');
    resultsDiv.innerHTML = `
      <div id="vps-progress">
        <div class="mb-2" id="vps-progress-label">Đang xử lý 0 / ${indices.length} VPS...</div>
        <div style="border:2px solid #00ff88;border-radius:0.5rem;height:16px;background:transparent;margin-bottom:8px">
          <div id="vps-progress-bar" style="height:100%;background:#00ff88;width:0%;transition:width 0.3s;border-radius:0.4rem"></div>
        </div>
        <div id="vps-log" style="max-height:220px;overflow:auto;font-size:0.95em"></div>
      </div>`;

    const byVps = [];
    const log = document.getElementById('vps-log');

    for (let i = 0; i < indices.length; i++) {
      const idx = indices[i];
      const vps = vpsData[idx] || {};
      const pct = Math.round(((i) / indices.length) * 100);
      document.getElementById('vps-progress-bar').style.width = pct + '%';
      document.getElementById('vps-progress-label').textContent = `Đang xử lý ${i + 1} / ${indices.length} VPS: ${vps.ip || idx}...`;

      try {
        const resp = await fetch(window.location.href, {
          method: 'POST',
          headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
          body: 'ssh_sqlite3=1&vps_idx=' + encodeURIComponent(idx)
        });
        const domains = await resp.json();
        if (Array.isArray(domains)) {
          updateVpsDomainCount(Number(idx), domains.length);
          byVps.push({
            vps_idx: parseInt(idx),
            ip: vps.ip || '',
            info: vps.info || '',
            team: vps.team || '',
            domain_count: domains.length,
            domains: domains
          });
          log.innerHTML += `<div>&#10003; ${vps.ip || idx}: <b>${domains.length}</b> domain</div>`;
        } else {
          log.innerHTML += `<div class="text-warning">&#10007; ${vps.ip || idx}: lỗi dữ liệu trả về</div>`;
        }
      } catch (e) {
        log.innerHTML += `<div class="text-danger">&#10007; ${vps.ip || idx}: ${e}</div>`;
      }
      log.scrollTop = log.scrollHeight;
    }

    document.getElementById('vps-progress-bar').style.width = '100%';
    document.getElementById('vps-progress-label').textContent = `Hoàn thành! Đã xử lý ${indices.length} VPS.`;

    // Lưu JSON
    let saveResult = null;
    try {
      const saveResp = await fetch(window.location.href, {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: 'save_domains_json_data=1&by_vps=' + encodeURIComponent(JSON.stringify(byVps))
      });
      saveResult = await saveResp.json();
    } catch (e) { /* ignore */ }

    if (saveOnly) {
      const totalDomains = byVps.reduce((s, v) => s + v.domain_count, 0);
      const totalUniqueDomains = countUniqueDomains(byVps.flatMap(v => Array.isArray(v.domains) ? v.domains : []));
      let html = '';
      if (saveResult && saveResult.success) {
        html = `${renderDomainSummary(byVps)}<div class='alert alert-success'>Đã lưu JSON thành công: ${saveResult.file || ''}<br>Tổng VPS: ${byVps.length} | Tổng domain: ${totalDomains} | Domain unique: ${totalUniqueDomains} | Kích thước: ${saveResult.bytes || 0} bytes</div>`;
      } else {
        html = `<div class='alert alert-danger'>Lưu JSON thất bại: ${(saveResult && saveResult.error) || 'Không rõ lỗi'}</div>`;
      }
      resultsDiv.innerHTML = html;
      return;
    }

    // Build domain map client-side
    const domainMap = {};
    byVps.forEach(v => {
      (v.domains || []).forEach(d => {
        if (!domainMap[d]) domainMap[d] = [];
        domainMap[d].push(v.ip);
      });
    });
    const flatMap = Object.keys(domainMap).sort().map(d => ({
      domain: d,
      vps_ips: [...new Set(domainMap[d])]
    }));

    let html = '';
    html += renderDomainSummary(byVps);
    html += `<div class='mb-3'>`;
    html += `<div><b>Tổng domain unique:</b> <span class='text-info'>${flatMap.length}</span></div>`;
    if (saveResult && saveResult.success) {
      html += `<div><b>Đã lưu JSON:</b> <span class='text-info'>${saveResult.file || ''}</span> (${saveResult.bytes || 0} bytes)</div>`;
    } else if (saveResult) {
      html += `<div class='text-danger'><b>Lỗi lưu JSON:</b> ${saveResult.error || 'Không rõ lỗi'}</div>`;
    }
    html += `</div>`;
    html += `<div class='mb-3'><b>Map domain -&gt; VPS:</b></div>`;
    if (flatMap.length) {
      html += `<div class='table-responsive mb-4'><table class='table table-modern table-bordered align-middle'>`;
      html += `<thead><tr><th>Domain</th><th>VPS (IP)</th><th>Số VPS chứa domain</th></tr></thead><tbody>`;
      flatMap.forEach(item => {
        const ips = Array.isArray(item.vps_ips) ? item.vps_ips : [];
        html += `<tr><td>${item.domain || ''}</td><td>${ips.join('<br>')}</td><td>${ips.length}</td></tr>`;
      });
      html += `</tbody></table></div>`;
    } else {
      html += `<div class='text-warning mb-4'>Không có domain nào để map.</div>`;
    }
    html += `<div class='mb-2'><b>Chi tiết theo VPS:</b></div>`;
    byVps.forEach(v => {
      html += `<div class='mb-3'>`;
      html += `<div><b>IP:</b> <span class='text-info'>${v.ip || ''}</span> | <b>Domain:</b> ${v.domain_count || 0}</div>`;
      if (Array.isArray(v.domains) && v.domains.length) {
        html += `<pre class='domain-list'>${v.domains.join('\n')}</pre>`;
      } else {
        html += `<div class='text-warning'>Không có domain</div>`;
      }
      html += `</div>`;
    });
    resultsDiv.innerHTML = html;
  }

  function mapDomainsByVps(indices) {
    collectVpsDomainsOneByOne(indices, false);
  }

  // Legacy function kept for compatibility
  function _mapDomainsByVpsOld(indices) {
    const resultsDiv = document.getElementById('domainResultsAPI');
    const selectedText = indices.length ? indices.length + ' VPS đã chọn' : 'tất cả VPS';
    resultsDiv.innerHTML = `<div class='alert alert-info'>Đang map domain theo ${selectedText}...</div>`;
    fetch(window.location.href, {
      method: 'POST',
      headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
      body: 'map_domains=1&vps_indices=' + encodeURIComponent(indices.join(','))
    })
    .then(r => r.json())
    .then(data => {
      if (!data || !Array.isArray(data.by_vps) || !Array.isArray(data.domain_map)) {
        resultsDiv.innerHTML = '<div class="alert alert-danger">Không nhận được dữ liệu map domain hợp lệ.</div>';
        return;
      }
      resultsDiv.innerHTML = '<div>Done</div>';
    })
    .catch(err => {
      resultsDiv.innerHTML = '<div class="alert alert-danger">Lỗi: ' + err + '</div>';
    });
  }
</script>
<?php
$currentPage = 'dashboard';

$functionGroups = [
    [
        'title' => 'Domain & DNS',
        'description' => 'Quản lý domain, DNS records và tra cứu thông tin.',
        'icon' => 'fa-globe',
        'links' => [
            ['title' => 'Thêm domain vào Cloudflare', 'url' => 'add_domain_cf.php', 'description' => 'Đưa domain mới vào Cloudflare.', 'icon' => 'fa-cloud'],
            ['title' => 'Domain Actions', 'url' => 'domain_actions.php', 'description' => 'Thao tác SSL và cache cho domain.', 'icon' => 'fa-sliders'],
            ['title' => 'Domain Status Checker', 'url' => 'domain_status_checker.php', 'description' => 'Kiểm tra trạng thái domain.', 'icon' => 'fa-heart-pulse'],
            ['title' => 'DNS Simple', 'url' => 'dns_simple.php', 'description' => 'Tạo và quản lý DNS records.', 'icon' => 'fa-network-wired'],
            ['title' => 'DNS Bulk Update', 'url' => 'dns_bulk_update.php', 'description' => 'Cập nhật DNS cho nhiều domain.', 'icon' => 'fa-layer-group'],
            ['title' => 'DNS Tools Overview', 'url' => 'dns_tools_overview.php', 'description' => 'Tổng quan các công cụ DNS.', 'icon' => 'fa-sitemap'],
            ['title' => 'Domain Extractor', 'url' => 'domain_extractor.php', 'description' => 'Trích xuất domain từ danh sách văn bản.', 'icon' => 'fa-filter'],
            ['title' => 'Tìm domain theo IP', 'url' => 'find_domains_by_ip.php', 'description' => 'Tìm domain theo địa chỉ IP.', 'icon' => 'fa-magnifying-glass-location'],
            ['title' => 'IDN Converter', 'url' => 'IDNPunycodeConverter/index.php', 'description' => 'Chuyển đổi tên miền quốc tế và Punycode.', 'icon' => 'fa-language'],
            ['title' => 'Delete DNS Records', 'url' => 'DeleteDNS.php', 'description' => 'Xóa DNS records trên Cloudflare.', 'icon' => 'fa-trash'],
        ],
    ],
    [
        'title' => 'Cloudflare & Bảo mật',
        'description' => 'Cấu hình bảo mật và quản lý rule trên Cloudflare.',
        'icon' => 'fa-shield-halved',
        'links' => [
            ['title' => 'Under Attack Mode', 'url' => 'SecurityLevel.php', 'description' => 'Quản lý chế độ bảo vệ domain.', 'icon' => 'fa-shield'],
            ['title' => 'Cloudflare Security Tool', 'url' => 'cloudflare_security_tool.html', 'description' => 'Mở bộ công cụ bảo mật Cloudflare.', 'icon' => 'fa-lock'],
            ['title' => 'Security Rules UI', 'url' => 'security_rules_ui.html', 'description' => 'Giao diện quản lý security rules.', 'icon' => 'fa-list-check'],
            ['title' => 'Abuse Reports', 'url' => 'abuse_reports.php', 'description' => 'Công cụ xử lý abuse reports.', 'icon' => 'fa-triangle-exclamation'],
        ],
    ],
    [
        'title' => 'Redirect & Cache',
        'description' => 'Quản lý chuyển hướng và cache cho website.',
        'icon' => 'fa-right-left',
        'links' => [
            ['title' => 'Bulk Redirect Manager', 'url' => 'bulk_redirect_manager.php', 'description' => 'Quản lý chuyển hướng hàng loạt.', 'icon' => 'fa-arrow-right-arrow-left'],
            ['title' => 'Redirect Manager', 'url' => 'redirect_manager.php', 'description' => 'Quản lý cấu hình redirect.', 'icon' => 'fa-turn-up'],
            ['title' => '301 Chain Checker', 'url' => 'check_301/index.php', 'description' => 'Kiểm tra chuỗi chuyển hướng 301.', 'icon' => 'fa-link'],
            ['title' => 'Cache Manager', 'url' => 'cache_manager.php', 'description' => 'Quản lý và làm mới cache.', 'icon' => 'fa-gauge-high'],
            ['title' => 'Cache TTL Bulk Update', 'url' => 'cache_ttl_bulk_update.php', 'description' => 'Cập nhật TTL cache hàng loạt.', 'icon' => 'fa-clock'],
        ],
    ],
    [
        'title' => 'VPS & aaPanel',
        'description' => 'Công cụ quản trị máy chủ và website WordPress.',
        'icon' => 'fa-server',
        'links' => [
            ['title' => 'VPS Manager', 'url' => 'vps_manager.php', 'description' => 'Quản lý VPS và kết nối máy chủ.', 'icon' => 'fa-server'],
            ['title' => 'SSH Multi Terminal', 'url' => 'IDNPunycodeConverter/runterminal.php', 'description' => 'Mở terminal SSH nhiều máy chủ.', 'icon' => 'fa-terminal'],
            ['title' => 'VPS Domain Tool', 'url' => 'IDNPunycodeConverter/vpsdomain.php', 'description' => 'Công cụ domain dành cho VPS.', 'icon' => 'fa-globe'],
            ['title' => 'aaPanel Manager', 'url' => 'aapanel_manager.php', 'description' => 'Quản lý các thao tác aaPanel.', 'icon' => 'fa-gear'],
            ['title' => 'Tạo website hàng loạt', 'url' => 'aapanel_bulk_website_creator.php', 'description' => 'Tạo website hàng loạt qua aaPanel.', 'icon' => 'fa-copy'],
            ['title' => 'WordPress Toolkit', 'url' => 'aapanel_bulk_wp_password_reset_ui.php', 'description' => 'Reset mật khẩu WordPress hàng loạt.', 'icon' => 'fa-wordpress'],
            ['title' => 'Rewrite WordPress hàng loạt', 'url' => 'aapanel_bulk_rewrite_wordpress.php', 'description' => 'Cập nhật rewrite cho nhiều website.', 'icon' => 'fa-pen-to-square'],
            ['title' => 'Reset Password Web', 'url' => 'resetpassword_web.php', 'description' => 'Đặt lại mật khẩu website.', 'icon' => 'fa-key'],
            ['title' => 'Xóa Site aaPanel', 'url' => 'delete_site_ui.php', 'description' => 'Xóa website qua giao diện aaPanel.', 'icon' => 'fa-trash-can'],
            ['title' => 'aaPanel Account Checker', 'url' => 'aapanel_account_checker.php', 'description' => 'Kiểm tra tài khoản aaPanel.', 'icon' => 'fa-user-check'],
            ['title' => 'aaPanel Log Reader', 'url' => 'aapanel_log_reader.php', 'description' => 'Đọc log từ aaPanel.', 'icon' => 'fa-file-lines'],
        ],
    ],
    [
        'title' => 'Tìm kiếm & Tiện ích',
        'description' => 'Tra cứu domain và truy cập các tiện ích khác.',
        'icon' => 'fa-magnifying-glass',
        'links' => [
            ['title' => 'Tìm kiếm', 'url' => 'search.php', 'description' => 'Tìm kiếm dữ liệu domain.', 'icon' => 'fa-magnifying-glass'],
            ['title' => 'Quick Domain Search', 'url' => 'quick_domain_search.php', 'description' => 'Tra cứu domain nhanh.', 'icon' => 'fa-bolt'],
            ['title' => 'WHOIS Lookup', 'url' => 'whois.php', 'description' => 'Tra cứu thông tin WHOIS.', 'icon' => 'fa-address-card'],
            ['title' => 'Extract Domains From List', 'url' => 'extract_domains_from_list.php', 'description' => 'Lọc domain từ danh sách đầu vào.', 'icon' => 'fa-filter'],
            ['title' => 'API Setup', 'url' => 'api_setup.php', 'description' => 'Thiết lập kết nối API.', 'icon' => 'fa-plug'],
            ['title' => 'Tools Menu', 'url' => 'tools_menu.php', 'description' => 'Mở danh mục công cụ bổ sung.', 'icon' => 'fa-toolbox'],
        ],
    ],
];
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Dashboard - Danh mục chức năng</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="assets/css/rpg-shooter-theme.css" rel="stylesheet">
    <style>
        :root {
            color-scheme: dark;
            --dashboard-green: #00ff88;
            --dashboard-muted: #aab8ad;
            --dashboard-panel: rgba(10, 20, 14, 0.94);
        }

        body {
            min-height: 100vh;
            margin: 0;
            padding: 0;
            background: #050907;
            color: #e9f3eb;
        }

        .dashboard-content {
            max-width: 1440px;
            margin: 0 auto 0 280px;
            padding: 2.5rem clamp(1rem, 3vw, 3rem);
        }

        .dashboard-header {
            margin-bottom: 2rem;
            padding: clamp(1.5rem, 4vw, 3rem);
            border: 1px solid rgba(0, 255, 136, 0.35);
            border-radius: 18px;
            background: radial-gradient(ellipse at top right, rgba(0, 255, 136, 0.12), transparent 55%), var(--dashboard-panel);
        }

        .dashboard-kicker {
            color: var(--dashboard-green);
            font-size: 0.8rem;
            font-weight: 700;
            letter-spacing: 0.16em;
            text-transform: uppercase;
        }

        .dashboard-header h1 {
            margin: 0.5rem 0;
            color: #f4fff6;
            font-size: clamp(1.8rem, 4vw, 2.8rem);
            font-weight: 700;
        }

        .dashboard-header p {
            max-width: 720px;
            margin: 0;
            color: var(--dashboard-muted);
            line-height: 1.7;
        }

        .function-group {
            margin: 2rem 0;
        }

        .group-heading {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            margin-bottom: 1rem;
        }

        .group-heading i {
            color: var(--dashboard-green);
        }

        .group-heading h2 {
            margin: 0;
            color: #f4fff6;
            font-size: 1.35rem;
        }

        .group-description {
            margin: 0.3rem 0 1rem 2rem;
            color: var(--dashboard-muted);
            font-size: 0.92rem;
        }

        .function-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(min(100%, 250px), 1fr));
            gap: 1rem;
        }

        .function-card {
            display: flex;
            min-height: 124px;
            gap: 1rem;
            align-items: flex-start;
            padding: 1.1rem;
            border: 1px solid rgba(0, 255, 136, 0.2);
            border-radius: 12px;
            background: var(--dashboard-panel);
            color: inherit;
            text-decoration: none;
            transition: border-color 160ms ease, background 160ms ease, transform 160ms ease;
        }

        .function-card:hover,
        .function-card:focus-visible {
            transform: translateY(-3px);
            border-color: var(--dashboard-green);
            background: rgba(0, 45, 25, 0.92);
            color: #fff;
            outline: none;
        }

        .function-icon {
            display: grid;
            width: 42px;
            height: 42px;
            flex: 0 0 42px;
            place-items: center;
            border-radius: 10px;
            background: rgba(0, 255, 136, 0.1);
            color: var(--dashboard-green);
        }

        .function-card h3 {
            margin: 0 0 0.4rem;
            color: #f4fff6;
            font-size: 1rem;
            font-weight: 650;
        }

        .function-card p {
            margin: 0;
            color: var(--dashboard-muted);
            font-size: 0.88rem;
            line-height: 1.5;
        }

        @media (max-width: 991px) {
            .dashboard-content {
                margin-left: 0;
                padding-top: 1.5rem;
            }
        }
    </style>
</head>
<body>
    <?php include 'includes/main_navigation.php'; ?>

    <main class="dashboard-content">
        <header class="dashboard-header">
            <div class="dashboard-kicker">Cloudflare Manager</div>
            <h1>Danh mục chức năng</h1>
            <p>Truy cập nhanh các công cụ quản lý domain, DNS, Cloudflare, cache, redirect và máy chủ. Chọn một chức năng bên dưới để mở trang tương ứng.</p>
        </header>

        <?php foreach ($functionGroups as $groupIndex => $group): ?>
            <section class="function-group" aria-labelledby="group-<?= $groupIndex ?>">
                <div class="group-heading">
                    <i class="fa-solid <?= htmlspecialchars($group['icon'], ENT_QUOTES, 'UTF-8') ?>" aria-hidden="true"></i>
                    <h2 id="group-<?= $groupIndex ?>">
                        <?= htmlspecialchars($group['title'], ENT_QUOTES, 'UTF-8') ?>
                    </h2>
                </div>
                <p class="group-description"><?= htmlspecialchars($group['description'], ENT_QUOTES, 'UTF-8') ?></p>
                <div class="function-grid">
                    <?php foreach ($group['links'] as $link): ?>
                        <a class="function-card" href="<?= htmlspecialchars($link['url'], ENT_QUOTES, 'UTF-8') ?>">
                            <span class="function-icon">
                                <i class="fa-solid <?= htmlspecialchars($link['icon'], ENT_QUOTES, 'UTF-8') ?>" aria-hidden="true"></i>
                            </span>
                            <div class="function-copy">
                                <h3><?= htmlspecialchars($link['title'], ENT_QUOTES, 'UTF-8') ?></h3>
                                <p><?= htmlspecialchars($link['description'], ENT_QUOTES, 'UTF-8') ?></p>
                            </div>
                        </a>
                    <?php endforeach; ?>
                </div>
            </section>
        <?php endforeach; ?>
    </main>
</body>
</html>

<?php
/**
 * Plugin Name: Dual URLs – Domain 1A
 * Description: Quản lý canonical & alternate giữa hai domain (desktop ↔ mobile) – tương thích Rank Math, tối ưu SEO, an toàn cache Cloudflare.
 * Version: 1.0.0
 * Author: Himas
 */

if (!defined('ABSPATH')) exit;

// ====================
// SETTINGS PAGE
// ====================
add_action('admin_menu', function () {
    add_options_page(
        'Dual URLs – Domain 1A',
        'Dual URLs – Domain 1A',
        'manage_options',
        'dual-urls-domain-1a',
        'dual_urls_domain1a_settings_page'
    );
});

add_action('admin_init', function () {
    register_setting('dual_urls_domain1a', 'dual_desktop_host');
    register_setting('dual_urls_domain1a', 'dual_mobile_host');
});

function dual_urls_domain1a_settings_page() { ?>
    <div class="wrap">
        <h1>Dual URLs – Domain 1A</h1>
        <form method="post" action="options.php">
            <?php settings_fields('dual_urls_domain1a'); ?>
            <table class="form-table" role="presentation">
                <tr>
                    <th scope="row">🌐 Domain desktop (chính)</th>
                    <td><input type="text" name="dual_desktop_host"
                               value="<?php echo esc_attr(get_option('dual_desktop_host')); ?>"
                               size="40" placeholder="vd: new88.vip" /></td>
                </tr>
                <tr>
                    <th scope="row">📱 Domain mobile (phụ)</th>
                    <td><input type="text" name="dual_mobile_host"
                               value="<?php echo esc_attr(get_option('dual_mobile_host')); ?>"
                               size="40" placeholder="vd: new88.mobi" /></td>
                </tr>
            </table>
            <?php submit_button('Lưu cài đặt'); ?>
        </form>
        <p><em>💡 Gợi ý:</em> Sitemap chỉ nên chứa domain desktop. Google sẽ hiểu mối quan hệ desktop ↔ mobile qua canonical và alternate.</p>
    </div>
<?php }

// ====================
// HELPER FUNCTIONS
// ====================
function dual_urls1a_https_scheme() {
    return 'https'; // luôn ép HTTPS trong canonical
}

function dual_urls1a_build_url_for_host($host) {
    $uri = $_SERVER['REQUEST_URI'] ?? '/';
    if ($uri === '') $uri = '/';
    $url = dual_urls1a_https_scheme() . '://' . $host . $uri;
    return dual_urls1a_strip_tracking_params($url);
}

function dual_urls1a_strip_tracking_params($url) {
    $parts = wp_parse_url($url);
    if (!$parts) return $url;

    $query = [];
    if (!empty($parts['query'])) {
        parse_str($parts['query'], $query);
    }

    $remove = [
        'utm_source','utm_medium','utm_campaign','utm_term','utm_content',
        'gclid','fbclid','yclid','mc_cid','mc_eid','ref','referrer','igshid'
    ];
    foreach ($remove as $key) {
        unset($query[$key]);
    }

    $new_query = http_build_query($query);
    $rebuilt = (isset($parts['scheme']) ? $parts['scheme'] . '://' : '') .
               ($parts['host'] ?? '') .
               (isset($parts['port']) ? ':' . $parts['port'] : '') .
               ($parts['path'] ?? '') .
               ($new_query ? '?' . $new_query : '') .
               (!empty($parts['fragment']) ? '#' . $parts['fragment'] : '');

    return $rebuilt ?: $url;
}

function dual_urls1a_get_hosts() {
    $desktop = trim(get_option('dual_desktop_host'));
    $mobile  = trim(get_option('dual_mobile_host'));
    return [$desktop, $mobile];
}

function dual_urls1a_is_host($host) {
    $current = $_SERVER['HTTP_HOST'] ?? '';
    return $current && $host && (strcasecmp($current, $host) === 0);
}

// ====================
// DESKTOP → MOBILE (alternate)
// ====================
add_action('wp_head', function () {
    list($desktop, $mobile) = dual_urls1a_get_hosts();
    if (!$desktop || !$mobile) return;

    if (dual_urls1a_is_host($desktop)) {
        $mobile_url = esc_url(dual_urls1a_build_url_for_host($mobile));
        echo '<link rel="alternate" media="only screen and (max-width: 640px)" href="' . $mobile_url . "\" />\n";
    }
}, 2);

// ====================
// MOBILE → DESKTOP (canonical)
// ====================
// Rank Math
add_filter('rank_math/frontend/canonical', function ($canonical) {
    list($desktop, $mobile) = dual_urls1a_get_hosts();
    if (!$desktop || !$mobile) return $canonical;

    if (dual_urls1a_is_host($mobile)) {
        $target = dual_urls1a_build_url_for_host($desktop);
        return esc_url($target);
    }
    return $canonical;
}, 20);

// Fallback nếu Rank Math bị tắt
add_action('wp_head', function () {
    list($desktop, $mobile) = dual_urls1a_get_hosts();
    if (!$desktop || !$mobile) return;
    if (!dual_urls1a_is_host($mobile)) return;

    if (!defined('RANK_MATH_VERSION')) {
        $target = dual_urls1a_build_url_for_host($desktop);
        echo '<link rel="canonical" href="' . esc_url($target) . "\" />\n";
    }
}, 99);

// ====================
// HEADERS
// ====================
// Tắt mặc định `Vary: User-Agent` (bật bằng filter nếu cần)
add_action('send_headers', function () {
    $enable_vary = apply_filters('dual_urls1a_send_vary', false);
    if ($enable_vary) {
        header('Vary: User-Agent', false);
    }
});

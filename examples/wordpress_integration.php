<?php
/**
 * Plugin Name:  PHP-Entwickler Job-Search
 * Description:  Live PHP-Stellen von php-entwickler.de — als Block + Shortcode.
 * Version:      1.0.0
 * Requires PHP: 8.1
 * License:      MIT
 *
 * WordPress-Plugin-Snippet — minimaler Wrapper um den MCP-Server.
 *
 * Drei Use-Cases:
 *   1) [php_entwickler_jobs skill="Laravel" city="Berlin"] Shortcode
 *   2) AJAX-Endpoint /wp-json/php-entwickler/v1/search fuer Frontend-Apps
 *   3) Cron-Task: einmal taeglich Top-5 Senior-Laravel-Jobs als Transient cachen
 *
 * In wp-config.php (optional):
 *   define('PHP_ENTWICKLER_MCP_URL', 'https://mcp.php-entwickler.de');
 *
 * Aktivierung:
 *   wp-admin → Plugins → Aktivieren
 */

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

const PHP_ENTWICKLER_MCP_DEFAULT_URL = 'https://mcp.php-entwickler.de';

/**
 * MCP-Tool-Call via WP_Http. Cached fuer 5 Minuten (Transient).
 */
function php_entwickler_call_tool(string $name, array $args): array
{
    $mcp_url = defined('PHP_ENTWICKLER_MCP_URL')
        ? PHP_ENTWICKLER_MCP_URL
        : PHP_ENTWICKLER_MCP_DEFAULT_URL;

    $cache_key = 'pe_mcp_' . md5($name . wp_json_encode($args));
    $cached = get_transient($cache_key);
    if ($cached !== false) {
        return $cached;
    }

    $response = wp_remote_post($mcp_url, [
        'headers' => [
            'Content-Type' => 'application/json',
            'User-Agent' => 'wp-php-entwickler/1.0',
        ],
        'body' => wp_json_encode([
            'jsonrpc' => '2.0',
            'id' => 1,
            'method' => 'tools/call',
            'params' => ['name' => $name, 'arguments' => (object) $args],
        ]),
        'timeout' => 15,
    ]);

    if (is_wp_error($response)) {
        return ['error' => $response->get_error_message(), 'jobs' => []];
    }

    $body = json_decode(wp_remote_retrieve_body($response), true);
    $text = $body['result']['content'][0]['text'] ?? '{}';
    $data = json_decode($text, true) ?? [];

    set_transient($cache_key, $data, 5 * MINUTE_IN_SECONDS);
    return $data;
}

/**
 * Shortcode: [php_entwickler_jobs skill="Laravel" city="Berlin" limit="5"]
 *
 * Rendert eine simple Job-Liste mit Titel, Stadt, Skills + utm-getrackte Links.
 */
function php_entwickler_jobs_shortcode($atts): string
{
    $atts = shortcode_atts([
        'skill' => '',
        'city' => '',
        'experience' => '',
        'remote' => '',
        'limit' => 5,
    ], $atts, 'php_entwickler_jobs');

    $args = ['limit' => (int) $atts['limit']];
    foreach (['skill', 'city', 'experience', 'remote'] as $key) {
        if ($atts[$key] !== '') {
            $args[$key] = $atts[$key];
        }
    }

    $data = php_entwickler_call_tool('search_jobs', $args);

    if (empty($data['jobs'])) {
        return '<p>Keine passenden Stellen gefunden.</p>';
    }

    $html = '<ul class="php-entwickler-jobs">';
    foreach ($data['jobs'] as $job) {
        $company = $job['company'] ?? '[Arbeitgeber auf Premium-Profil]';
        $location = $job['location'] ?? '—';
        $salary = $job['salary'] ?? '';
        $skills = implode(', ', array_slice($job['skills'] ?? [], 0, 5));

        $html .= sprintf(
            '<li>'
            . '<strong><a href="%s" target="_blank" rel="noopener">%s</a></strong><br>'
            . '%s · %s · %s<br>'
            . '<small>%s</small>'
            . '</li>',
            esc_url($job['url']), // ← utm-Parameter NICHT entfernen (Lizenz-Pflicht)
            esc_html($job['title']),
            esc_html($company),
            esc_html($location),
            esc_html($salary),
            esc_html($skills),
        );
    }
    $html .= '</ul>';
    $html .= '<p><small>Quelle: <a href="https://www.php-entwickler.de" target="_blank" rel="noopener">php-entwickler.de</a></small></p>';

    return $html;
}
add_shortcode('php_entwickler_jobs', 'php_entwickler_jobs_shortcode');

/**
 * REST-API Endpoint: /wp-json/php-entwickler/v1/search
 *
 * GET-Params: skill, city, experience, remote, limit
 * Beispiel:   /wp-json/php-entwickler/v1/search?skill=Laravel&city=Berlin
 */
add_action('rest_api_init', function (): void {
    register_rest_route('php-entwickler/v1', '/search', [
        'methods' => 'GET',
        'permission_callback' => '__return_true',
        'callback' => function (WP_REST_Request $request) {
            $args = [
                'limit' => min(20, (int) $request->get_param('limit') ?: 10),
            ];
            foreach (['skill', 'city', 'experience', 'remote'] as $key) {
                $value = $request->get_param($key);
                if (is_string($value) && $value !== '') {
                    $args[$key] = $value;
                }
            }
            return rest_ensure_response(php_entwickler_call_tool('search_jobs', $args));
        },
    ]);
});

/**
 * Cron-Job: einmal taeglich die Top-Stellen fuer ein Standard-Set abrufen
 * und in der DB cachen. Reduziert MCP-Roundtrips bei stark frequentierten
 * Sidebar-Widgets.
 */
add_action('php_entwickler_daily_refresh', function (): void {
    $top_jobs = php_entwickler_call_tool('search_jobs', [
        'experience' => 'senior',
        'limit' => 10,
    ]);
    update_option('php_entwickler_top_jobs', $top_jobs);
});

register_activation_hook(__FILE__, function (): void {
    if (!wp_next_scheduled('php_entwickler_daily_refresh')) {
        wp_schedule_event(time(), 'daily', 'php_entwickler_daily_refresh');
    }
});
register_deactivation_hook(__FILE__, function (): void {
    wp_clear_scheduled_hook('php_entwickler_daily_refresh');
});

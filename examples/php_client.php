<?php
/**
 * Minimal PHP client for php-entwickler.de MCP server.
 *
 * No MCP SDK required — pure curl + JSON-RPC 2.0.
 * Useful for Laravel/Symfony service integrations, scripts, integration tests.
 *
 * Requirements: PHP 8.1+ with curl extension
 *
 * Usage:
 *   php examples/php_client.php
 */

declare(strict_types=1);

const ENDPOINT = 'https://mcp.php-entwickler.de';
const USER_AGENT = 'php-entwickler-mcp-client/1.0';

/**
 * Send a JSON-RPC call and return the `result` field (or throw on error).
 */
function mcp_call(string $method, ?array $params = null, int $id = 1): mixed
{
    $payload = ['jsonrpc' => '2.0', 'id' => $id, 'method' => $method];
    if ($params !== null) {
        $payload['params'] = $params;
    }

    $ch = curl_init(ENDPOINT);
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => json_encode($payload, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE),
        CURLOPT_HTTPHEADER => ['Content-Type: application/json', 'User-Agent: ' . USER_AGENT],
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 30,
    ]);

    $body = curl_exec($ch);
    $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err = curl_error($ch);
    curl_close($ch);

    if ($body === false) {
        throw new RuntimeException("Curl error: $err");
    }
    if ($status !== 200) {
        throw new RuntimeException("HTTP $status: $body");
    }

    $data = json_decode($body, true, flags: JSON_THROW_ON_ERROR);
    if (isset($data['error'])) {
        throw new RuntimeException('MCP error: ' . $data['error']['message']);
    }

    return $data['result'] ?? null;
}

/**
 * Call a tool and unpack the inner text/JSON payload.
 */
function mcp_tool_call(string $name, array $arguments = []): mixed
{
    $result = mcp_call('tools/call', ['name' => $name, 'arguments' => (object) $arguments]);
    $text = $result['content'][0]['text'] ?? null;
    return $text !== null ? json_decode($text, true, flags: JSON_THROW_ON_ERROR) : null;
}

function demo_search_jobs(): void
{
    echo "\n=== search_jobs: Senior Laravel Berlin ===\n";
    $res = mcp_tool_call('search_jobs', [
        'skill' => 'Laravel',
        'city' => 'Berlin',
        'experience' => 'senior',
        'limit' => 5,
    ]);
    printf("  total=%d scope=%s\n", $res['total'], $res['search_scope'] ?? '—');
    foreach ($res['jobs'] as $job) {
        $company = $job['company'] ?? '[Premium-Profil erforderlich]';
        printf("  · %s @ %s\n", $job['title'], $company);
        printf("    %s · %s · %s\n", $job['experience_label'] ?? '—', $job['remote_label'] ?? '—', $job['salary'] ?? '—');
        printf("    %s\n", $job['url']);
    }
}

function demo_match_profile(): void
{
    echo "\n=== match_profile: CV-based matching ===\n";
    $matches = mcp_tool_call('match_profile', [
        'skills' => ['PHP', 'Symfony', 'Doctrine', 'Docker', 'AWS'],
        'experience_years' => 6,
        'city' => 'Muenchen',
        'remote' => 'hybrid',
        'salary_min' => 70000,
        'limit' => 3,
    ]);
    printf("  Profile: %s\n", json_encode($matches['profile']));
    printf("  Found %d matches:\n\n", $matches['returned']);
    foreach ($matches['jobs'] as $job) {
        printf("  · [%d/100] %s\n", $job['match_score'], $job['title']);
        printf("    Matched: %s\n", implode(', ', $job['matched_skills'] ?: ['—']));
        printf("    Missing: %s\n", implode(', ', $job['missing_skills'] ?: ['—']));
        printf("    %s\n", $job['url']);
    }
}

function demo_market_insights(string $skill = 'Laravel'): void
{
    echo "\n=== market_insights: $skill ===\n";
    $market = mcp_tool_call('market_insights', ['skill' => $skill]);
    printf("  Total active jobs: %d\n\n", $market['total_active_jobs']);

    echo "  Salary by experience:\n";
    foreach ($market['salary_by_experience'] as $level) {
        $min = $level['avg_min_salary'] ?? 0;
        $max = $level['avg_max_salary'] ?? 0;
        $salary = ($min && $max) ? sprintf('%s – %s €', number_format($min), number_format($max)) : '—';
        printf("    %-8s: %4d Jobs · %s\n", $level['level'], $level['job_count'], $salary);
    }

    echo "\n  Top 5 cities:\n";
    foreach (array_slice($market['top_cities'], 0, 5) as $city) {
        $avg = $city['avg_max_salary'];
        $salary = $avg ? sprintf('avg max %s €', number_format($avg)) : '—';
        printf("    %-20s: %4d Jobs · %s\n", $city['city'], $city['job_count'], $salary);
    }

    if (!empty($market['top_premium_companies'])) {
        echo "\n  Top premium employers:\n";
        foreach ($market['top_premium_companies'] as $c) {
            printf("    · %s (%d Jobs)\n", $c['name'], $c['job_count']);
        }
    }
    printf("\n  + %d additional anonymous companies\n", $market['additional_anonymous_companies']);
}

// --- Main ---
$info = mcp_call('initialize', ['protocolVersion' => '2025-06-18', 'capabilities' => (object) []]);
printf("Server: %s\n", json_encode($info['serverInfo']));

$tools = mcp_call('tools/list')['tools'];
printf("\n%d tools available:\n", count($tools));
foreach ($tools as $t) {
    printf("  - %-18s %s...\n", $t['name'], substr(explode('.', $t['description'])[0], 0, 80));
}

demo_search_jobs();
demo_match_profile();
demo_market_insights('Laravel');

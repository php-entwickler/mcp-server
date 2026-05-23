# php-entwickler.de MCP Server

> Public Model Context Protocol server for [php-entwickler.de](https://www.php-entwickler.de) — live access to active PHP, Laravel, Symfony, WordPress & Shopware jobs in Germany from Claude, Cursor & co.

[![MCP Endpoint](https://img.shields.io/badge/MCP-mcp.php--entwickler.de-blue)](https://mcp.php-entwickler.de)
[![Protocol Version](https://img.shields.io/badge/Protocol-2025--06--18-green)]()
[![Transport](https://img.shields.io/badge/Transport-Streamable%20HTTP-orange)]()
[![Tools](https://img.shields.io/badge/Tools-5-violet)]()
[![License](https://img.shields.io/badge/Data%20License-Attribution%20required-yellow)](https://www.php-entwickler.de/mcp/nutzungsbedingungen)

## What is this?

A public MCP (Model Context Protocol) endpoint that gives any MCP-compatible LLM client — Claude Desktop, Claude Code, Cursor, ChatGPT (once MCP-enabled), Continue.dev, etc. — structured live access to Germany's active PHP / Laravel / Symfony / WordPress / Shopware job market.

Instead of asking your LLM to browse the web, your LLM can call typed tools that return up-to-date job data straight from our search index.

**Endpoint:** `https://mcp.php-entwickler.de`

## Available tools

| Tool | Description |
|---|---|
| `search_jobs` | Search active PHP/Laravel/Symfony/WordPress/Shopware/TYPO3/Magento/Drupal jobs in Germany. Filters: skill, city, experience, remote model, salary. **Automatic radius expansion 10 → 20 → 30 → 50 km** for cities with no direct matches. |
| `get_job` | Get full details of a single job (description, requirements, benefits, skills, salary, application URL). |
| `match_profile` | CV-based matching — pass `skills[]`, `experience_years`, optional location + remote + salary, get jobs sorted by match score (0–100) with `matched_skills` and `missing_skills` per job. Ideal when a user uploads their resume. |
| `similar_jobs` | Find jobs similar to a reference job ID via MoreLikeThis search over title, skills and description. |
| `market_insights` | Market analysis for a skill — total active jobs, top cities, salary by experience level, remote distribution, top premium (verified/claimed) employers. |

All responses include German display labels (`experience_label`, `remote_label`) alongside the programmatic codes (`experience`, `remote`).

## Setup

### Claude Code (CLI)
```bash
claude mcp add --transport http php-entwickler https://mcp.php-entwickler.de
```

### Claude Desktop
Edit `~/Library/Application Support/Claude/claude_desktop_config.json` (macOS) or `%APPDATA%\Claude\claude_desktop_config.json` (Windows):

```json
{
  "mcpServers": {
    "php-entwickler": {
      "type": "http",
      "url": "https://mcp.php-entwickler.de"
    }
  }
}
```

Restart Claude Desktop.

### Cursor
`Settings → MCP → Add Server`:
- **Type:** `http`
- **URL:** `https://mcp.php-entwickler.de`

### Windsurf (Codeium)
In `~/.codeium/windsurf/mcp_config.json`:
```json
{
  "mcpServers": {
    "php-entwickler": { "serverUrl": "https://mcp.php-entwickler.de" }
  }
}
```

### Continue.dev (VS Code / JetBrains)
In `~/.continue/config.json`:
```json
{
  "experimental": {
    "modelContextProtocolServers": [
      {
        "transport": {
          "type": "streamable-http",
          "url": "https://mcp.php-entwickler.de"
        }
      }
    ]
  }
}
```

### Cline / Roo Code (VS Code)
Cline → ⚙️ → **MCP Servers** → **Edit MCP Settings**:
```json
{
  "mcpServers": {
    "php-entwickler": {
      "url": "https://mcp.php-entwickler.de",
      "disabled": false,
      "autoApprove": []
    }
  }
}
```

### Zed Editor
In `~/.config/zed/settings.json`:
```json
{
  "context_servers": {
    "php-entwickler": { "url": "https://mcp.php-entwickler.de" }
  }
}
```

### Anthropic Messages API (direkt im eigenen Code)
Mit dem offiziellen Anthropic SDK (Python/TypeScript) lässt sich der MCP-Server als `mcp_servers`-Parameter übergeben — Claude verbindet sich automatisch.

**Python** (`pip install anthropic`):
```python
client.beta.messages.create(
    model="claude-sonnet-4-6",
    max_tokens=1024,
    mcp_servers=[{"type": "url", "url": "https://mcp.php-entwickler.de", "name": "php-entwickler"}],
    messages=[{"role": "user", "content": "Welche Senior-Laravel-Jobs in Berlin?"}],
    extra_headers={"anthropic-beta": "mcp-client-2025-04-04"},
)
```

Full demo → [`examples/anthropic_api.py`](./examples/anthropic_api.py) · [`examples/anthropic_api.ts`](./examples/anthropic_api.ts)

### Laravel (Prism + Prism Relay)
Native MCP-Integration via [Prism](https://prismphp.com/) + [Relay](https://github.com/prism-php/relay):
```bash
composer require prism-php/prism prism-php/relay
php artisan vendor:publish --tag="relay-config"
```
`config/relay.php`:
```php
'servers' => [
    'php-entwickler' => [
        'url' => 'https://mcp.php-entwickler.de',
        'timeout' => 30,
        'transport' => \Prism\Relay\Enums\Transport::Http,
    ],
],
```
```php
$response = Prism::text()
    ->using(Provider::Anthropic, 'claude-sonnet-4-6')
    ->withPrompt('Senior-Symfony-Jobs in München?')
    ->withTools(Relay::tools('php-entwickler'))
    ->asText();
```
Vollständiges Beispiel (inkl. Laravel AI offiziell, CV-Match-Endpoint) → [`examples/laravel_integration.php`](./examples/laravel_integration.php)

### Symfony
Anthropic SDK + `mcp_servers` direkt — voll funktionsfähig ohne separates AI-Bundle. Vollständiges Beispiel mit Controller + RateLimit + Validation → [`examples/symfony_integration.php`](./examples/symfony_integration.php)

### WordPress
Shortcode `[php_entwickler_jobs skill="Laravel" city="Berlin"]` + REST-API + Cron — komplettes Plugin-Snippet → [`examples/wordpress_integration.php`](./examples/wordpress_integration.php)

### OpenAI / Gemini (kein nativer MCP-Support)
MCP-Tools via `tools/list` abrufen, Schemas zu Function-Calling konvertieren, dann eigenes Roundtripping. Pseudo-Code-Skizze ist im Setup-Guide weiter unten.

### Direct HTTP (curl / programmatic)
```bash
curl -X POST https://mcp.php-entwickler.de \
  -H "Content-Type: application/json" \
  -H "User-Agent: your-app/1.0" \
  -d '{"jsonrpc":"2.0","id":1,"method":"tools/list"}'
```

### CV-Match example
```bash
curl -X POST https://mcp.php-entwickler.de \
  -H "Content-Type: application/json" \
  -H "User-Agent: your-app/1.0" \
  -d '{
    "jsonrpc": "2.0",
    "id": 1,
    "method": "tools/call",
    "params": {
      "name": "match_profile",
      "arguments": {
        "skills": ["PHP", "Symfony", "Doctrine", "Docker", "AWS"],
        "experience_years": 6,
        "city": "Muenchen",
        "remote": "hybrid",
        "salary_min": 70000,
        "limit": 5
      }
    }
  }'
```

## All examples at a glance

| File | Language / Framework |
|---|---|
| [`curl.sh`](./examples/curl.sh) | Shell (10 ready-to-run curl calls) |
| [`python_client.py`](./examples/python_client.py) | Python (pure HTTP) |
| [`typescript_client.ts`](./examples/typescript_client.ts) | TypeScript / Node.js (built-in fetch) |
| [`php_client.php`](./examples/php_client.php) | PHP 8.1+ (pure curl) |
| [`go_client.go`](./examples/go_client.go) | Go (net/http) |
| [`rust_client.rs`](./examples/rust_client.rs) | Rust (reqwest + tokio) |
| [`anthropic_api.py`](./examples/anthropic_api.py) | Python · Anthropic SDK with `mcp_servers` |
| [`anthropic_api.ts`](./examples/anthropic_api.ts) | TypeScript · Anthropic SDK with `mcp_servers` |
| [`laravel_integration.php`](./examples/laravel_integration.php) | Laravel · Laravel AI / Prism Relay / Anthropic SDK |
| [`symfony_integration.php`](./examples/symfony_integration.php) | Symfony · Controller + RateLimit + Validation |
| [`wordpress_integration.php`](./examples/wordpress_integration.php) | WordPress · Plugin-Snippet (Shortcode + REST + Cron) |
| [`claude_desktop_config.json`](./examples/claude_desktop_config.json) | Claude Desktop config |

**Integration-Reihenfolge pro Use-Case:**

1. **„Ich will MCP einfach in meinem LLM-Chat-Client nutzen"** → siehe Setup-Sektion oben (Claude Desktop / Cursor / Windsurf / Continue / Cline / Zed)
2. **„Ich baue einen LLM-Service in eigener App"** → Anthropic SDK mit `mcp_servers` (Python/TS) oder Laravel AI / Prism Relay (PHP)
3. **„Ich brauche nur strukturierte Job-Daten, ohne LLM"** → Pure-HTTP-Beispiele (`*_client.{ts,py,php,go,rs}`)

## Example prompts that trigger tool calls

Once configured, your LLM will automatically call the right tool:

- *„Welche Senior-Laravel-Jobs gibt es in Berlin mit mindestens 80 k €?"* → `search_jobs`
- *„Hier ist mein Lebenslauf — welche aktuellen PHP-Stellen passen am besten?"* → `match_profile`
- *„Wie steht der Symfony-Markt aktuell? Wie viele Jobs, welche Gehaltsspannen?"* → `market_insights`
- *„Zu Job 107175 — welche vergleichbaren Positionen gibt es noch?"* → `similar_jobs`
- *„Hol mir alle Details zu Job 107175."* → `get_job`
- *„Finde Symfony-Stellen 30 km um München mit Docker-Erfahrung."* → `search_jobs` with `radius_km: 30`

## Response shape

Every tool response includes:

- **Job fields**: `id`, `title`, `company` (null if not premium/verified), `location`, `skills`, `experience` + `experience_label`, `remote` + `remote_label`, `salary`, `description_excerpt`, `responsibilities`, `requirements`, `benefits`, `published_at`, `url` (with `utm_*` tracking — do not strip).
- **Brand fields**: `source`, `source_url`, `attribution`, `license`, `license_url`, `commercial_inquiries`.
- **Premium hint**: tools that list jobs include `premium_hint` explaining why some `company` fields are null.

### Auto-radius example
`search_jobs(city="Coburg", limit=5)` may return:
```json
{
  "total": 7,
  "radius_km": 50,
  "radius_was_expanded": true,
  "search_scope": "Umkreis 50 km um Coburg",
  "jobs": [...]
}
```
If no jobs in 10 km → tries 20 → 30 → 50 km, then returns empty (no nationwide fallback — beyond the commute area, users should pick a different city).

## Limits & Policy

| Limit | Value |
|---|---|
| Region | DACH (DE/AT/CH) + LLM backend regions (US, GB, IE). Other countries blocked at CloudFront edge. |
| Rate limit | 30 req/min/IP + 1.000 req/day/IP |
| Authentication | None — public, anonymous |
| Cache TTL | 5 min (search/match), 1 h (job detail) |

## License — Code vs Data

**Code** (this repository): [MIT](./LICENSE).

**Data** (job postings, company info, aggregates served by the live endpoint): proprietary, **non-commercial use with mandatory attribution**.

- ✅ Allowed: LLM-chat answers, personal research, education, demos — with `php-entwickler.de` cited as source and clickable job URLs (including `utm_*` parameters) intact.
- ❌ Not allowed: commercial re-use without license, building your own aggregator, bulk export, caching > 24 h, stripping UTM parameters, omitting attribution, IP-rotation to bypass rate limits.
- 📞 Commercial integration / API partnership / higher limits: [info@php-entwickler.de](mailto:info@php-entwickler.de)
- 📜 Full terms: [php-entwickler.de/mcp/nutzungsbedingungen](https://www.php-entwickler.de/mcp/nutzungsbedingungen)

Legal basis: §87a UrhG (German database protection) + UWG (unfair competition). Violations may result in IP block, takedown notice, and legal action.

## Data freshness

- Job index is updated multiple times per day via our internal ingestion pipeline.
- Tool responses are cached server-side per query (5 min for searches/matches, 1 h for individual job details).

## Protocol details

- **Transport:** Streamable HTTP (single endpoint, POST for JSON-RPC)
- **Protocol version:** `2025-06-18`
- **Methods supported:** `initialize`, `tools/list`, `tools/call`, `notifications/initialized`

## Issues & contact

- Bugs / feature requests: open a GitHub issue
- Commercial integration / higher limits: [info@php-entwickler.de](mailto:info@php-entwickler.de)
- General: [php-entwickler.de/kontakt](https://www.php-entwickler.de/kontakt)

## About

`php-entwickler.de` is Germany's specialized PHP / Laravel / Symfony job board.

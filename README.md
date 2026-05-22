# php-entwickler.de MCP Server

> Public Model Context Protocol server for [php-entwickler.de](https://www.php-entwickler.de) — live access to active PHP, Laravel, Symfony, WordPress & Shopware jobs in Germany from Claude, Cursor & co.

[![MCP Endpoint](https://img.shields.io/badge/MCP-mcp.php--entwickler.de-blue)](https://mcp.php-entwickler.de)
[![Protocol Version](https://img.shields.io/badge/Protocol-2025--06--18-green)]()
[![Transport](https://img.shields.io/badge/Transport-Streamable%20HTTP-orange)]()
[![Region](https://img.shields.io/badge/Region-DACH%20only-yellow)]()

## What is this?

A public MCP (Model Context Protocol) endpoint that gives any MCP-compatible LLM client — Claude Desktop, Claude Code, Cursor, ChatGPT (once MCP-enabled), Continue.dev, etc. — structured live access to Germany's active PHP / Laravel / Symfony / WordPress / Shopware job market.

Instead of asking your LLM to browse the web, your LLM can call typed tools that return up-to-date job data straight from our search index.

**Endpoint:** `https://mcp.php-entwickler.de`

## Available tools

Currently active (MVP):

| Tool | Description |
|---|---|
| `search_jobs` | Search active PHP/Laravel/Symfony/WordPress/Shopware/TYPO3/Magento/Drupal jobs in Germany. Filters: tech, city, experience level, work model (remote/hybrid/onsite), minimum salary. |
| `get_job` | Get full details of a single job (description, requirements, benefits, skills, salary, application URL). |

**Coming soon** (data quality + opt-in policy work in progress):

- `get_salary_range` — aggregated junior/mid/senior salary ranges per framework. Currently disabled until we ship our dedicated salary data source.
- `top_companies_hiring` — top employers per tech stack and city. Disabled until we publish only claimed/verified company profiles (opt-in via [/fuer-arbeitgeber](https://www.php-entwickler.de/fuer-arbeitgeber)).
- `compare_cities` — city-by-city comparison of jobs and salary spread. Depends on `get_salary_range`.

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

### Direct HTTP (curl / programmatic)
```bash
curl -X POST https://mcp.php-entwickler.de \
  -H "Content-Type: application/json" \
  -H "User-Agent: your-app/1.0" \
  -d '{"jsonrpc":"2.0","id":1,"method":"tools/list"}'
```

More examples in [`examples/`](./examples).

## Example prompts that trigger tool calls

Once configured, your LLM will automatically call the right tool:

- *„Welche Senior-Laravel-Jobs gibt es in Berlin mit mindestens 80 k €?"* → `search_jobs`
- *„Zeig mir 5 Remote-Symfony-Stellen, sortiert nach Aktualität."* → `search_jobs`
- *„Hol mir alle Details zu Job 107175."* → `get_job`
- *„Vergleiche WordPress-Devs Hamburg vs. Köln — welche Stellen sind aktiv?"* → multiple `search_jobs` calls

## Limits & Policy

| Limit | Wert |
|---|---|
| Region | DACH only (DE, AT, CH) — CloudFront-Edge-Block (HTTP 403 für andere) |
| Rate limit | 30 req/min/IP + 1.000 req/Tag/IP |
| Authentication | None — public, anonymous |
| User-Agent | Required (anti-bot) |
| Cache TTL | 5 min (search), 1 h (job detail) |
| Cost (commercial integration) | Contact us before re-hosting / mass-scraping |

## Data freshness

- Job index is updated multiple times per day via our internal ingestion pipeline.
- Tool responses are cached server-side per query (5 min for searches, 1 h for individual job details).
- Branding/CTA fields (`_powered_by`, `_explore`) are appended to every response — please leave them intact so end-users can find their way back to the canonical listings.

## Protocol details

- **Transport:** Streamable HTTP (single endpoint, POST for JSON-RPC, GET returns server info)
- **Protocol version:** `2025-06-18`
- **Methods supported:** `initialize`, `tools/list`, `tools/call`, `notifications/initialized`

## Issues & contact

- Bugs / feature requests: open a GitHub issue
- Commercial integration / higher limits: [kontakt@php-entwickler.de](mailto:kontakt@php-entwickler.de)
- General: [php-entwickler.de/kontakt](https://www.php-entwickler.de/kontakt)

## About

`php-entwickler.de` is operated by [codehero GmbH](https://www.codehero.gmbh). We're Germany's specialized PHP / Laravel / Symfony job board.

## License

MIT — see [LICENSE](./LICENSE).

The MCP server **code** in this repo is MIT-licensed. The **data** served by the live endpoint (job postings, company information) is aggregated from multiple sources and subject to its respective terms — do not re-publish at scale without permission.
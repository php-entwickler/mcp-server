"""
Minimal Python client for php-entwickler.de MCP server.

No MCP SDK required — pure HTTP + JSON-RPC 2.0.
Useful for scripting / data pipelines / integration tests.

Usage:
    pip install httpx
    python examples/python_client.py
"""

from __future__ import annotations

import json
from typing import Any

import httpx

ENDPOINT = "https://mcp.php-entwickler.de"
HEADERS = {
    "Content-Type": "application/json",
    "User-Agent": "php-entwickler-mcp-client/1.0",
}


def call(method: str, params: dict | None = None, *, request_id: int = 1) -> Any:
    """Send a JSON-RPC call and return the `result` field (or raise on error)."""
    payload = {"jsonrpc": "2.0", "id": request_id, "method": method}
    if params is not None:
        payload["params"] = params
    res = httpx.post(ENDPOINT, headers=HEADERS, json=payload, timeout=30.0)
    res.raise_for_status()
    data = res.json()
    if "error" in data:
        raise RuntimeError(f"MCP error: {data['error']}")
    return data.get("result")


def tool_call(name: str, arguments: dict | None = None) -> Any:
    """Call a tool and unpack the inner text/JSON payload."""
    result = call("tools/call", {"name": name, "arguments": arguments or {}})
    content = result.get("content") or []
    if not content:
        return None
    return json.loads(content[0]["text"])


def main() -> None:
    # Handshake (optional for stateless servers but recommended)
    info = call(
        "initialize",
        {"protocolVersion": "2025-06-18", "capabilities": {}},
    )
    print("Server:", info["serverInfo"])

    # List tools
    tools = call("tools/list")["tools"]
    print(f"\n{len(tools)} tools available:")
    for t in tools:
        print(f"  - {t['name']}: {t['description'][:80]}…")

    # Search Senior Laravel jobs in Berlin
    print("\n→ Senior Laravel Berlin (top 5):")
    jobs = tool_call(
        "search_jobs",
        {"skill": "Laravel", "city": "Berlin", "experience": "senior", "limit": 5},
    )
    print(f"  total={jobs['total']} returned={jobs['returned']}")
    for job in jobs["jobs"]:
        salary = job.get("salary") or "—"
        print(f"  · {job['title']} — {salary} — {job['url']}")


if __name__ == "__main__":
    main()

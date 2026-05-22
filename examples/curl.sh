#!/usr/bin/env bash
# php-entwickler.de MCP Server — direct HTTP examples (Streamable HTTP / JSON-RPC 2.0)
# User-Agent header is required.

ENDPOINT="https://mcp.php-entwickler.de"

# 1) Server info (GET → simple JSON)
echo "→ Server info"
curl -sS -X GET "$ENDPOINT" -H "User-Agent: example/1.0" | jq .

# 2) MCP initialize handshake
echo
echo "→ initialize"
curl -sS -X POST "$ENDPOINT" \
  -H "Content-Type: application/json" \
  -H "User-Agent: example/1.0" \
  -d '{
    "jsonrpc": "2.0",
    "id": 1,
    "method": "initialize",
    "params": {"protocolVersion": "2025-06-18", "capabilities": {}}
  }' | jq .

# 3) List available tools
echo
echo "→ tools/list"
curl -sS -X POST "$ENDPOINT" \
  -H "Content-Type: application/json" \
  -H "User-Agent: example/1.0" \
  -d '{"jsonrpc":"2.0","id":2,"method":"tools/list"}' | jq .

# 4) Search Senior Laravel jobs in Berlin
echo
echo "→ tools/call search_jobs (Senior Laravel Berlin)"
curl -sS -X POST "$ENDPOINT" \
  -H "Content-Type: application/json" \
  -H "User-Agent: example/1.0" \
  -d '{
    "jsonrpc": "2.0",
    "id": 3,
    "method": "tools/call",
    "params": {
      "name": "search_jobs",
      "arguments": {
        "skill": "Laravel",
        "city": "Berlin",
        "experience": "senior",
        "limit": 5
      }
    }
  }' | jq .

# 5) Get full job detail by ID
echo
echo "→ tools/call get_job"
curl -sS -X POST "$ENDPOINT" \
  -H "Content-Type: application/json" \
  -H "User-Agent: example/1.0" \
  -d '{
    "jsonrpc": "2.0",
    "id": 4,
    "method": "tools/call",
    "params": {
      "name": "get_job",
      "arguments": {"id_or_slug": "107175"}
    }
  }' | jq .

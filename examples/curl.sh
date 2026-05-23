#!/usr/bin/env bash
# php-entwickler.de MCP Server — direct HTTP examples (Streamable HTTP / JSON-RPC 2.0)

ENDPOINT="https://mcp.php-entwickler.de"
UA="example/1.0"

# 1) Server info (GET → simple JSON)
echo "→ Server info"
curl -sS -X GET "$ENDPOINT" -H "User-Agent: $UA" | jq .

# 2) MCP initialize handshake — returns serverInfo + instructions (incl. license)
echo
echo "→ initialize"
curl -sS -X POST "$ENDPOINT" \
  -H "Content-Type: application/json" \
  -H "User-Agent: $UA" \
  -d '{
    "jsonrpc": "2.0",
    "id": 1,
    "method": "initialize",
    "params": {"protocolVersion": "2025-06-18", "capabilities": {}}
  }' | jq .

# 3) List available tools (5 tools)
echo
echo "→ tools/list"
curl -sS -X POST "$ENDPOINT" \
  -H "Content-Type: application/json" \
  -H "User-Agent: $UA" \
  -d '{"jsonrpc":"2.0","id":2,"method":"tools/list"}' | jq '.result.tools[] | {name, description: (.description[:120] + "...")}'

# 4) search_jobs — Senior Laravel jobs in Berlin
echo
echo "→ tools/call search_jobs (Senior Laravel Berlin)"
curl -sS -X POST "$ENDPOINT" \
  -H "Content-Type: application/json" \
  -H "User-Agent: $UA" \
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

# 5) search_jobs — auto radius expansion for small towns (Coburg)
# Response includes radius_km + radius_was_expanded + search_scope
echo
echo "→ tools/call search_jobs (Coburg with auto-radius)"
curl -sS -X POST "$ENDPOINT" \
  -H "Content-Type: application/json" \
  -H "User-Agent: $UA" \
  -d '{
    "jsonrpc": "2.0",
    "id": 4,
    "method": "tools/call",
    "params": {
      "name": "search_jobs",
      "arguments": {"city": "Coburg", "limit": 5}
    }
  }' | jq .

# 6) get_job — full details for one specific job
echo
echo "→ tools/call get_job"
curl -sS -X POST "$ENDPOINT" \
  -H "Content-Type: application/json" \
  -H "User-Agent: $UA" \
  -d '{
    "jsonrpc": "2.0",
    "id": 5,
    "method": "tools/call",
    "params": {
      "name": "get_job",
      "arguments": {"id_or_slug": "28723"}
    }
  }' | jq .

# 7) match_profile — CV-based matching
# Pass skills (from parsed resume), experience_years, optional location & remote.
# Response: jobs sorted by match_score (0-100) with matched_skills + missing_skills.
echo
echo "→ tools/call match_profile (CV-based matching)"
curl -sS -X POST "$ENDPOINT" \
  -H "Content-Type: application/json" \
  -H "User-Agent: $UA" \
  -d '{
    "jsonrpc": "2.0",
    "id": 6,
    "method": "tools/call",
    "params": {
      "name": "match_profile",
      "arguments": {
        "skills": ["PHP", "Symfony", "Doctrine", "Docker", "AWS", "PostgreSQL"],
        "experience_years": 6,
        "city": "Muenchen",
        "remote": "hybrid",
        "salary_min": 70000,
        "limit": 5
      }
    }
  }' | jq .

# 8) similar_jobs — find jobs similar to a reference (MoreLikeThis)
echo
echo "→ tools/call similar_jobs"
curl -sS -X POST "$ENDPOINT" \
  -H "Content-Type: application/json" \
  -H "User-Agent: $UA" \
  -d '{
    "jsonrpc": "2.0",
    "id": 7,
    "method": "tools/call",
    "params": {
      "name": "similar_jobs",
      "arguments": {"job_id": "28723", "limit": 5}
    }
  }' | jq .

# 9) market_insights — Laravel market overview (Germany-wide)
echo
echo "→ tools/call market_insights (Laravel)"
curl -sS -X POST "$ENDPOINT" \
  -H "Content-Type: application/json" \
  -H "User-Agent: $UA" \
  -d '{
    "jsonrpc": "2.0",
    "id": 8,
    "method": "tools/call",
    "params": {
      "name": "market_insights",
      "arguments": {"skill": "Laravel"}
    }
  }' | jq .

# 10) market_insights — Symfony market in Munich specifically
echo
echo "→ tools/call market_insights (Symfony, Muenchen)"
curl -sS -X POST "$ENDPOINT" \
  -H "Content-Type: application/json" \
  -H "User-Agent: $UA" \
  -d '{
    "jsonrpc": "2.0",
    "id": 9,
    "method": "tools/call",
    "params": {
      "name": "market_insights",
      "arguments": {"skill": "Symfony", "city": "Muenchen"}
    }
  }' | jq .

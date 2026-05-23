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


def demo_search_jobs() -> None:
    print("\n=== search_jobs: Senior Laravel Berlin (top 5) ===")
    jobs = tool_call(
        "search_jobs",
        {"skill": "Laravel", "city": "Berlin", "experience": "senior", "limit": 5},
    )
    print(f"  total={jobs['total']} returned={jobs['returned']} scope={jobs.get('search_scope')}")
    for job in jobs["jobs"]:
        company = job.get("company") or "[Premium-Profil erforderlich]"
        salary = job.get("salary") or "—"
        print(f"  · {job['title']} @ {company}")
        print(f"    {job['experience_label']} · {job['remote_label']} · {salary}")
        print(f"    {job['url']}")


def demo_auto_radius() -> None:
    print("\n=== search_jobs: Coburg with auto-radius expansion ===")
    jobs = tool_call("search_jobs", {"city": "Coburg", "limit": 5})
    print(
        f"  radius_km={jobs['radius_km']} "
        f"radius_was_expanded={jobs['radius_was_expanded']} "
        f"scope={jobs['search_scope']}"
    )
    print(f"  total={jobs['total']}")


def demo_match_profile() -> None:
    print("\n=== match_profile: CV-based matching ===")
    matches = tool_call(
        "match_profile",
        {
            "skills": ["PHP", "Symfony", "Doctrine", "Docker", "AWS", "PostgreSQL"],
            "experience_years": 6,
            "city": "Muenchen",
            "remote": "hybrid",
            "salary_min": 70000,
            "limit": 3,
        },
    )
    print(f"  Profile: {matches['profile']}")
    print(f"  Found {matches['returned']} of {matches['total']} potential matches:\n")
    for job in matches["jobs"]:
        print(f"  · [{job['match_score']}/100] {job['title']}")
        print(f"    Matched:  {', '.join(job['matched_skills']) or '—'}")
        print(f"    Missing:  {', '.join(job['missing_skills']) or '—'}")
        print(f"    {job['url']}")


def demo_similar_jobs(job_id: str = "28723") -> None:
    print(f"\n=== similar_jobs: jobs similar to ID {job_id} ===")
    result = tool_call("similar_jobs", {"job_id": job_id, "limit": 5})
    if result.get("error"):
        print(f"  Error: {result['reason']}")
        return
    ref = result["reference"]
    print(f"  Reference: {ref['title']}")
    print(f"  Skills:    {', '.join(ref['skills'][:5])}")
    print(f"\n  Found {result['returned']} similar jobs:")
    for job in result["jobs"]:
        company = job.get("company") or "[Premium]"
        print(f"  · {job['title']} @ {company} — {job['url']}")


def demo_market_insights(skill: str = "Laravel") -> None:
    print(f"\n=== market_insights: {skill} market overview ===")
    market = tool_call("market_insights", {"skill": skill})
    print(f"  Total active jobs: {market['total_active_jobs']}")

    print("\n  Salary by experience level:")
    for level in market["salary_by_experience"]:
        avg_min = level.get("avg_min_salary") or 0
        avg_max = level.get("avg_max_salary") or 0
        salary = f"{avg_min:,} – {avg_max:,} €" if avg_min and avg_max else "—"
        print(f"    {level['level']:<8}: {level['job_count']:>4} Jobs · {salary}")

    print("\n  Top 5 cities:")
    for city in market["top_cities"][:5]:
        avg = city.get("avg_max_salary")
        salary = f"{avg:,} €" if avg else "—"
        print(f"    {city['city']:<20}: {city['job_count']:>4} Jobs · avg max {salary}")

    print("\n  Remote distribution:")
    for r in market["remote_distribution"]:
        print(f"    {r['type']:<8}: {r['job_count']} Jobs")

    if market["top_premium_companies"]:
        print("\n  Top premium employers (claimed/verified):")
        for c in market["top_premium_companies"]:
            print(f"    · {c['name']} ({c['job_count']} Jobs)")
    print(f"\n  + {market['additional_anonymous_companies']} additional companies not yet on premium profile")


def main() -> None:
    # Handshake — returns serverInfo + instructions (incl. license clause)
    info = call("initialize", {"protocolVersion": "2025-06-18", "capabilities": {}})
    print("Server:", info["serverInfo"])

    # List tools
    tools = call("tools/list")["tools"]
    print(f"\n{len(tools)} tools available:")
    for t in tools:
        first_line = t["description"].split(".")[0]
        print(f"  - {t['name']:<18} {first_line[:80]}...")

    # Run demos
    demo_search_jobs()
    demo_auto_radius()
    demo_match_profile()
    demo_similar_jobs()
    demo_market_insights("Laravel")


if __name__ == "__main__":
    main()

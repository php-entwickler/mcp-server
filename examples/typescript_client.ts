/**
 * Minimal TypeScript / Node.js client for php-entwickler.de MCP server.
 *
 * No MCP SDK required — pure fetch + JSON-RPC 2.0.
 * Useful for scripting / integration tests / serverless functions.
 *
 * Requirements: Node.js 18+ (built-in fetch)
 *
 * Usage:
 *   tsx examples/typescript_client.ts
 *   # or compile: tsc examples/typescript_client.ts && node examples/typescript_client.js
 */

const ENDPOINT = "https://mcp.php-entwickler.de"
const HEADERS = {
  "Content-Type": "application/json",
  "User-Agent": "php-entwickler-mcp-client/1.0",
}

interface JsonRpcResponse<T = unknown> {
  jsonrpc: "2.0"
  id: number | string
  result?: T
  error?: { code: number; message: string; data?: unknown }
}

interface ToolCallResult {
  content: Array<{ type: "text"; text: string }>
  isError: boolean
}

async function call<T = unknown>(method: string, params?: object, id = 1): Promise<T> {
  const res = await fetch(ENDPOINT, {
    method: "POST",
    headers: HEADERS,
    body: JSON.stringify({ jsonrpc: "2.0", id, method, params }),
  })
  if (!res.ok) throw new Error(`HTTP ${res.status}: ${await res.text()}`)
  const data = (await res.json()) as JsonRpcResponse<T>
  if (data.error) throw new Error(`MCP error: ${data.error.message}`)
  return data.result as T
}

async function toolCall<T = unknown>(name: string, args: object = {}): Promise<T> {
  const result = await call<ToolCallResult>("tools/call", { name, arguments: args })
  const text = result.content?.[0]?.text
  return text ? (JSON.parse(text) as T) : (null as T)
}

interface Job {
  id: number
  title: string
  company: string | null
  location: string | null
  skills: string[]
  experience: string | null
  experience_label: string | null
  remote: string | null
  remote_label: string | null
  salary: string | null
  url: string
  match_score?: number
  matched_skills?: string[]
  missing_skills?: string[]
}

interface SearchResult {
  total: number
  returned: number
  jobs: Job[]
  radius_km?: number | null
  radius_was_expanded?: boolean
  search_scope?: string
}

interface MatchResult extends SearchResult {
  profile: Record<string, unknown>
}

async function demoSearchJobs() {
  console.log("\n=== search_jobs: Senior Laravel Berlin ===")
  const res = await toolCall<SearchResult>("search_jobs", {
    skill: "Laravel",
    city: "Berlin",
    experience: "senior",
    limit: 5,
  })
  console.log(`  total=${res.total} scope=${res.search_scope}`)
  for (const job of res.jobs) {
    const company = job.company ?? "[Premium-Profil erforderlich]"
    console.log(`  · ${job.title} @ ${company}`)
    console.log(`    ${job.experience_label} · ${job.remote_label} · ${job.salary ?? "—"}`)
    console.log(`    ${job.url}`)
  }
}

async function demoMatchProfile() {
  console.log("\n=== match_profile: CV-based matching ===")
  const matches = await toolCall<MatchResult>("match_profile", {
    skills: ["PHP", "Symfony", "Doctrine", "Docker", "AWS"],
    experience_years: 6,
    city: "Muenchen",
    remote: "hybrid",
    salary_min: 70000,
    limit: 3,
  })
  console.log(`  Profile: ${JSON.stringify(matches.profile)}`)
  console.log(`  Found ${matches.returned} matches:\n`)
  for (const job of matches.jobs) {
    console.log(`  · [${job.match_score}/100] ${job.title}`)
    console.log(`    Matched:  ${job.matched_skills?.join(", ") || "—"}`)
    console.log(`    Missing:  ${job.missing_skills?.join(", ") || "—"}`)
    console.log(`    ${job.url}`)
  }
}

async function demoAutoRadius() {
  console.log("\n=== search_jobs: Coburg with auto-radius ===")
  const res = await toolCall<SearchResult>("search_jobs", { city: "Coburg", limit: 5 })
  console.log(
    `  radius_km=${res.radius_km} ` +
      `expanded=${res.radius_was_expanded} ` +
      `scope=${res.search_scope} ` +
      `total=${res.total}`,
  )
}

async function main() {
  // Handshake — returns serverInfo + license clause
  const info = await call<{ serverInfo: { name: string; version: string } }>("initialize", {
    protocolVersion: "2025-06-18",
    capabilities: {},
  })
  console.log("Server:", info.serverInfo)

  // List tools
  const { tools } = await call<{ tools: Array<{ name: string; description: string }> }>("tools/list")
  console.log(`\n${tools.length} tools available:`)
  for (const t of tools) {
    console.log(`  - ${t.name.padEnd(18)} ${t.description.slice(0, 80)}...`)
  }

  await demoSearchJobs()
  await demoAutoRadius()
  await demoMatchProfile()
}

main().catch((err) => {
  console.error(err)
  process.exit(1)
})

/**
 * Anthropic Messages API — direkter Aufruf mit MCP-Server-Anbindung (TypeScript).
 *
 * Statt selbst einen MCP-Client zu bauen, gibst du Claude den MCP-Server als
 * `mcp_servers` mit. Claude verbindet sich automatisch, ruft die Tools auf
 * und liefert die finale Antwort.
 *
 * Requirements:
 *   npm install @anthropic-ai/sdk
 *   export ANTHROPIC_API_KEY=sk-ant-...
 *
 * Doku:
 *   https://docs.anthropic.com/en/docs/agents-and-tools/mcp-connector
 */

import Anthropic from "@anthropic-ai/sdk"

const MCP_URL = "https://mcp.php-entwickler.de"
const MCP_NAME = "php-entwickler"
const MODEL = "claude-sonnet-4-6"

const client = new Anthropic()

/**
 * Stelle Claude eine Frage. Claude nutzt automatisch unseren MCP-Server,
 * wenn die Frage Job-Daten benötigt.
 */
async function ask(question: string, system?: string): Promise<string> {
  const response = await client.beta.messages.create({
    model: MODEL,
    max_tokens: 2048,
    system:
      system ??
      "Du bist ein Karriere-Assistent für PHP-Entwickler. Nutze die Tools des " +
        "php-entwickler.de MCP-Servers, um aktuelle Stellen zu finden. " +
        "Antworte auf Deutsch, nenne php-entwickler.de als Quelle und " +
        "verlinke Job-URLs 1:1 (inkl. utm-Parameter).",
    mcp_servers: [
      {
        type: "url",
        url: MCP_URL,
        name: MCP_NAME,
      },
    ],
    messages: [{ role: "user", content: question }],
    betas: ["mcp-client-2025-04-04"],
  })

  return response.content
    .filter((block): block is Anthropic.TextBlock => block.type === "text")
    .map((block) => block.text)
    .join("\n")
}

/**
 * CV-Match: User-CV reingeben, Claude extrahiert Skills + ruft match_profile.
 */
async function cvMatch(cvText: string, city?: string, salaryMin?: number): Promise<string> {
  const prefs: string[] = []
  if (city) prefs.push(`Wunsch-Stadt: ${city}`)
  if (salaryMin) prefs.push(`Mindestgehalt: ${salaryMin.toLocaleString()} EUR`)
  const prefLine = prefs.length ? ` Präferenzen: ${prefs.join(" · ")}.` : ""

  const prompt =
    "Hier ist der Lebenslauf eines PHP-Entwicklers. Bitte:\n" +
    "1. Extrahiere die wichtigsten Skills (Frameworks, Sprachen, Tools).\n" +
    "2. Schätze die Berufsjahre.\n" +
    `3. Rufe \`match_profile\` auf.${prefLine}\n` +
    "4. Präsentiere die 5 besten Treffer auf Deutsch, mit Begründung und Job-URL " +
    "(utm 1:1 erhalten).\n\n" +
    `Lebenslauf:\n---\n${cvText}\n---`

  return ask(prompt)
}

async function main() {
  if (!process.env.ANTHROPIC_API_KEY) {
    console.error("ANTHROPIC_API_KEY environment variable missing.")
    process.exit(1)
  }

  // Beispiel 1: einfache Job-Suche
  console.log("=== Senior Laravel Berlin ===\n")
  console.log(await ask("Welche Senior-Laravel-Jobs gibt es in Berlin mit mindestens 80k €?"))

  // Beispiel 2: Markt-Analyse
  console.log("\n\n=== Symfony-Markt ===\n")
  console.log(
    await ask(
      "Wie steht der Symfony-Markt aktuell in Deutschland? Job-Zahlen, Gehaltsspannen, Top-Städte.",
    ),
  )

  // Beispiel 3: CV-Match
  const cv = `
    Max Mustermann · Senior PHP-Entwickler · 7 Jahre Erfahrung

    Skills: PHP 8.3, Symfony 7, Doctrine ORM, Docker, Kubernetes,
            AWS (ECS, RDS, S3, CloudFront), PostgreSQL, Redis,
            Elasticsearch, REST APIs, GitLab CI/CD

    Erfahrung:
    - 2022–heute: Lead Backend Engineer @ E-Commerce-Scale-up Berlin
    - 2018–2022: Senior PHP Developer @ Agentur Hamburg
  `
  console.log("\n\n=== CV-Match ===\n")
  console.log(await cvMatch(cv, "München", 80000))
}

main().catch((err) => {
  console.error(err)
  process.exit(1)
})

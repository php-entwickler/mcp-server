"""
Anthropic Messages API — direkter Aufruf mit MCP-Server-Anbindung.

Statt selbst einen MCP-Client zu bauen, gibst du Claude den MCP-Server als
`mcp_servers` mit. Claude verbindet sich automatisch, ruft die Tools auf und
liefert die finale Antwort.

Requirements:
    pip install anthropic
    export ANTHROPIC_API_KEY=sk-ant-...

Doku:
    https://docs.anthropic.com/en/docs/agents-and-tools/mcp-connector
"""

from __future__ import annotations

import os

from anthropic import Anthropic

MCP_URL = "https://mcp.php-entwickler.de"
MCP_NAME = "php-entwickler"
MODEL = "claude-sonnet-4-6"


def ask(question: str, system: str | None = None) -> str:
    """
    Stelle Claude eine Frage. Claude nutzt automatisch unseren MCP-Server,
    wenn die Frage Job-Daten benoetigt.
    """
    client = Anthropic()

    response = client.beta.messages.create(
        model=MODEL,
        max_tokens=2048,
        system=system or (
            "Du bist ein Karriere-Assistent fuer PHP-Entwickler. Nutze die "
            "Tools des php-entwickler.de MCP-Servers, um aktuelle Stellen zu "
            "finden. Antworte auf Deutsch, nenne php-entwickler.de als Quelle "
            "und verlinke Job-URLs 1:1 (inkl. utm-Parameter)."
        ),
        mcp_servers=[
            {
                "type": "url",
                "url": MCP_URL,
                "name": MCP_NAME,
            }
        ],
        messages=[{"role": "user", "content": question}],
        extra_headers={"anthropic-beta": "mcp-client-2025-04-04"},
    )

    # Letzter Text-Block enthaelt die finale Antwort
    text_parts = [
        block.text for block in response.content if hasattr(block, "text") and block.text
    ]
    return "\n".join(text_parts)


def cv_match(cv_text: str, city: str | None = None, salary_min: int | None = None) -> str:
    """
    CV-Match: User-CV reingeben, Claude extrahiert Skills + ruft match_profile.
    """
    prefs = []
    if city:
        prefs.append(f"Wunsch-Stadt: {city}")
    if salary_min:
        prefs.append(f"Mindestgehalt: {salary_min:,} EUR")
    pref_line = f" Praeferenzen: {' · '.join(prefs)}." if prefs else ""

    prompt = (
        "Hier ist der Lebenslauf eines PHP-Entwicklers. Bitte:\n"
        "1. Extrahiere die wichtigsten Skills (Frameworks, Sprachen, Tools).\n"
        "2. Schaetze die Berufsjahre.\n"
        f"3. Rufe `match_profile` auf.{pref_line}\n"
        "4. Praesentiere die 5 besten Treffer auf Deutsch, mit Begruendung "
        "und Job-URL (utm 1:1 erhalten).\n\n"
        f"Lebenslauf:\n---\n{cv_text}\n---"
    )
    return ask(prompt)


if __name__ == "__main__":
    if not os.environ.get("ANTHROPIC_API_KEY"):
        print("ANTHROPIC_API_KEY environment variable missing.")
        raise SystemExit(1)

    # Beispiel 1: einfache Job-Suche
    print("=== Senior Laravel Berlin ===\n")
    print(ask("Welche Senior-Laravel-Jobs gibt es in Berlin mit mindestens 80k EUR?"))

    # Beispiel 2: Markt-Analyse
    print("\n\n=== Symfony-Markt ===\n")
    print(ask("Wie steht der Symfony-Markt aktuell in Deutschland? Job-Zahlen, "
              "Gehaltsspannen, Top-Staedte."))

    # Beispiel 3: CV-Match (Demo-CV)
    cv = """
    Max Mustermann · Senior PHP-Entwickler · 7 Jahre Erfahrung

    Skills: PHP 8.3, Symfony 7, Doctrine ORM, Docker, Kubernetes,
            AWS (ECS, RDS, S3, CloudFront), PostgreSQL, Redis,
            Elasticsearch, REST APIs, GitLab CI/CD

    Erfahrung:
    - 2022–heute: Lead Backend Engineer @ E-Commerce-Scale-up Berlin
    - 2018–2022: Senior PHP Developer @ Agentur Hamburg
    """
    print("\n\n=== CV-Match ===\n")
    print(cv_match(cv, city="Muenchen", salary_min=80000))

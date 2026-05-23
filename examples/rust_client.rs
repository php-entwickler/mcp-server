// Minimal Rust client for php-entwickler.de MCP server.
//
// No MCP SDK required — pure reqwest + serde_json + JSON-RPC 2.0.
//
// Cargo.toml deps:
//   reqwest = { version = "0.12", features = ["json", "rustls-tls"], default-features = false }
//   tokio = { version = "1", features = ["macros", "rt-multi-thread"] }
//   serde = { version = "1", features = ["derive"] }
//   serde_json = "1"
//
// Usage:
//   cargo run --example rust_client

use serde::{Deserialize, Serialize};
use serde_json::{json, Value};

const ENDPOINT: &str = "https://mcp.php-entwickler.de";
const USER_AGENT: &str = "php-entwickler-mcp-client/1.0";

#[derive(Serialize)]
struct RpcRequest<'a> {
    jsonrpc: &'a str,
    id: u32,
    method: &'a str,
    #[serde(skip_serializing_if = "Option::is_none")]
    params: Option<Value>,
}

#[derive(Deserialize)]
struct RpcResponse {
    result: Option<Value>,
    error: Option<RpcError>,
}

#[derive(Deserialize)]
struct RpcError {
    message: String,
}

#[derive(Deserialize, Debug)]
struct Job {
    id: u64,
    title: String,
    company: Option<String>,
    experience_label: Option<String>,
    remote_label: Option<String>,
    salary: Option<String>,
    url: String,
    #[serde(default)]
    match_score: Option<u32>,
    #[serde(default)]
    matched_skills: Vec<String>,
    #[serde(default)]
    missing_skills: Vec<String>,
}

#[derive(Deserialize, Debug)]
struct SearchResult {
    total: u32,
    returned: u32,
    jobs: Vec<Job>,
    #[serde(default)]
    radius_km: Option<u32>,
    #[serde(default)]
    radius_was_expanded: bool,
    #[serde(default)]
    search_scope: Option<String>,
}

async fn call(
    client: &reqwest::Client,
    method: &str,
    params: Option<Value>,
) -> Result<Value, Box<dyn std::error::Error>> {
    let req = RpcRequest { jsonrpc: "2.0", id: 1, method, params };
    let res = client
        .post(ENDPOINT)
        .header("Content-Type", "application/json")
        .header("User-Agent", USER_AGENT)
        .json(&req)
        .send()
        .await?;

    if !res.status().is_success() {
        return Err(format!("HTTP {}: {}", res.status(), res.text().await?).into());
    }

    let resp: RpcResponse = res.json().await?;
    if let Some(err) = resp.error {
        return Err(format!("MCP error: {}", err.message).into());
    }
    Ok(resp.result.unwrap_or(Value::Null))
}

async fn tool_call<T: for<'de> Deserialize<'de>>(
    client: &reqwest::Client,
    name: &str,
    args: Value,
) -> Result<T, Box<dyn std::error::Error>> {
    let res = call(client, "tools/call", Some(json!({ "name": name, "arguments": args }))).await?;
    let text = res["content"][0]["text"]
        .as_str()
        .ok_or("missing content[0].text")?;
    Ok(serde_json::from_str(text)?)
}

async fn demo_search_jobs(client: &reqwest::Client) -> Result<(), Box<dyn std::error::Error>> {
    println!("\n=== search_jobs: Senior Laravel Berlin ===");
    let res: SearchResult = tool_call(
        client,
        "search_jobs",
        json!({ "skill": "Laravel", "city": "Berlin", "experience": "senior", "limit": 5 }),
    )
    .await?;
    println!("  total={} scope={}", res.total, res.search_scope.unwrap_or_default());
    for j in res.jobs {
        let company = j.company.unwrap_or_else(|| "[Premium-Profil erforderlich]".to_string());
        println!("  · {} @ {}", j.title, company);
        println!(
            "    {} · {} · {}",
            j.experience_label.unwrap_or_else(|| "—".to_string()),
            j.remote_label.unwrap_or_else(|| "—".to_string()),
            j.salary.unwrap_or_else(|| "—".to_string()),
        );
        println!("    {}", j.url);
    }
    Ok(())
}

async fn demo_match_profile(client: &reqwest::Client) -> Result<(), Box<dyn std::error::Error>> {
    println!("\n=== match_profile: CV-based matching ===");
    let res: SearchResult = tool_call(
        client,
        "match_profile",
        json!({
            "skills": ["PHP", "Symfony", "Doctrine", "Docker", "AWS"],
            "experience_years": 6,
            "city": "Muenchen",
            "remote": "hybrid",
            "salary_min": 70000,
            "limit": 3,
        }),
    )
    .await?;
    println!("  Found {} matches:\n", res.returned);
    for j in res.jobs {
        println!("  · [{}/100] {}", j.match_score.unwrap_or(0), j.title);
        println!("    Matched: {}", j.matched_skills.join(", "));
        println!("    Missing: {}", j.missing_skills.join(", "));
        println!("    {}", j.url);
    }
    Ok(())
}

async fn demo_auto_radius(client: &reqwest::Client) -> Result<(), Box<dyn std::error::Error>> {
    println!("\n=== search_jobs: Coburg with auto-radius ===");
    let res: SearchResult = tool_call(client, "search_jobs", json!({ "city": "Coburg", "limit": 5 })).await?;
    println!(
        "  radius_km={:?} expanded={} scope={} total={}",
        res.radius_km,
        res.radius_was_expanded,
        res.search_scope.unwrap_or_default(),
        res.total,
    );
    Ok(())
}

#[tokio::main]
async fn main() -> Result<(), Box<dyn std::error::Error>> {
    let client = reqwest::Client::new();

    // Handshake
    let info = call(
        &client,
        "initialize",
        Some(json!({ "protocolVersion": "2025-06-18", "capabilities": {} })),
    )
    .await?;
    println!("Server: {}", info["serverInfo"]);

    // List tools
    let tools = call(&client, "tools/list", None).await?;
    let tools_arr = tools["tools"].as_array().ok_or("invalid tools list")?;
    println!("\n{} tools available:", tools_arr.len());
    for t in tools_arr {
        let name = t["name"].as_str().unwrap_or("?");
        let desc = t["description"].as_str().unwrap_or("");
        let truncated = if desc.len() > 80 { &desc[..80] } else { desc };
        println!("  - {:<18} {}...", name, truncated);
    }

    demo_search_jobs(&client).await?;
    demo_auto_radius(&client).await?;
    demo_match_profile(&client).await?;

    Ok(())
}

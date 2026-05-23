// Minimal Go client for php-entwickler.de MCP server.
//
// No MCP SDK required — pure net/http + JSON-RPC 2.0.
// Useful for serverless functions / data pipelines / integration tests.
//
// Usage:
//   go run examples/go_client.go

package main

import (
	"bytes"
	"encoding/json"
	"fmt"
	"io"
	"log"
	"net/http"
	"strings"
	"time"
)

const (
	endpoint  = "https://mcp.php-entwickler.de"
	userAgent = "php-entwickler-mcp-client/1.0"
)

type rpcRequest struct {
	JSONRPC string `json:"jsonrpc"`
	ID      int    `json:"id"`
	Method  string `json:"method"`
	Params  any    `json:"params,omitempty"`
}

type rpcResponse struct {
	JSONRPC string          `json:"jsonrpc"`
	ID      int             `json:"id"`
	Result  json.RawMessage `json:"result,omitempty"`
	Error   *rpcError       `json:"error,omitempty"`
}

type rpcError struct {
	Code    int    `json:"code"`
	Message string `json:"message"`
}

type toolCallResult struct {
	Content []struct {
		Type string `json:"type"`
		Text string `json:"text"`
	} `json:"content"`
	IsError bool `json:"isError"`
}

type Job struct {
	ID              int      `json:"id"`
	Title           string   `json:"title"`
	Company         *string  `json:"company"`
	Location        *string  `json:"location"`
	Skills          []string `json:"skills"`
	ExperienceLabel *string  `json:"experience_label"`
	RemoteLabel     *string  `json:"remote_label"`
	Salary          *string  `json:"salary"`
	URL             string   `json:"url"`
	MatchScore      int      `json:"match_score,omitempty"`
	MatchedSkills   []string `json:"matched_skills,omitempty"`
	MissingSkills   []string `json:"missing_skills,omitempty"`
}

type SearchResult struct {
	Total            int    `json:"total"`
	Returned         int    `json:"returned"`
	Jobs             []Job  `json:"jobs"`
	RadiusKM         *int   `json:"radius_km"`
	RadiusWasExpand  bool   `json:"radius_was_expanded"`
	SearchScope      string `json:"search_scope"`
}

var client = &http.Client{Timeout: 30 * time.Second}

func call(method string, params any, id int, out any) error {
	body, _ := json.Marshal(rpcRequest{JSONRPC: "2.0", ID: id, Method: method, Params: params})
	req, _ := http.NewRequest("POST", endpoint, bytes.NewReader(body))
	req.Header.Set("Content-Type", "application/json")
	req.Header.Set("User-Agent", userAgent)

	res, err := client.Do(req)
	if err != nil {
		return fmt.Errorf("http: %w", err)
	}
	defer res.Body.Close()

	raw, _ := io.ReadAll(res.Body)
	if res.StatusCode != 200 {
		return fmt.Errorf("HTTP %d: %s", res.StatusCode, raw)
	}

	var resp rpcResponse
	if err := json.Unmarshal(raw, &resp); err != nil {
		return fmt.Errorf("decode: %w", err)
	}
	if resp.Error != nil {
		return fmt.Errorf("MCP error: %s", resp.Error.Message)
	}
	if out != nil {
		return json.Unmarshal(resp.Result, out)
	}
	return nil
}

func toolCall(name string, args map[string]any, out any) error {
	var result toolCallResult
	if err := call("tools/call", map[string]any{"name": name, "arguments": args}, 1, &result); err != nil {
		return err
	}
	if len(result.Content) == 0 {
		return nil
	}
	return json.Unmarshal([]byte(result.Content[0].Text), out)
}

func deref(s *string, fallback string) string {
	if s == nil {
		return fallback
	}
	return *s
}

func demoSearchJobs() {
	fmt.Println("\n=== search_jobs: Senior Laravel Berlin ===")
	var res SearchResult
	if err := toolCall("search_jobs", map[string]any{
		"skill":      "Laravel",
		"city":       "Berlin",
		"experience": "senior",
		"limit":      5,
	}, &res); err != nil {
		log.Fatal(err)
	}
	fmt.Printf("  total=%d scope=%s\n", res.Total, res.SearchScope)
	for _, j := range res.Jobs {
		fmt.Printf("  · %s @ %s\n", j.Title, deref(j.Company, "[Premium-Profil erforderlich]"))
		fmt.Printf("    %s · %s · %s\n", deref(j.ExperienceLabel, "—"), deref(j.RemoteLabel, "—"), deref(j.Salary, "—"))
		fmt.Printf("    %s\n", j.URL)
	}
}

func demoMatchProfile() {
	fmt.Println("\n=== match_profile: CV-based matching ===")
	var res SearchResult
	if err := toolCall("match_profile", map[string]any{
		"skills":           []string{"PHP", "Symfony", "Doctrine", "Docker", "AWS"},
		"experience_years": 6,
		"city":             "Muenchen",
		"remote":           "hybrid",
		"salary_min":       70000,
		"limit":            3,
	}, &res); err != nil {
		log.Fatal(err)
	}
	fmt.Printf("  Found %d matches:\n\n", res.Returned)
	for _, j := range res.Jobs {
		fmt.Printf("  · [%d/100] %s\n", j.MatchScore, j.Title)
		fmt.Printf("    Matched: %s\n", strings.Join(j.MatchedSkills, ", "))
		fmt.Printf("    Missing: %s\n", strings.Join(j.MissingSkills, ", "))
		fmt.Printf("    %s\n", j.URL)
	}
}

func demoAutoRadius() {
	fmt.Println("\n=== search_jobs: Coburg with auto-radius ===")
	var res SearchResult
	if err := toolCall("search_jobs", map[string]any{"city": "Coburg", "limit": 5}, &res); err != nil {
		log.Fatal(err)
	}
	radius := "nationwide"
	if res.RadiusKM != nil {
		radius = fmt.Sprintf("%d km", *res.RadiusKM)
	}
	fmt.Printf("  radius=%s expanded=%v scope=%s total=%d\n", radius, res.RadiusWasExpand, res.SearchScope, res.Total)
}

func main() {
	// Initialize handshake
	var info struct {
		ServerInfo struct {
			Name    string `json:"name"`
			Version string `json:"version"`
		} `json:"serverInfo"`
	}
	if err := call("initialize", map[string]any{
		"protocolVersion": "2025-06-18",
		"capabilities":    map[string]any{},
	}, 1, &info); err != nil {
		log.Fatal(err)
	}
	fmt.Printf("Server: %s v%s\n", info.ServerInfo.Name, info.ServerInfo.Version)

	// List tools
	var tools struct {
		Tools []struct {
			Name        string `json:"name"`
			Description string `json:"description"`
		} `json:"tools"`
	}
	if err := call("tools/list", nil, 1, &tools); err != nil {
		log.Fatal(err)
	}
	fmt.Printf("\n%d tools available:\n", len(tools.Tools))
	for _, t := range tools.Tools {
		desc := t.Description
		if len(desc) > 80 {
			desc = desc[:80] + "..."
		}
		fmt.Printf("  - %-18s %s\n", t.Name, desc)
	}

	demoSearchJobs()
	demoAutoRadius()
	demoMatchProfile()
}

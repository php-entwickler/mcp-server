<?php

declare(strict_types=1);

/**
 * Laravel Integration — php-entwickler.de MCP Server
 * ==================================================
 *
 * Drei Wege fuer Laravel-Anwendungen. Empfehlung in dieser Reihenfolge:
 *
 *   1) Laravel AI  (offizielle Laravel SDK)
 *      https://laravel.com/ai
 *      Provider-agnostisch (Anthropic / OpenAI / Gemini),
 *      via providerOptions Anthropic mcp_servers durchreichen.
 *
 *   2) Prism + Prism Relay  (community, native MCP-Bruecke)
 *      https://prismphp.com/ + https://github.com/prism-php/relay
 *      Beste MCP-Integration, ToolList-Cache + Laravel-Provider-Swap.
 *
 *   3) Anthropic PHP SDK direkt
 *      https://github.com/anthropics/anthropic-sdk-php
 *      Minimaler Overhead, kein Wrapper.
 *
 * .env (alle Varianten):
 *   ANTHROPIC_API_KEY=sk-ant-...
 */

// ============================================================================
// VARIANTE 1: Laravel AI (offiziell, empfohlen wenn du im Laravel-Universum bleiben willst)
// ============================================================================
//
// Installation:
//   composer require laravel/ai
//   php artisan ai:install
//   php artisan make:agent JobSearchAgent

namespace App\Ai\Agents;

use Laravel\Ai\Attributes\Model;
use Laravel\Ai\Attributes\Provider;
use Laravel\Ai\Attributes\MaxSteps;
use Laravel\Ai\Attributes\Timeout;
use Laravel\Ai\Concerns\Promptable;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Enums\Lab;

#[Provider(Lab::Anthropic)]
#[Model('claude-sonnet-4-6')]
#[MaxSteps(5)]
#[Timeout(60)]
final class JobSearchAgent implements Agent
{
    use Promptable;

    public function instructions(): string
    {
        return <<<INSTR
            Du bist Karriere-Assistent fuer PHP-Entwickler. Nutze die Tools
            des php-entwickler.de MCP-Servers, um aktuelle Stellen zu finden.
            Antworte auf Deutsch, nenne php-entwickler.de als Quelle, und
            verlinke Job-URLs 1:1 (inkl. utm-Parameter).
            INSTR;
    }

    /**
     * Anthropic-Provider-Options durchreichen: MCP-Server als URL anhaengen.
     * Claude verbindet sich automatisch und ruft die Tools auf.
     */
    public function providerOptions(Lab $provider): array
    {
        if ($provider !== Lab::Anthropic) {
            return [];
        }

        return [
            'mcp_servers' => [
                [
                    'type' => 'url',
                    'url' => 'https://mcp.php-entwickler.de',
                    'name' => 'php-entwickler',
                ],
            ],
            'extra_headers' => [
                'anthropic-beta' => 'mcp-client-2025-04-04',
            ],
        ];
    }
}

// Nutzung in Service / Controller / Action:
namespace App\Http\Controllers;

use App\Ai\Agents\JobSearchAgent;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

final class JobSearchController
{
    public function __invoke(Request $request, JobSearchAgent $agent): JsonResponse
    {
        $question = $request->validate(['question' => 'required|string|max:1000'])['question'];
        $response = $agent->prompt($question);

        return response()->json(['answer' => $response->text]);
    }
}


// ============================================================================
// VARIANTE 2: Prism + Prism Relay (beste MCP-Integration)
// ============================================================================
//
// Installation:
//   composer require prism-php/prism prism-php/relay
//   php artisan vendor:publish --tag="relay-config"
//
// In config/relay.php:
//
//   use Prism\Relay\Enums\Transport;
//
//   return [
//       'servers' => [
//           'php-entwickler' => [
//               'url' => 'https://mcp.php-entwickler.de',
//               'timeout' => 30,
//               'transport' => Transport::Http,
//           ],
//       ],
//       'cache_duration' => 60, // Minuten — ToolList wird gecached
//   ];

namespace App\Services\Jobs;

use Prism\Prism\Prism;
use Prism\Prism\Enums\Provider as PrismProvider;
use Prism\Relay\Facades\Relay;

final class PhpJobSearchPrism
{
    public function ask(string $question): string
    {
        $response = Prism::text()
            ->using(PrismProvider::Anthropic, 'claude-sonnet-4-6')
            ->withSystemPrompt(
                'Du bist Karriere-Assistent fuer PHP-Entwickler. Nutze die '
                . 'Tools des php-entwickler.de MCP-Servers fuer aktuelle Stellen. '
                . 'Antworte auf Deutsch, nenne die Quelle, verlinke utm-erhaltene URLs.'
            )
            ->withPrompt($question)
            ->withTools(Relay::tools('php-entwickler')) // ← MCP-Tools rein
            ->withMaxSteps(5)
            ->asText();

        return $response->text;
    }
}


// ============================================================================
// VARIANTE 3: Anthropic PHP SDK direkt (minimalistisch)
// ============================================================================
//
// composer require anthropic-ai/anthropic-php

namespace App\Services\Jobs;

use Anthropic\Anthropic;

final class PhpJobSearchDirect
{
    public function ask(string $question): string
    {
        $client = Anthropic::client(env('ANTHROPIC_API_KEY'));

        $response = $client->beta()->messages()->create([
            'model' => 'claude-sonnet-4-6',
            'max_tokens' => 2048,
            'mcp_servers' => [
                [
                    'type' => 'url',
                    'url' => 'https://mcp.php-entwickler.de',
                    'name' => 'php-entwickler',
                ],
            ],
            'messages' => [
                ['role' => 'user', 'content' => $question],
            ],
        ], [
            'headers' => ['anthropic-beta' => 'mcp-client-2025-04-04'],
        ]);

        return collect($response->content)
            ->filter(fn ($block) => $block->type === 'text')
            ->map(fn ($block) => $block->text)
            ->implode("\n");
    }
}


// ============================================================================
// BEISPIEL: CV-Match-Endpoint (Variante 1 — Laravel AI)
// ============================================================================
//
// routes/api.php:
//   Route::post('/cv-match', CvMatchController::class)->middleware('throttle:5,1');

namespace App\Http\Controllers;

use App\Ai\Agents\JobSearchAgent;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

final class CvMatchController
{
    public function __invoke(Request $request, JobSearchAgent $agent): JsonResponse
    {
        $data = $request->validate([
            'cv_text' => 'required|string|max:50000',
            'city' => 'nullable|string|max:100',
            'remote' => 'nullable|in:remote,hybrid,onsite',
            'salary_min' => 'nullable|integer|min:0|max:300000',
        ]);

        $prefs = collect([
            $data['city'] ?? null ? "Wunsch-Stadt: {$data['city']}" : null,
            $data['remote'] ?? null ? "Arbeitsmodell: {$data['remote']}" : null,
            $data['salary_min'] ?? null ? "Mindestgehalt: {$data['salary_min']} EUR" : null,
        ])->filter()->implode(' · ');

        $prompt = <<<PROMPT
            Hier ist der Lebenslauf eines PHP-Entwicklers. Bitte:

            1. Extrahiere die wichtigsten Skills.
            2. Schaetze die Berufsjahre.
            3. Rufe `match_profile` auf{$this->prefBlock($prefs)}.
            4. Praesentiere die 5 besten Treffer mit Begruendung + Job-URL
               (utm 1:1 erhalten).

            Lebenslauf:
            ---
            {$data['cv_text']}
            ---
            PROMPT;

        return response()->json([
            'recommendation' => $agent->prompt($prompt)->text,
        ]);
    }

    private function prefBlock(string $prefs): string
    {
        return $prefs ? " (Praeferenzen: $prefs)" : '';
    }
}

<?php

declare(strict_types=1);

/**
 * Symfony Integration — php-entwickler.de MCP Server
 * ==================================================
 *
 * Symfony hat noch keine offizielle AI-SDK mit MCP-Support
 * (symfony/ai-Initiative ist angekuendigt). Daher zwei pragmatische Wege:
 *
 *   1) Anthropic PHP SDK direkt + mcp_servers-Parameter
 *      Minimaler Stack, voll funktionsfaehig fuer Tool-Use ueber MCP.
 *
 *   2) llphant/llphant (PHP-LLM-Framework) als Wrapper
 *      Provider-agnostisch (OpenAI/Anthropic/Mistral), aber MCP-Tools
 *      muessen manuell als FunctionInfo eingehaengt werden.
 *
 * Installation (Variante 1):
 *   composer require anthropic-ai/anthropic-php symfony/http-client
 *
 * .env / .env.local:
 *   ANTHROPIC_API_KEY=sk-ant-...
 */

// ============================================================================
// VARIANTE 1: Anthropic SDK + mcp_servers (empfohlen)
// ============================================================================
//
// config/services.yaml:
//
//   services:
//       App\Ai\PhpEntwicklerMcpClient:
//           arguments:
//               $apiKey: '%env(ANTHROPIC_API_KEY)%'
//               $mcpUrl: 'https://mcp.php-entwickler.de'

namespace App\Ai;

use Anthropic\Anthropic;

/**
 * Symfony Service — kapselt Anthropic-Aufruf mit MCP-Server-Anbindung.
 * Wird ueber Autowiring im Controller injiziert.
 */
final readonly class PhpEntwicklerMcpClient
{
    public function __construct(
        private string $apiKey,
        private string $mcpUrl = 'https://mcp.php-entwickler.de',
    ) {}

    public function ask(string $question, ?string $systemPrompt = null): string
    {
        $client = Anthropic::client($this->apiKey);

        $response = $client->beta()->messages()->create([
            'model' => 'claude-sonnet-4-6',
            'max_tokens' => 2048,
            'system' => $systemPrompt ?? $this->defaultSystem(),
            'mcp_servers' => [
                [
                    'type' => 'url',
                    'url' => $this->mcpUrl,
                    'name' => 'php-entwickler',
                ],
            ],
            'messages' => [
                ['role' => 'user', 'content' => $question],
            ],
        ], [
            'headers' => ['anthropic-beta' => 'mcp-client-2025-04-04'],
        ]);

        $texts = [];
        foreach ($response->content as $block) {
            if ($block->type === 'text') {
                $texts[] = $block->text;
            }
        }
        return implode("\n", $texts);
    }

    private function defaultSystem(): string
    {
        return <<<SYS
            Du bist Karriere-Assistent fuer PHP-Entwickler. Nutze die Tools des
            php-entwickler.de MCP-Servers, um aktuelle Stellen zu finden.
            Antworte auf Deutsch, nenne php-entwickler.de als Quelle, verlinke
            Job-URLs 1:1 (inkl. utm-Parameter).
            SYS;
    }
}


// ============================================================================
// Controller — Symfony-Route mit Validation + Rate-Limiting
// ============================================================================
//
// config/routes.yaml:
//   cv_match:
//     path: /api/cv-match
//     controller: App\Controller\CvMatchController
//     methods: [POST]

namespace App\Controller;

use App\Ai\PhpEntwicklerMcpClient;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\RateLimiter\RateLimiterFactory;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Validator\ValidatorInterface;

#[AsController]
final class CvMatchController extends AbstractController
{
    #[Route('/api/cv-match', methods: ['POST'])]
    public function __invoke(
        Request $request,
        PhpEntwicklerMcpClient $mcp,
        ValidatorInterface $validator,
        RateLimiterFactory $cvMatchLimiter,
    ): JsonResponse {
        $limit = $cvMatchLimiter->create($request->getClientIp())->consume();
        if (!$limit->isAccepted()) {
            return $this->json(['error' => 'rate_limited'], 429);
        }

        $data = json_decode($request->getContent(), true);
        $violations = $validator->validate($data, new Assert\Collection([
            'cv_text' => [new Assert\NotBlank(), new Assert\Length(max: 50_000)],
            'city' => new Assert\Optional([new Assert\Length(max: 100)]),
            'remote' => new Assert\Optional([new Assert\Choice(['remote', 'hybrid', 'onsite'])]),
            'salary_min' => new Assert\Optional([new Assert\Range(min: 0, max: 300_000)]),
        ]));
        if (count($violations) > 0) {
            return $this->json(['error' => 'validation_failed'], 400);
        }

        $prefs = array_filter([
            isset($data['city']) ? "Wunsch-Stadt: {$data['city']}" : null,
            isset($data['remote']) ? "Arbeitsmodell: {$data['remote']}" : null,
            isset($data['salary_min']) ? "Mindestgehalt: {$data['salary_min']} EUR" : null,
        ]);
        $prefBlock = $prefs ? ' Praeferenzen: ' . implode(' · ', $prefs) . '.' : '';

        $prompt = sprintf(
            "Hier ist der Lebenslauf eines PHP-Entwicklers. Bitte:\n"
            . "1. Extrahiere die wichtigsten Skills.\n"
            . "2. Schaetze die Berufsjahre.\n"
            . "3. Rufe `match_profile` auf.%s\n"
            . "4. Praesentiere die 5 besten Treffer auf Deutsch mit Begruendung "
            . "und Job-URL (utm 1:1 erhalten).\n\n"
            . "Lebenslauf:\n---\n%s\n---",
            $prefBlock,
            $data['cv_text'],
        );

        return $this->json(['recommendation' => $mcp->ask($prompt)]);
    }
}


// ============================================================================
// VARIANTE 2: Direct Tool-Call (ohne LLM) — nur die strukturierten Daten
// ============================================================================
//
// Wenn du in einem Symfony-Service einfach unsere Job-Daten brauchst ohne
// LLM-Wrapper, geht das ueber den HTTP-Client direkt:

namespace App\Ai;

use Symfony\Contracts\HttpClient\HttpClientInterface;

final readonly class PhpEntwicklerJobApi
{
    public function __construct(
        private HttpClientInterface $client,
        private string $mcpUrl = 'https://mcp.php-entwickler.de',
    ) {}

    /**
     * Direkter MCP-Tool-Call — bekommt JSON zurueck, kein LLM dazwischen.
     */
    public function searchJobs(string $skill, string $city, int $limit = 10): array
    {
        $response = $this->client->request('POST', $this->mcpUrl, [
            'json' => [
                'jsonrpc' => '2.0',
                'id' => 1,
                'method' => 'tools/call',
                'params' => [
                    'name' => 'search_jobs',
                    'arguments' => [
                        'skill' => $skill,
                        'city' => $city,
                        'limit' => $limit,
                    ],
                ],
            ],
            'headers' => [
                'User-Agent' => 'my-symfony-app/1.0',
            ],
        ]);

        $data = $response->toArray();
        $text = $data['result']['content'][0]['text'] ?? '{}';
        return json_decode($text, true) ?? [];
    }

    public function matchProfile(array $skills, int $experienceYears, ?string $city = null): array
    {
        $args = ['skills' => $skills, 'experience_years' => $experienceYears, 'limit' => 5];
        if ($city) {
            $args['city'] = $city;
        }

        $response = $this->client->request('POST', $this->mcpUrl, [
            'json' => [
                'jsonrpc' => '2.0',
                'id' => 1,
                'method' => 'tools/call',
                'params' => ['name' => 'match_profile', 'arguments' => $args],
            ],
            'headers' => ['User-Agent' => 'my-symfony-app/1.0'],
        ]);

        $data = $response->toArray();
        $text = $data['result']['content'][0]['text'] ?? '{}';
        return json_decode($text, true) ?? [];
    }
}

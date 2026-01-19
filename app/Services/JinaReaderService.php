<?php

declare(strict_types=1);

namespace App\Services;

use App\Contracts\ContentExtractorInterface;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class JinaReaderService implements ContentExtractorInterface
{
    protected string $baseUrl = 'https://r.jina.ai/';

    /**
     * Extract article content from a URL using Jina Reader API.
     *
     * @param  string  $url  The URL to extract content from
     * @return array{title: string|null, content: string|null, image: string|null, summary: string|null, source_name: string|null}
     *
     * @throws RuntimeException When extraction fails due to blocked domain or API error
     */
    public function extract(string $url): array
    {
        try {
            $headers = [
                'Accept' => 'application/json',
                'X-Return-Format' => 'markdown',
            ];

            // Add API key if configured (bypasses domain blocks)
            $apiKey = config('services.jina.api_key');
            if ($apiKey) {
                $headers['Authorization'] = 'Bearer '.$apiKey;
            }

            $response = Http::timeout(30)
                ->withHeaders($headers)
                ->get($this->baseUrl.$url);

            $data = $response->json();

            // Check for Jina-specific errors (domain blocks, abuse detection)
            if (isset($data['code']) && $data['code'] >= 400) {
                $errorMessage = $this->parseJinaError($data, $url);
                Log::warning('Jina Reader API error', [
                    'url' => $url,
                    'code' => $data['code'],
                    'message' => $data['message'] ?? 'Unknown error',
                ]);
                throw new RuntimeException($errorMessage);
            }

            if ($response->failed()) {
                Log::warning('Jina Reader API request failed', [
                    'url' => $url,
                    'status' => $response->status(),
                ]);
                throw new RuntimeException('Failed to fetch content from URL. Please try again later.');
            }

            if (! is_array($data) || empty($data['data'])) {
                throw new RuntimeException('No content could be extracted from this URL.');
            }

            $articleData = $data['data'];

            // Use description if available, otherwise generate from content
            $summary = $articleData['description'] ?? null;
            if (empty($summary)) {
                $summary = $this->generateSummary($articleData['content'] ?? '');
            } elseif (strlen($summary) > 280) {
                $summary = $this->generateSummary($summary);
            }

            return [
                'title' => $articleData['title'] ?? null,
                'content' => $articleData['content'] ?? null,
                'image' => $this->extractMainImage($articleData),
                'summary' => $summary,
                'source_name' => $this->extractSourceName($url),
            ];
        } catch (RuntimeException $e) {
            throw $e;
        } catch (\Exception $e) {
            Log::warning('Jina Reader extraction failed', [
                'url' => $url,
                'error' => $e->getMessage(),
            ]);

            throw new RuntimeException('Failed to extract content: '.$e->getMessage());
        }
    }

    protected function parseJinaError(array $data, string $url): string
    {
        $code = $data['code'] ?? 0;
        $name = $data['name'] ?? '';

        // Domain blocked due to abuse detection
        if ($code === 451 || $name === 'SecurityCompromiseError') {
            $host = parse_url($url, PHP_URL_HOST);

            return "This domain ({$host}) is temporarily blocked by the content service. Try again later or use a different source.";
        }

        // Rate limiting
        if ($code === 429) {
            return 'Too many requests. Please wait a moment and try again.';
        }

        // Generic error with readable message if available
        if (! empty($data['readableMessage'])) {
            return 'Content extraction failed: '.str($data['readableMessage'])->limit(100);
        }

        return $data['message'] ?? 'Failed to extract content from this URL.';
    }

    protected function extractMainImage(array $data): ?string
    {
        if (! empty($data['images']) && is_array($data['images'])) {
            return $data['images'][0] ?? null;
        }

        return null;
    }

    protected function generateSummary(string $content): ?string
    {
        if (empty($content)) {
            return null;
        }

        $plainText = strip_tags($content);
        $plainText = preg_replace('/\s+/', ' ', $plainText);
        $plainText = trim($plainText);

        if (strlen($plainText) <= 280) {
            return $plainText;
        }

        $truncated = substr($plainText, 0, 277);
        $lastSpace = strrpos($truncated, ' ');

        if ($lastSpace !== false) {
            $truncated = substr($truncated, 0, $lastSpace);
        }

        return $truncated.'...';
    }

    protected function extractSourceName(string $url): ?string
    {
        $parsed = parse_url($url);
        $host = $parsed['host'] ?? null;

        if (! $host) {
            return null;
        }

        $host = preg_replace('/^www\./', '', $host);

        $knownSources = [
            'ign.com' => 'IGN',
            'kotaku.com' => 'Kotaku',
            'polygon.com' => 'Polygon',
            'gamespot.com' => 'GameSpot',
            'eurogamer.net' => 'Eurogamer',
            'pcgamer.com' => 'PC Gamer',
            'rockpapershotgun.com' => 'Rock Paper Shotgun',
            'gamesradar.com' => 'GamesRadar+',
            'destructoid.com' => 'Destructoid',
            'theverge.com' => 'The Verge',
            'arstechnica.com' => 'Ars Technica',
            'vg247.com' => 'VG247',
            'gematsu.com' => 'Gematsu',
            'siliconera.com' => 'Siliconera',
            'dualshockers.com' => 'DualShockers',
            'pureplaystation.com' => 'Pure PlayStation',
            'playstation.com' => 'PlayStation Blog',
            'xbox.com' => 'Xbox Wire',
            'nintendo.com' => 'Nintendo',
        ];

        // Check exact match first
        if (isset($knownSources[$host])) {
            return $knownSources[$host];
        }

        // Check if host ends with any known source (handles subdomains like pt.ign.com)
        foreach ($knownSources as $domain => $name) {
            if (str_ends_with($host, '.'.$domain) || $host === $domain) {
                return $name;
            }
        }

        // Extract base domain name for unknown sources
        $parts = explode('.', $host);
        $baseName = count($parts) >= 2 ? $parts[count($parts) - 2] : $parts[0];

        return ucwords(str_replace(['-', '_'], ' ', $baseName));
    }

    /**
     * @return array{title: null, content: null, image: null, summary: null, source_name: null}
     */
    protected function emptyResult(): array
    {
        return [
            'title' => null,
            'content' => null,
            'image' => null,
            'summary' => null,
            'source_name' => null,
        ];
    }
}

<?php

declare(strict_types=1);

namespace App\Services;

use App\Contracts\NewsGenerationServiceInterface;
use App\Support\News\MarkdownToTiptapConverter;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class AnthropicNewsGenerationService implements NewsGenerationServiceInterface
{
    public function __construct(
        private readonly MarkdownToTiptapConverter $converter
    ) {}

    /**
     * {@inheritdoc}
     */
    public function summarizeAndLocalize(array $article): array
    {
        $response = Http::withHeaders([
            'x-api-key' => config('services.anthropic.api_key'),
            'anthropic-version' => config('services.anthropic.version'),
            'content-type' => 'application/json',
        ])->timeout(60)->post('https://api.anthropic.com/v1/messages', [
            'model' => config('services.anthropic.model'),
            'max_tokens' => 8192,
            'messages' => [
                ['role' => 'user', 'content' => $this->buildPrompt($article)],
            ],
        ]);

        if ($response->failed()) {
            Log::error('Anthropic API failed', ['status' => $response->status(), 'body' => $response->body()]);
            throw new RuntimeException('AI generation failed. Status: '.$response->status());
        }

        $stopReason = $response->json('stop_reason');
        $usage = $response->json('usage');
        $raw = $response->json('content.0.text', '');

        if ($stopReason === 'max_tokens') {
            Log::error('Anthropic response truncated (stop_reason=max_tokens)', ['usage' => $usage, 'raw' => $raw]);
            throw new RuntimeException('AI response truncated — raise max_tokens.');
        }

        $stripped = trim((string) $raw);
        if (str_starts_with($stripped, '```')) {
            $stripped = preg_replace('/^```(?:json)?\s*/i', '', $stripped);
            $stripped = preg_replace('/\s*```$/', '', $stripped);
        }

        $data = json_decode($stripped, true);

        if (! is_array($data) || ! isset($data['en'], $data['pt-PT'], $data['pt-BR'])) {
            Log::error('Anthropic unexpected response format', [
                'stop_reason' => $stopReason,
                'usage' => $usage,
                'raw' => $raw,
                'json_error' => json_last_error_msg(),
                'decoded_keys' => is_array($data) ? array_keys($data) : null,
            ]);
            throw new RuntimeException('AI returned unexpected response format.');
        }

        return [
            'en' => $this->processLocale($data['en']),
            'pt-PT' => $this->processLocale($data['pt-PT']),
            'pt-BR' => $this->processLocale($data['pt-BR']),
        ];
    }

    private function processLocale(array $locale): array
    {
        return [
            'title' => $locale['title'] ?? '',
            'summary_short' => $locale['summary_short'] ?? '',
            'summary_medium' => $locale['summary_medium'] ?? '',
            'body' => $this->converter->convert($locale['body_markdown'] ?? ''),
            'seo_title' => $locale['seo_title'] ?? '',
            'seo_description' => $locale['seo_description'] ?? '',
        ];
    }

    private function buildPrompt(array $article): string
    {
        $title = $article['title'];
        $content = $article['content'];
        $source = $article['source'];

        return <<<PROMPT
        You are a professional gaming news editor. Summarise and localise the following article into English (en), European Portuguese (pt-PT), and Brazilian Portuguese (pt-BR).

        Source: {$source}
        Title: {$title}

        Content:
        {$content}

        Return ONLY a valid JSON object with this exact structure (no markdown, no explanation):
        {
          "en": {
            "title": "polished title in English",
            "summary_short": "1-2 sentence summary in English (max 160 chars)",
            "summary_medium": "3-4 sentence summary in English (max 400 chars)",
            "body_markdown": "full article body in English in Markdown",
            "seo_title": "SEO-optimised title in English (max 70 chars)",
            "seo_description": "SEO meta description in English (max 160 chars)"
          },
          "pt-PT": {
            "title": "translated title in pt-PT",
            "summary_short": "1-2 sentence summary in pt-PT (max 160 chars)",
            "summary_medium": "3-4 sentence summary in pt-PT (max 400 chars)",
            "body_markdown": "full article body translated to pt-PT in Markdown",
            "seo_title": "SEO-optimised title in pt-PT (max 70 chars)",
            "seo_description": "SEO meta description in pt-PT (max 160 chars)"
          },
          "pt-BR": {
            "title": "translated title in pt-BR",
            "summary_short": "1-2 sentence summary in pt-BR (max 160 chars)",
            "summary_medium": "3-4 sentence summary in pt-BR (max 400 chars)",
            "body_markdown": "full article body translated to pt-BR in Markdown",
            "seo_title": "SEO-optimised title in pt-BR (max 70 chars)",
            "seo_description": "SEO meta description in pt-BR (max 160 chars)"
          }
        }
        PROMPT;
    }
}

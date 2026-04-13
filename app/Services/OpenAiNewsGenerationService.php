<?php

declare(strict_types=1);

namespace App\Services;

use App\Contracts\NewsGenerationServiceInterface;
use App\Support\News\MarkdownToTiptapConverter;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class OpenAiNewsGenerationService implements NewsGenerationServiceInterface
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
            'Authorization' => 'Bearer '.config('services.openai.api_key'),
            'Content-Type' => 'application/json',
        ])->timeout(60)->post('https://api.openai.com/v1/chat/completions', [
            'model' => config('services.openai.model'),
            'max_tokens' => 4096,
            'messages' => [
                ['role' => 'user', 'content' => $this->buildPrompt($article)],
            ],
        ]);

        if ($response->failed()) {
            Log::error('OpenAI API failed', ['status' => $response->status(), 'body' => $response->body()]);
            throw new RuntimeException('AI generation failed. Status: '.$response->status());
        }

        $raw = $response->json('choices.0.message.content', '');
        $data = json_decode($raw, true);

        if (! is_array($data) || ! isset($data['pt-PT'], $data['pt-BR'])) {
            Log::error('OpenAI unexpected response format', ['raw' => $raw]);
            throw new RuntimeException('AI returned unexpected response format.');
        }

        return [
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
        You are a professional gaming news editor. Translate and summarise the following article into both pt-PT (European Portuguese) and pt-BR (Brazilian Portuguese).

        Source: {$source}
        Title: {$title}

        Content:
        {$content}

        Return ONLY a valid JSON object with this exact structure (no markdown, no explanation):
        {
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

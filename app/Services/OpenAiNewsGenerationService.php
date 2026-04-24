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
            'max_completion_tokens' => 8192,
            'response_format' => ['type' => 'json_object'],
            'messages' => [
                ['role' => 'user', 'content' => $this->buildPrompt($article)],
            ],
        ]);

        if ($response->failed()) {
            Log::error('OpenAI API failed', ['status' => $response->status(), 'body' => $response->body()]);
            throw new RuntimeException('AI generation failed. Status: '.$response->status());
        }

        $finishReason = $response->json('choices.0.finish_reason');
        $usage = $response->json('usage');
        $raw = $response->json('choices.0.message.content', '');

        if ($finishReason === 'length') {
            Log::error('OpenAI response truncated (finish_reason=length)', ['usage' => $usage, 'raw' => $raw]);
            throw new RuntimeException('AI response truncated — raise max_completion_tokens.');
        }

        $stripped = trim((string) $raw);
        if (str_starts_with($stripped, '```')) {
            $stripped = preg_replace('/^```(?:json)?\s*/i', '', $stripped);
            $stripped = preg_replace('/\s*```$/', '', $stripped);
        }

        $data = json_decode($stripped, true);

        if (! is_array($data) || ! isset($data['en'], $data['pt-PT'], $data['pt-BR'])) {
            Log::error('OpenAI unexpected response format', [
                'finish_reason' => $finishReason,
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

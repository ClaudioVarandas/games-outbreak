<?php

namespace App\Providers;

use App\Contracts\ContentExtractorInterface;
use App\Contracts\NewsGenerationServiceInterface;
use App\Services\AnthropicNewsGenerationService;
use App\Services\Broadcasts\Channels\TelegramChannel;
use App\Services\Broadcasts\Channels\XChannel;
use App\Services\Broadcasts\WeeklyChoicesBroadcaster;
use App\Services\IgdbService;
use App\Services\JinaReaderService;
use App\Services\OpenAiNewsGenerationService;
use App\Support\News\MarkdownToTiptapConverter;
use Http;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(ContentExtractorInterface::class, JinaReaderService::class);

        $this->app->bind(NewsGenerationServiceInterface::class, function () {
            $converter = new MarkdownToTiptapConverter;

            return match (config('services.news_ai_provider')) {
                'openai' => new OpenAiNewsGenerationService($converter),
                default => new AnthropicNewsGenerationService($converter),
            };
        });

        $this->app->tag([TelegramChannel::class, XChannel::class], 'broadcasts.channels');

        $this->app->when(WeeklyChoicesBroadcaster::class)
            ->needs('$channels')
            ->giveTagged('broadcasts.channels');
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Http::macro('igdb', function () {
            // Add delay BEFORE each request (~3.5 req/sec → safe under 4/sec limit)
            // usleep(280000); // 280ms = 0.28 seconds
            usleep(config('services.igdb.rate_limit_delay_ms'));

            return Http::withHeaders([
                'Client-ID' => config('igdb.credentials.client_id'),
                'Authorization' => 'Bearer '.app(IgdbService::class)->getAccessToken(),
                'Accept' => 'application/json',
            ])
                ->acceptJson();
        });
    }
}

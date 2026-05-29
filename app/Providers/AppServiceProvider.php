<?php

declare(strict_types=1);

namespace App\Providers;

use App\Contracts\ContentExtractorInterface;
use App\Contracts\NewsGenerationServiceInterface;
use App\Services\AnthropicNewsGenerationService;
use App\Services\Broadcasts\Channels\MonthlyTelegramChannel;
use App\Services\Broadcasts\Channels\TelegramChannel;
use App\Services\Broadcasts\Channels\XChannel;
use App\Services\Broadcasts\MonthlyChoicesBroadcaster;
use App\Services\Broadcasts\WeeklyChoicesBroadcaster;
use App\Services\IgdbService;
use App\Services\JinaReaderService;
use App\Services\OpenAiNewsGenerationService;
use App\Support\Metrics\QueueEventLogger;
use App\Support\News\MarkdownToTiptapConverter;
use Http;
use Illuminate\Queue\Events\JobExceptionOccurred;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\ServiceProvider;
use Laravel\Pennant\Feature;

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

        $this->app->tag([MonthlyTelegramChannel::class], 'broadcasts.monthly_channels');

        $this->app->when(MonthlyChoicesBroadcaster::class)
            ->needs('$channels')
            ->giveTagged('broadcasts.monthly_channels');

        $this->app->singleton(QueueEventLogger::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Feature::define('game_user_actions', fn () => false);

        $queueLogger = $this->app->make(QueueEventLogger::class);
        Queue::before($queueLogger->processing(...));
        Queue::after($queueLogger->processed(...));
        Queue::failing($queueLogger->failed(...));
        Event::listen(JobExceptionOccurred::class, $queueLogger->exceptionOccurred(...));

        Http::macro('igdb', function () {
            // Add delay BEFORE each request (~3.5 req/sec → safe under 4/sec limit)
            // usleep(280000); // 280ms = 0.28 seconds
            usleep((int) config('services.igdb.rate_limit_delay_ms'));

            return Http::withHeaders([
                'Client-ID' => config('igdb.credentials.client_id'),
                'Authorization' => 'Bearer '.app(IgdbService::class)->getAccessToken(),
                'Accept' => 'application/json',
            ])
                ->acceptJson();
        });
    }
}

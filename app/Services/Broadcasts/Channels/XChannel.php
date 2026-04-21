<?php

declare(strict_types=1);

namespace App\Services\Broadcasts\Channels;

use App\Services\Broadcasts\Clients\XClient;
use App\Services\Broadcasts\Formatters\XTweetFormatter;
use App\Services\WeeklyChoicesPayload;
use RuntimeException;

class XChannel implements BroadcastChannel
{
    public function __construct(
        private readonly XClient $client,
        private readonly XTweetFormatter $formatter,
    ) {}

    public function name(): string
    {
        return 'x';
    }

    public function isEnabled(): bool
    {
        return (bool) config('services.x.enabled')
            && ! empty(config('services.x.api_key'))
            && ! empty(config('services.x.api_secret'))
            && ! empty(config('services.x.access_token'))
            && ! empty(config('services.x.access_token_secret'));
    }

    public function send(WeeklyChoicesPayload $payload): void
    {
        $credentials = [
            'api_key' => (string) config('services.x.api_key'),
            'api_secret' => (string) config('services.x.api_secret'),
            'access_token' => (string) config('services.x.access_token'),
            'access_token_secret' => (string) config('services.x.access_token_secret'),
        ];

        foreach ($credentials as $key => $value) {
            if ($value === '') {
                throw new RuntimeException("X credential missing: {$key}.");
            }
        }

        $this->client->postTweet($credentials, $this->formatter->format($payload));
    }

    public function preview(WeeklyChoicesPayload $payload): string
    {
        return $this->formatter->format($payload);
    }
}

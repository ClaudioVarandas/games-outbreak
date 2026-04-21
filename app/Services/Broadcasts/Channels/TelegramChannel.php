<?php

declare(strict_types=1);

namespace App\Services\Broadcasts\Channels;

use App\Services\Broadcasts\Clients\TelegramClient;
use App\Services\Broadcasts\Formatters\TelegramMessageFormatter;
use App\Services\WeeklyChoicesPayload;
use RuntimeException;

class TelegramChannel implements BroadcastChannel
{
    public function __construct(
        private readonly TelegramClient $client,
        private readonly TelegramMessageFormatter $formatter,
    ) {}

    public function name(): string
    {
        return 'telegram';
    }

    public function isEnabled(): bool
    {
        return (bool) config('services.telegram.enabled')
            && ! empty(config('services.telegram.bot_token'))
            && ! empty(config('services.telegram.chat_id'));
    }

    public function send(WeeklyChoicesPayload $payload): void
    {
        $botToken = (string) config('services.telegram.bot_token');
        $chatId = (string) config('services.telegram.chat_id');

        if ($botToken === '' || $chatId === '') {
            throw new RuntimeException('Telegram credentials missing (bot_token / chat_id).');
        }

        $this->client->sendMessage(
            botToken: $botToken,
            chatId: $chatId,
            text: $this->formatter->format($payload),
            parseMode: 'MarkdownV2',
            disableWebPagePreview: false,
        );
    }

    public function preview(WeeklyChoicesPayload $payload): string
    {
        return $this->formatter->format($payload);
    }
}

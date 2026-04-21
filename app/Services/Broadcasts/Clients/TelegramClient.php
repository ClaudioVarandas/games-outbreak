<?php

declare(strict_types=1);

namespace App\Services\Broadcasts\Clients;

use Illuminate\Support\Facades\Http;

class TelegramClient
{
    public function sendMessage(
        string $botToken,
        string $chatId,
        string $text,
        string $parseMode = 'MarkdownV2',
        bool $disableWebPagePreview = false,
    ): void {
        Http::asJson()
            ->throw()
            ->post("https://api.telegram.org/bot{$botToken}/sendMessage", [
                'chat_id' => $chatId,
                'text' => $text,
                'parse_mode' => $parseMode,
                'disable_web_page_preview' => $disableWebPagePreview,
            ]);
    }
}

<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Enums\PcPortSectionEnum;
use App\Models\PcPort;
use App\Services\IgdbService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Throwable;

class FetchPcPortCover implements ShouldQueue
{
    use Queueable;

    public const DIRECTORY = 'ports';

    public function __construct(public int $pcPortId) {}

    /**
     * Downloads the curated SteamGridDB cover, or falls back to a SteamGridDB title search.
     */
    public function handle(IgdbService $igdbService): void
    {
        $port = PcPort::find($this->pcPortId);

        if (! $port) {
            return;
        }

        $url = $port->cover_source_url ?: $this->search($port, $igdbService);

        if (! $url) {
            return;
        }

        $path = $this->download($port, $url);

        if (! $path) {
            return;
        }

        if ($port->cover_path && $port->cover_path !== $path && str_starts_with($port->cover_path, self::DIRECTORY.'/')) {
            Storage::disk('public')->delete($port->cover_path);
        }

        $port->update(['cover_path' => $path]);
    }

    /**
     * Multi-game tools have no single game to search for; they keep the typographic cover.
     */
    private function search(PcPort $port, IgdbService $igdbService): ?string
    {
        if ($port->section() === PcPortSectionEnum::Tools) {
            return null;
        }

        try {
            return $igdbService->findSteamGridDbImageUrl($port->game_title, 'cover');
        } catch (Throwable $e) {
            Log::warning("FetchPcPortCover: SteamGridDB search failed for {$port->slug}", ['error' => $e->getMessage()]);

            return null;
        }
    }

    private function download(PcPort $port, string $url): ?string
    {
        if (! PcPort::isSteamGridDbUrl($url)) {
            Log::warning("FetchPcPortCover: refusing non-SteamGridDB cover for {$port->slug}");

            return null;
        }

        try {
            $response = Http::timeout(15)->get($url);
        } catch (Throwable $e) {
            Log::warning("FetchPcPortCover: download failed for {$port->slug}", ['error' => $e->getMessage()]);

            return null;
        }

        if ($response->failed()) {
            Log::warning("FetchPcPortCover: download failed for {$port->slug}", ['status' => $response->status()]);

            return null;
        }

        $extension = strtolower(pathinfo((string) parse_url($url, PHP_URL_PATH), PATHINFO_EXTENSION));
        $extension = in_array($extension, ['jpg', 'jpeg', 'png', 'webp'], true) ? $extension : 'jpg';
        $path = self::DIRECTORY."/{$port->slug}.{$extension}";

        Storage::disk('public')->put($path, $response->body());

        return $path;
    }
}

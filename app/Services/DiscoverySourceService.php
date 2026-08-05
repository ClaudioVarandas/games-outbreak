<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\DiscoverySourceKindEnum;
use App\Enums\DiscoverySourceOriginEnum;
use App\Enums\DiscoverySourcePurposeEnum;
use App\Enums\DiscoverySourceStatusEnum;
use App\Models\DiscoverySource;

class DiscoverySourceService
{
    /**
     * Path segments on x.com/twitter.com that are site features, not profiles.
     */
    private const X_RESERVED_SEGMENTS = ['search', 'hashtag', 'explore', 'home', 'i', 'intent', 'share', 'settings'];

    private const CALENDAR_PATH_HINTS = ['release', 'calendar', 'upcoming', 'schedule'];

    public function __construct(private readonly YoutubeDataService $youtubeDataService) {}

    /**
     * Cheap URL-pattern classification for admin-pasted links. YouTube and X
     * profiles are unambiguous; calendar/press are guesses the agent (or the
     * admin) still has to confirm, so they stay flagged for enrichment.
     *
     * @return array{kind: DiscoverySourceKindEnum|null, locator: string|null, name: string|null, needs_enrichment: bool}
     */
    public function classifyUrl(string $url): array
    {
        $host = mb_strtolower((string) parse_url($url, PHP_URL_HOST));
        $host = preg_replace('/^www\./', '', $host) ?? $host;
        $path = trim((string) parse_url($url, PHP_URL_PATH), '/');

        if ($host === '') {
            return ['kind' => null, 'locator' => null, 'name' => null, 'needs_enrichment' => true];
        }

        if (in_array($host, ['youtube.com', 'm.youtube.com', 'youtu.be'], true)) {
            $handle = $this->youtubeDataService->extractChannelHandle($url);

            if ($handle !== null && str_contains($url, '@')) {
                return [
                    'kind' => DiscoverySourceKindEnum::Youtube,
                    'locator' => '@'.mb_strtolower($handle),
                    'name' => $handle,
                    'needs_enrichment' => false,
                ];
            }

            return [
                'kind' => DiscoverySourceKindEnum::Youtube,
                'locator' => $this->normalizeUrlLocator($url),
                'name' => null,
                'needs_enrichment' => true,
            ];
        }

        if (in_array($host, ['x.com', 'twitter.com', 'mobile.twitter.com'], true)) {
            $segment = explode('/', $path)[0] ?? '';

            if ($segment !== '' && ! in_array(mb_strtolower($segment), self::X_RESERVED_SEGMENTS, true)) {
                return [
                    'kind' => DiscoverySourceKindEnum::X,
                    'locator' => '@'.mb_strtolower($segment),
                    'name' => $segment,
                    'needs_enrichment' => false,
                ];
            }

            return ['kind' => DiscoverySourceKindEnum::X, 'locator' => null, 'name' => null, 'needs_enrichment' => true];
        }

        $pathAndQuery = mb_strtolower($path.'?'.(string) parse_url($url, PHP_URL_QUERY));

        foreach (self::CALENDAR_PATH_HINTS as $hint) {
            if ($path !== '' && str_contains($pathAndQuery, $hint)) {
                return [
                    'kind' => DiscoverySourceKindEnum::Calendar,
                    'locator' => $this->normalizeUrlLocator($url),
                    'name' => $this->nameFromDomain($host),
                    'needs_enrichment' => true,
                ];
            }
        }

        return [
            'kind' => DiscoverySourceKindEnum::Press,
            'locator' => $host,
            'name' => $this->nameFromDomain($host),
            'needs_enrichment' => true,
        ];
    }

    public function normalizeLocator(DiscoverySourceKindEnum $kind, string $raw): string
    {
        return match ($kind) {
            DiscoverySourceKindEnum::Youtube,
            DiscoverySourceKindEnum::X => '@'.mb_strtolower(ltrim(trim($raw), '@')),
            DiscoverySourceKindEnum::Press => $this->pressDomain($raw),
            DiscoverySourceKindEnum::Calendar => $this->normalizeUrlLocator($raw),
        };
    }

    /**
     * Create a pending source, deduplicating on the normalized locator.
     *
     * @param array{
     *     url?: string|null,
     *     kind?: DiscoverySourceKindEnum|string|null,
     *     purpose?: string|null,
     *     name?: string|null,
     *     confidence?: string|null,
     *     evidence?: string|null,
     *     origin?: DiscoverySourceOriginEnum,
     * } $attributes
     * @return array{status: 'created'|'duplicate'|'invalid', source: DiscoverySource|null, error?: string}
     */
    public function propose(array $attributes): array
    {
        $url = $attributes['url'] ?? null;
        $kind = $attributes['kind'] ?? null;

        if (is_string($kind)) {
            $kind = DiscoverySourceKindEnum::tryFrom($kind);
        }

        $needsEnrichment = false;
        $name = $attributes['name'] ?? null;

        if ($kind instanceof DiscoverySourceKindEnum) {
            $locator = $this->normalizeLocator($kind, $url ?? ($name ?? ''));
        } else {
            if (! $url) {
                return ['status' => 'invalid', 'source' => null, 'error' => 'Either kind or url is required.'];
            }

            $classified = $this->classifyUrl($url);
            $kind = $classified['kind'];
            $locator = $classified['locator'];
            $name ??= $classified['name'];
            $needsEnrichment = $classified['needs_enrichment'];
        }

        if (! $kind || ! $locator || trim($locator, '@ ') === '') {
            return ['status' => 'invalid', 'source' => null, 'error' => 'Could not derive a locator from the given url.'];
        }

        $existing = DiscoverySource::where('locator', $locator)->first();

        if ($existing) {
            return ['status' => 'duplicate', 'source' => $existing];
        }

        $source = DiscoverySource::create([
            'name' => $name ?: $locator,
            'kind' => $kind,
            'purpose' => $attributes['purpose'] ?? DiscoverySourcePurposeEnum::Both->value,
            'status' => DiscoverySourceStatusEnum::Pending,
            'origin' => $attributes['origin'] ?? DiscoverySourceOriginEnum::Agent,
            'url' => $url,
            'locator' => $locator,
            'confidence' => $attributes['confidence'] ?? null,
            'evidence' => $attributes['evidence'] ?? null,
            'needs_enrichment' => $needsEnrichment,
        ]);

        return ['status' => 'created', 'source' => $source];
    }

    /**
     * @param  list<array{id: int, items_found: int, fetch_ok: bool}>  $rows
     * @return int Number of sources updated.
     */
    public function applyRunReport(array $rows): int
    {
        $updated = 0;

        foreach ($rows as $row) {
            $source = DiscoverySource::find($row['id']);

            if (! $source) {
                continue;
            }

            $fetchOk = (bool) $row['fetch_ok'];

            $source->forceFill([
                'runs_count' => $source->runs_count + 1,
                'items_found_total' => $source->items_found_total + max(0, (int) $row['items_found']),
                'consecutive_failures' => $fetchOk ? 0 : $source->consecutive_failures + 1,
                'last_run_at' => now(),
                'last_success_at' => $fetchOk ? now() : $source->last_success_at,
            ])->save();

            $updated++;
        }

        return $updated;
    }

    /**
     * Attribution feedback from the staging review page: each id may appear
     * multiple times (once per reviewed game it contributed).
     *
     * @param  list<int>  $sourceIds
     */
    public function recordReviewOutcome(array $sourceIds, bool $promoted): void
    {
        $column = $promoted ? 'items_promoted' : 'items_rejected';

        foreach (array_count_values($sourceIds) as $sourceId => $count) {
            DiscoverySource::where('id', $sourceId)->increment($column, $count);
        }
    }

    private function normalizeUrlLocator(string $url): string
    {
        $host = mb_strtolower((string) parse_url($url, PHP_URL_HOST));
        $host = preg_replace('/^www\./', '', $host) ?? $host;
        $path = rtrim((string) parse_url($url, PHP_URL_PATH), '/');
        $query = (string) parse_url($url, PHP_URL_QUERY);

        return $host.$path.($query !== '' ? '?'.$query : '');
    }

    private function pressDomain(string $raw): string
    {
        $host = parse_url($raw, PHP_URL_HOST) ?: $raw;

        return preg_replace('/^www\./', '', mb_strtolower(trim((string) $host, '/ '))) ?? '';
    }

    private function nameFromDomain(string $host): string
    {
        return ucfirst(explode('.', $host)[0] ?? $host);
    }
}

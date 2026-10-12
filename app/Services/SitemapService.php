<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\ListTypeEnum;
use App\Enums\NewsLocaleEnum;
use App\Http\Middleware\EnsureNewsFeatureEnabled;
use App\Models\Game;
use App\Models\GameList;
use App\Models\NewsArticle;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Builds the public sitemap entries. Only URLs a guest can open (and that do not redirect) are listed.
 *
 * @phpstan-type SitemapEntry array{loc: string, lastmod?: string|null, alternates?: array<string, string>}
 */
class SitemapService
{
    public const GAMES_PER_SITEMAP = 5000;

    /**
     * @return list<array{loc: string, lastmod: string|null}>
     */
    public function index(): array
    {
        $sitemaps = [
            ['loc' => route('sitemaps.pages'), 'lastmod' => null],
            ['loc' => route('sitemaps.lists'), 'lastmod' => $this->format($this->publicLists()->max('updated_at'))],
        ];

        if ($this->newsIsPublic()) {
            $sitemaps[] = ['loc' => route('sitemaps.news'), 'lastmod' => $this->format(NewsArticle::published()->max('updated_at'))];
        }

        foreach (range(1, max(1, $this->gamesSitemapCount())) as $page) {
            $sitemaps[] = ['loc' => route('sitemaps.games', $page), 'lastmod' => null];
        }

        return $sitemaps;
    }

    /**
     * @return list<SitemapEntry>
     */
    public function pages(): array
    {
        $urls = [
            route('homepage'),
            route('releases.seasoned'),
            route('upcoming'),
            route('most-wanted'),
            route('events'),
            route('videos.index'),
            route('ports'),
        ];

        // The current year's page redirects to the current month, so list the month instead.
        foreach ($this->releaseYears() as $year) {
            $urls[] = $year === now()->year
                ? route('releases.year.month', [$year, now()->month])
                : route('releases.year', $year);
        }

        $entries = array_map(fn (string $url): array => ['loc' => $url], $urls);

        if ($this->newsIsPublic()) {
            $alternates = $this->newsAlternates(fn (NewsLocaleEnum $locale): string => $locale->indexUrl());

            foreach ($alternates as $url) {
                $entries[] = ['loc' => $url, 'alternates' => $alternates];
            }
        }

        return $entries;
    }

    /**
     * @return list<SitemapEntry>
     */
    public function lists(): array
    {
        return $this->publicLists()
            ->map(fn (GameList $list): array => [
                'loc' => route('lists.show', ['type' => $list->list_type->toSlug(), 'slug' => $list->slug]),
                'lastmod' => $this->format($list->updated_at),
            ])
            ->values()
            ->all();
    }

    /**
     * @return list<SitemapEntry>
     */
    public function news(): array
    {
        if (! $this->newsIsPublic()) {
            return [];
        }

        $entries = [];

        foreach (NewsArticle::published()->orderByDesc('published_at')->get() as $article) {
            $alternates = $this->newsAlternates(
                fn (NewsLocaleEnum $locale): ?string => $article->{$locale->slugColumn()} ? $locale->articleUrl($article) : null
            );

            foreach ($alternates as $url) {
                $entries[] = ['loc' => $url, 'lastmod' => $this->format($article->updated_at), 'alternates' => $alternates];
            }
        }

        return $entries;
    }

    public function gamesSitemapCount(): int
    {
        return (int) ceil(Game::whereNotNull('slug')->count() / self::GAMES_PER_SITEMAP);
    }

    /**
     * @return list<SitemapEntry>
     */
    public function games(int $page): array
    {
        return Game::whereNotNull('slug')
            ->orderBy('id')
            ->forPage($page, self::GAMES_PER_SITEMAP)
            ->get(['id', 'slug', 'updated_at'])
            ->map(fn (Game $game): array => [
                'loc' => route('game.show', $game->slug),
                'lastmod' => $this->format($game->updated_at),
            ])
            ->all();
    }

    /**
     * Same visibility rule guests get on /list/{type}/{slug}.
     *
     * @return Collection<int, GameList>
     */
    private function publicLists(): Collection
    {
        return GameList::whereIn('list_type', [ListTypeEnum::YEARLY->value, ListTypeEnum::SEASONED->value, ListTypeEnum::EVENTS->value])
            ->where('is_public', true)
            ->where('is_active', true)
            ->whereNotNull('slug')
            ->orderBy('id')
            ->get(['id', 'list_type', 'slug', 'updated_at']);
    }

    /**
     * Same years the releases page offers in its prev/next navigation.
     *
     * @return list<int>
     */
    private function releaseYears(): array
    {
        return GameList::yearly()
            ->where('is_system', true)
            ->where('is_active', true)
            ->whereNotNull('start_at')
            ->orderBy('start_at')
            ->pluck('start_at')
            ->map(fn (CarbonInterface $date): int => $date->year)
            ->unique()
            ->values()
            ->all();
    }

    private function newsIsPublic(): bool
    {
        return EnsureNewsFeatureEnabled::isVisibleTo(null);
    }

    /**
     * @param  callable(NewsLocaleEnum): ?string  $url
     * @return array<string, string> hreflang => url
     */
    private function newsAlternates(callable $url): array
    {
        $alternates = [];

        foreach (NewsLocaleEnum::cases() as $locale) {
            if ($href = $url($locale)) {
                $alternates[$locale->value] = $href;
            }
        }

        return $alternates;
    }

    /**
     * Accepts model dates and the raw strings returned by aggregate queries such as max().
     */
    private function format(CarbonInterface|string|null $date): ?string
    {
        if ($date === null || $date === '') {
            return null;
        }

        return ($date instanceof CarbonInterface ? $date : Carbon::parse($date))->toAtomString();
    }
}

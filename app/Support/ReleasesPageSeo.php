<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\Game;
use Illuminate\Support\Carbon;

class ReleasesPageSeo
{
    public const MONTHLY_IMAGE = 'images/releases-monthly-og.jpg';

    /**
     * Title, description, canonical URL, share image and JSON-LD for /releases/{year}[/{month}].
     *
     * @param  array<string, array{label: string, month_number: int|null, games: list<Game>}>  $gamesByMonth
     * @return array{title: string, description: string, canonical: string, image: string|null, imageAlt: string|null, structuredData: array<string, mixed>}
     */
    public static function for(int $year, ?int $month, ?string $only, array $gamesByMonth): array
    {
        $releasesTitle = "Game Releases {$year}";

        if ($month === null) {
            $canonical = $only === 'tba' ? route('releases.year', $year).'?only=tba' : route('releases.year', $year);

            return [
                'title' => $releasesTitle,
                'description' => "Every notable game releasing in {$year}, month by month — release dates, platforms and trailers, curated by Games Outbreak.",
                'canonical' => $canonical,
                'image' => null,
                'imageAlt' => null,
                'structuredData' => self::breadcrumb([
                    ['name' => $releasesTitle, 'url' => route('releases.year', $year)],
                ]),
            ];
        }

        $monthName = Carbon::create($year, $month)->format('F');
        $canonical = route('releases.year.month', [$year, $month]);
        $games = collect($gamesByMonth)->flatMap(fn (array $group): array => $group['games'])->values();
        $count = $games->count();

        $description = $count > 0
            ? "{$count} game ".str('release')->plural($count)." for {$monthName} {$year} — release dates, platforms and trailers, curated by Games Outbreak."
            : "Game releases for {$monthName} {$year} — release dates, platforms and trailers, curated by Games Outbreak.";

        return [
            'title' => "{$monthName} Game Releases {$year}",
            'description' => $description,
            'canonical' => $canonical,
            'image' => asset(self::MONTHLY_IMAGE),
            'imageAlt' => "Games Outbreak monthly releases — {$monthName} {$year}",
            'structuredData' => [
                '@context' => 'https://schema.org',
                '@graph' => [
                    self::breadcrumb([
                        ['name' => $releasesTitle, 'url' => route('releases.year', $year)],
                        ['name' => "{$monthName} {$year}", 'url' => $canonical],
                    ], withContext: false),
                    [
                        '@type' => 'CollectionPage',
                        'name' => "{$monthName} Game Releases {$year}",
                        'description' => $description,
                        'url' => $canonical,
                        'image' => asset(self::MONTHLY_IMAGE),
                        'isPartOf' => ['@type' => 'WebSite', 'name' => 'Games Outbreak', 'url' => url('/')],
                        'mainEntity' => [
                            '@type' => 'ItemList',
                            'numberOfItems' => $count,
                            'itemListElement' => $games->map(fn (Game $game, int $index): array => [
                                '@type' => 'ListItem',
                                'position' => $index + 1,
                                'name' => $game->name,
                                'url' => route('game.show', $game->slug),
                            ])->all(),
                        ],
                    ],
                ],
            ],
        ];
    }

    /**
     * @param  list<array{name: string, url: string}>  $trail  Crumbs after the site root
     * @return array<string, mixed>
     */
    private static function breadcrumb(array $trail, bool $withContext = true): array
    {
        $items = collect([['name' => 'Games Outbreak', 'url' => url('/')], ...$trail])
            ->map(fn (array $crumb, int $index): array => [
                '@type' => 'ListItem',
                'position' => $index + 1,
                'name' => $crumb['name'],
                'item' => $crumb['url'],
            ])
            ->all();

        return array_filter([
            '@context' => $withContext ? 'https://schema.org' : null,
            '@type' => 'BreadcrumbList',
            'itemListElement' => $items,
        ]);
    }
}

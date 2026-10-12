<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\PcPort;
use Illuminate\Support\Collection;

class PcPortStructuredData
{
    /**
     * Schema.org graph for the ports page: breadcrumb plus a CollectionPage listing every project.
     *
     * @param  Collection<int, PcPort>  $ports
     * @return array<string, mixed>
     */
    public static function for(Collection $ports, string $title, string $description): array
    {
        $pageUrl = route('ports');

        return [
            '@context' => 'https://schema.org',
            '@graph' => [
                [
                    '@type' => 'BreadcrumbList',
                    'itemListElement' => [
                        ['@type' => 'ListItem', 'position' => 1, 'name' => 'Games Outbreak', 'item' => url('/')],
                        ['@type' => 'ListItem', 'position' => 2, 'name' => $title, 'item' => $pageUrl],
                    ],
                ],
                [
                    '@type' => 'CollectionPage',
                    'name' => $title,
                    'description' => $description,
                    'url' => $pageUrl,
                    'image' => asset('images/ports-og.jpg'),
                    'isPartOf' => ['@type' => 'WebSite', 'name' => 'Games Outbreak', 'url' => url('/')],
                    'dateModified' => $ports->max('last_checked_at')?->toDateString(),
                    'mainEntity' => [
                        '@type' => 'ItemList',
                        'numberOfItems' => $ports->count(),
                        'itemListElement' => $ports->values()->map(fn (PcPort $port, int $index): array => array_filter([
                            '@type' => 'ListItem',
                            'position' => $index + 1,
                            'name' => $port->project_name ? "{$port->game_title} — {$port->project_name}" : $port->game_title,
                            'url' => $port->repo_url ?: $port->primary_source ?: $port->homepage_url,
                        ]))->all(),
                    ],
                ],
            ],
        ];
    }
}

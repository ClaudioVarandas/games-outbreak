<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Read-only reference for the IGDB / game-list maintenance CLI commands, surfaced on the
 * admin "CLI Reference" page. Kept in PHP (not Blade) so it stays the single source.
 */
class AdminCliReference
{
    /**
     * @return list<array{
     *     title: string,
     *     summary: string,
     *     commands: list<array{name: string, flags: array<string, string>, does: string, writes: string}>
     * }>
     */
    public static function tiers(): array
    {
        return [
            [
                'title' => 'Tier 1 — Refresh game records',
                'summary' => 'Writes the shared game data (name, release dates incl. date_format, platforms, genres, trailers, ratings, external sources, images). List pivots derive from this, so refresh games before deriving lists.',
                'commands' => [
                    [
                        'name' => 'igdb:gamelist:refresh {id}',
                        'flags' => [
                            '--force' => 'Refresh even games synced less than a day ago (otherwise skipped).',
                        ],
                        'does' => 'Refresh every game in one list from IGDB.',
                        'writes' => 'Game records (Tier 1).',
                    ],
                    [
                        'name' => 'igdb:update-stale',
                        'flags' => [
                            '--min-days=90' => 'Minimum days since last sync to count as stale.',
                            '--batch-size=50' => 'Games updated per batch.',
                            '--force' => 'Update regardless of the stale threshold.',
                        ],
                        'does' => 'Refresh games not synced in N days.',
                        'writes' => 'Game records (Tier 1).',
                    ],
                    [
                        'name' => 'igdb:update-popular',
                        'flags' => [
                            '--limit=100' => 'Maximum games to update.',
                            '--min-views=5' => 'Minimum view count to count as popular.',
                            '--force' => 'Update even if recently synced.',
                        ],
                        'does' => 'Refresh popular games (by view count).',
                        'writes' => 'Game records (Tier 1).',
                    ],
                    [
                        'name' => 'igdb:update-recently-released',
                        'flags' => [
                            '--days=60' => 'How many days back to consider.',
                            '--limit=100' => 'Maximum games to update.',
                            '--force' => 'Update even if recently synced.',
                        ],
                        'does' => 'Refresh games released in the last N days.',
                        'writes' => 'Game records (Tier 1).',
                    ],
                    [
                        'name' => 'igdb:upcoming:update',
                        'flags' => [
                            '--days=14' => 'Days ahead to fetch.',
                            '--start-date=' => 'Start date (Y-m-d), defaults to today.',
                            '--platforms=' => 'Comma-separated IGDB platform ids.',
                            '--limit=500' => 'Max games to fetch (IGDB max per query).',
                            '--igdb-id=' => 'Fetch a single game by IGDB id.',
                        ],
                        'does' => 'Fetch and create/update upcoming games from IGDB.',
                        'writes' => 'Game records (Tier 1) — creates and updates.',
                    ],
                ],
            ],
            [
                'title' => 'Tier 2 — Build lists, pivots & metadata',
                'summary' => 'Writes list membership and pivot data (game_list_game) plus list metadata. These read the stored game data, so they are only as fresh as Tier 1.',
                'commands' => [
                    [
                        'name' => 'games:lists:create',
                        'flags' => [
                            '--name=' => 'List name.',
                            '--start-date=' => 'Start date (Y-m-d).',
                            '--end-date=' => 'End date (Y-m-d).',
                            '--is-active=' => 'yes/no.',
                            '--is-public=' => 'yes/no.',
                            '--is-system=' => 'yes/no.',
                            '--igdb-ids=' => 'Comma-separated IGDB game ids.',
                        ],
                        'does' => 'Create a list and attach games from IGDB ids (fetches missing games).',
                        'writes' => 'New list row; pivots: order, platforms, release (concrete date or TBA + year).',
                    ],
                    [
                        'name' => 'igdb:events:import {event}',
                        'flags' => [
                            '--update' => 'Non-interactive: update the matching list (fail if none).',
                            '--create' => 'Non-interactive: create a new list (fail if one exists).',
                            '--no-games' => 'Import event metadata only, skip the game sync.',
                            '--accept-all' => 'Non-interactive: auto-pick the best match and accept prompts.',
                            '--public=' => 'Override is_public (yes/no).',
                        ],
                        'does' => 'Create/update an events list from an IGDB event (numeric id or name search); attach new games, refresh stale games, resolve trailers.',
                        'writes' => 'List metadata (event_data + start_at); pivots of NEW games; trailers (video_url); refreshes stale game records. Existing pivots are left to sync-pivot.',
                    ],
                    [
                        'name' => 'igdb:events:sync-live',
                        'flags' => [],
                        'does' => 'Scheduled every 30 min: for events inside their live window, run the same game sync.',
                        'writes' => 'New-game pivots + trailers (add-only); refreshes stale game records.',
                    ],
                    [
                        'name' => 'events:sync-to-yearly {event}',
                        'flags' => [
                            '--all' => 'Sync every eligible game, skipping the interactive picker.',
                        ],
                        'does' => "Copy an events list's games into the matching yearly list(s) by year.",
                        'writes' => 'Yearly list membership + pivots (copied from the event pivot).',
                    ],
                    [
                        'name' => 'igdb:gamelist:sync-pivot {id}',
                        'flags' => [
                            '--accept-all' => 'Apply every suggested change without prompting.',
                            '--no-refresh' => 'Skip the IGDB refresh and suggest from stored data.',
                        ],
                        'does' => "Refresh the list's games from IGDB, then review/apply IGDB-derived pivot changes for games already in a list.",
                        'writes' => 'Refreshes game records (Tier 1), then pivots of EXISTING games: release (date / is_tba / release_year), early access, platforms, genres.',
                    ],
                    [
                        'name' => 'system-list:create {type} {year}',
                        'flags' => [],
                        'does' => 'Create an empty yearly list.',
                        'writes' => 'New list row only (no games).',
                    ],
                ],
            ],
        ];
    }

    /**
     * @return list<string>
     */
    public static function rules(): array
    {
        return [
            'Tier 1 before Tier 2 — Tier-2 commands read stored game data. sync-pivot and the event syncs now refresh games from IGDB first; the list display still shows whatever the pivots hold.',
            'Event syncs auto-refresh stale games (older than services.igdb.event_game_refresh_hours, default 24h), but only set pivots for NEW games — existing games\' pivots still need sync-pivot.',
            '--force differs per command: gamelist:refresh --force ignores the 24h "recently synced" skip; update-stale --force ignores the 90-day threshold.',
            'The admin "Sync from IGDB" button mirrors igdb:events:import\'s game sync (new games + stale refresh + trailers), without rewriting metadata.',
        ];
    }

    public static function mentalModel(): string
    {
        return 'update-* / gamelist:refresh fix games → events:import / games:lists:create build lists → sync-pivot reconciles existing pivots → events:sync-to-yearly fans out to yearly.';
    }
}

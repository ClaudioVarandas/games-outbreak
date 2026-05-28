<?php

declare(strict_types=1);

namespace App\Services;

use App\DTOs\ReleaseHeroLine;
use App\DTOs\ReleaseHeroSummary;
use App\Enums\PlatformEnum;
use App\Enums\ReleaseHeroVariantEnum;
use App\Models\Game;
use App\Models\GameReleaseDate;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class GameReleaseSummaryService
{
    public function forHero(Game $game): ReleaseHeroSummary
    {
        $now = Carbon::now();
        $events = $game->releaseDates ?? collect();

        if ($events->isEmpty()) {
            return $this->fromFirstReleaseDate($game, $now);
        }

        $released = $events->filter(fn (GameReleaseDate $e) => $this->isReleased($e, $now));
        $earlyActive = $events->filter(fn (GameReleaseDate $e) => $this->isEarlyAccess($e) && $this->onOrBefore($e, $now));
        $future = $events->filter(fn (GameReleaseDate $e) => ! $this->isReleased($e, $now) && ! $this->isEarlyAccess($e) && $this->isFuture($e, $now));

        if ($released->isNotEmpty()) {
            return $this->releasedSummary($released, $future, $events, $now);
        }

        if ($earlyActive->isNotEmpty()) {
            return $this->earlyAccessSummary($earlyActive, $events, $now);
        }

        if ($future->isNotEmpty()) {
            return new ReleaseHeroSummary($this->comingSoonLine($future));
        }

        return new ReleaseHeroSummary($this->tbaLine($game));
    }

    private function releasedSummary(Collection $released, Collection $future, Collection $all, Carbon $now): ReleaseHeroSummary
    {
        $releasedPlatforms = $this->platformLabels($released);
        $primary = new ReleaseHeroLine(
            label: 'Available now',
            variant: ReleaseHeroVariantEnum::Success,
            platforms: $releasedPlatforms,
            date: null,
            description: implode(' · ', $releasedPlatforms),
        );

        $secondary = null;
        $futureNotReleased = $future->reject(fn (GameReleaseDate $e) => in_array($this->platformLabel($e), $releasedPlatforms, true));
        if ($futureNotReleased->isNotEmpty()) {
            $platforms = $this->platformLabels($futureNotReleased);
            $date = $this->earliestDateLabel($futureNotReleased);
            $secondary = new ReleaseHeroLine(
                label: 'Coming later',
                variant: ReleaseHeroVariantEnum::Upcoming,
                platforms: $platforms,
                date: $date,
                description: trim(implode(' · ', $platforms).($date ? ' · '.$date : '')),
            );
        }

        $note = null;
        $pastEa = $all->first(fn (GameReleaseDate $e) => $this->isEarlyAccess($e) && $this->onOrBefore($e, $now));
        if ($pastEa) {
            $note = trim(($this->platformLabel($pastEa) ?? 'Early access').' early access started on '.$this->dateLabel($pastEa));
        }

        return new ReleaseHeroSummary($primary, $secondary, $note);
    }

    private function earlyAccessSummary(Collection $earlyActive, Collection $all, Carbon $now): ReleaseHeroSummary
    {
        $platforms = $this->platformLabels($earlyActive);
        $since = $this->earliestDateLabel($earlyActive);
        $primary = new ReleaseHeroLine(
            label: 'In Early Access',
            variant: ReleaseHeroVariantEnum::EarlyAccess,
            platforms: $platforms,
            date: $since,
            description: trim(implode(' · ', $platforms).($since ? ' · Since '.$since : '')),
        );

        $secondary = null;
        $fullFuture = $all->filter(fn (GameReleaseDate $e) => ! $this->isEarlyAccess($e) && $this->isFuture($e, $now));
        if ($fullFuture->isNotEmpty()) {
            $p = $this->platformLabels($fullFuture);
            $date = $this->earliestDateLabel($fullFuture);
            $secondary = new ReleaseHeroLine(
                label: 'Full release',
                variant: ReleaseHeroVariantEnum::Upcoming,
                platforms: $p,
                date: $date,
                description: trim(implode(' · ', $p).($date ? ' · '.$date : '')),
            );
        }

        return new ReleaseHeroSummary($primary, $secondary);
    }

    private function comingSoonLine(Collection $future): ReleaseHeroLine
    {
        $platforms = $this->platformLabels($future);
        $date = $this->earliestDateLabel($future);

        return new ReleaseHeroLine(
            label: 'Coming soon',
            variant: ReleaseHeroVariantEnum::Upcoming,
            platforms: $platforms,
            date: $date,
            description: trim(implode(' · ', $platforms).($date ? ' · '.$date : '')),
        );
    }

    private function tbaLine(Game $game): ReleaseHeroLine
    {
        $platforms = $this->gamePlatformLabels($game);

        return new ReleaseHeroLine(
            label: 'Release date TBA',
            variant: ReleaseHeroVariantEnum::Tba,
            platforms: $platforms,
            date: null,
            description: $platforms === [] ? 'Platforms to be announced' : implode(' · ', $platforms),
        );
    }

    private function fromFirstReleaseDate(Game $game, Carbon $now): ReleaseHeroSummary
    {
        $platforms = $this->gamePlatformLabels($game);
        $first = $game->first_release_date;

        if ($first && $first->lte($now)) {
            return new ReleaseHeroSummary(new ReleaseHeroLine('Available now', ReleaseHeroVariantEnum::Success, $platforms, null, implode(' · ', $platforms)));
        }
        if ($first && $first->gt($now)) {
            $date = $first->format('j M Y');

            return new ReleaseHeroSummary(new ReleaseHeroLine('Coming soon', ReleaseHeroVariantEnum::Upcoming, $platforms, $date, trim(implode(' · ', $platforms).' · '.$date)));
        }

        return new ReleaseHeroSummary($this->tbaLine($game));
    }

    private function isReleased(GameReleaseDate $e, Carbon $now): bool
    {
        $name = strtolower($e->status?->name ?? '');
        if (in_array($name, ['released', 'full release'], true)) {
            if ($e->date !== null) {
                return $e->date->lte($now);
            }
            // No exact date — if a future year is set, it hasn't released yet
            if ($e->year !== null && $e->year > $now->year) {
                return false;
            }

            return true;
        }
        if ($this->isEarlyAccess($e)) {
            return false;
        }

        return $e->date !== null && $e->date->lte($now);
    }

    private function isEarlyAccess(GameReleaseDate $e): bool
    {
        return str_contains(strtolower($e->status?->name ?? ''), 'early access');
    }

    private function isFuture(GameReleaseDate $e, Carbon $now): bool
    {
        if ($e->date !== null) {
            return $e->date->gt($now);
        }

        return $e->year !== null;
    }

    private function onOrBefore(GameReleaseDate $e, Carbon $now): bool
    {
        return $e->date !== null && $e->date->lte($now);
    }

    private function platformLabel(GameReleaseDate $e): ?string
    {
        return $e->platform?->abbreviation;
    }

    /** @return string[] */
    private function platformLabels(Collection $events): array
    {
        return $events->map(fn (GameReleaseDate $e) => $this->platformLabel($e))
            ->filter()
            ->unique()
            ->sortBy(fn (string $label) => $this->priorityForLabel($events, $label))
            ->values()
            ->all();
    }

    /** @return string[] */
    private function gamePlatformLabels(Game $game): array
    {
        return ($game->platforms ?? collect())
            ->sortBy(fn ($p) => PlatformEnum::getPriority($p->igdb_id))
            ->map(fn ($p) => PlatformEnum::fromIgdbId($p->igdb_id)?->label() ?? $p->name)
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    private function priorityForLabel(Collection $events, string $label): int
    {
        $e = $events->first(fn (GameReleaseDate $x) => $this->platformLabel($x) === $label);
        $igdbId = $e?->platform?->igdb_id;

        return $igdbId ? PlatformEnum::getPriority($igdbId) : 999;
    }

    private function dateLabel(GameReleaseDate $e): ?string
    {
        if ($e->date !== null) {
            return $e->date->format('j M Y');
        }
        if ($e->human_readable) {
            return $e->human_readable;
        }

        return $e->year ? (string) $e->year : null;
    }

    private function earliestDateLabel(Collection $events): ?string
    {
        $withDate = $events->filter(fn (GameReleaseDate $e) => $e->date !== null)->sortBy('date');
        if ($withDate->isNotEmpty()) {
            return $withDate->first()->date->format('j M Y');
        }
        $first = $events->first();

        return $first ? ('Expected '.($first->human_readable ?? $first->year)) : null;
    }
}

<?php

use App\Enums\ListTypeEnum;
use App\Models\GameList;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * Recompute start_at for every event list from its event_data so the queryable
     * column matches the authoritative event_time + event_timezone (fixing the
     * app-timezone drift that previously crept in via the admin form).
     */
    public function up(): void
    {
        GameList::query()
            ->where('list_type', ListTypeEnum::EVENTS->value)
            ->whereNotNull('event_data')
            ->cursor()
            ->each(function (GameList $list): void {
                $derived = GameList::eventStartAtFor($list->event_data);

                if ($derived !== null && ! $derived->equalTo($list->start_at)) {
                    $list->start_at = $derived;
                    $list->saveQuietly();
                }
            });
    }

    public function down(): void
    {
        // No-op: the previous (drifted) start_at values are not worth restoring.
    }
};

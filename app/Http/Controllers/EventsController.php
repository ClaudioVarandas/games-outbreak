<?php

namespace App\Http\Controllers;

use App\Models\GameList;
use Illuminate\View\View;

class EventsController extends Controller
{
    public function __invoke(): View
    {
        $events = GameList::events()
            ->where('is_active', true)
            ->where('is_public', true)
            ->get();

        $upcoming = $events
            ->filter(fn (GameList $e) => ! ($e->getEventTime()?->isPast() ?? false))
            ->sortBy(fn (GameList $e) => $e->getEventTime()?->getTimestamp() ?? PHP_INT_MAX)
            ->values();

        $past = $events
            ->filter(fn (GameList $e) => $e->getEventTime()?->isPast() ?? false)
            ->sortByDesc(fn (GameList $e) => $e->getEventTime()?->getTimestamp() ?? 0)
            ->values();

        return view('events.index', compact('upcoming', 'past'));
    }
}

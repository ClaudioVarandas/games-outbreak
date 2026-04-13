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
            ->orderBy('start_at', 'desc')
            ->get();

        $upcoming = $events
            ->filter(fn (GameList $e) => ! ($e->getEventTime()?->isPast() ?? false))
            ->sortBy('start_at')
            ->values();

        $past = $events
            ->filter(fn (GameList $e) => $e->getEventTime()?->isPast() ?? false)
            ->values();

        return view('events.index', compact('upcoming', 'past'));
    }
}

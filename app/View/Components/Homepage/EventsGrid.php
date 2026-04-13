<?php

namespace App\View\Components\Homepage;

use Closure;
use Illuminate\Contracts\View\View;
use Illuminate\View\Component;

class EventsGrid extends Component
{
    public function render(): View|Closure|string
    {
        return view('components.homepage.events-grid');
    }
}

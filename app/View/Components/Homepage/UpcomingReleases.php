<?php

namespace App\View\Components\Homepage;

use Closure;
use Illuminate\Contracts\View\View;
use Illuminate\View\Component;

class UpcomingReleases extends Component
{
    public function render(): View|Closure|string
    {
        return view('components.homepage.upcoming-releases');
    }
}

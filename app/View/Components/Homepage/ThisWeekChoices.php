<?php

namespace App\View\Components\Homepage;

use Closure;
use Illuminate\Contracts\View\View;
use Illuminate\View\Component;

class ThisWeekChoices extends Component
{
    public function render(): View|Closure|string
    {
        return view('components.homepage.this-week-choices');
    }
}

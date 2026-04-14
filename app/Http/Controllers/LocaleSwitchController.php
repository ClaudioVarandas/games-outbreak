<?php

namespace App\Http\Controllers;

use App\Enums\NewsLocaleEnum;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class LocaleSwitchController extends Controller
{
    public function __invoke(Request $request, string $prefix): RedirectResponse
    {
        NewsLocaleEnum::fromPrefix($prefix);

        session(['locale' => $prefix]);

        return redirect()->back(fallback: route('homepage'));
    }
}

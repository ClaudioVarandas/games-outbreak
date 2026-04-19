<?php

namespace App\Http\Controllers;

use App\Enums\NewsLocaleEnum;
use App\Models\NewsArticle;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class LocaleSwitchController extends Controller
{
    public function __invoke(Request $request, string $prefix): RedirectResponse
    {
        $target = NewsLocaleEnum::fromPrefix($prefix);

        session(['locale' => $prefix]);

        $previousPath = parse_url(url()->previous(), PHP_URL_PATH) ?? '';

        foreach (NewsLocaleEnum::cases() as $from) {
            $basePath = parse_url($from->indexUrl(), PHP_URL_PATH) ?? '';

            if ($basePath === '' || ! str_starts_with($previousPath, $basePath)) {
                continue;
            }

            $remainder = trim(substr($previousPath, strlen($basePath)), '/');

            if ($remainder === '') {
                return redirect()->to($target->indexUrl());
            }

            $article = NewsArticle::published()
                ->where($from->slugColumn(), $remainder)
                ->first();

            if ($article && $article->{$target->slugColumn()}) {
                return redirect()->to($target->articleUrl($article));
            }

            return redirect()->to($target->indexUrl());
        }

        return redirect()->back(fallback: route('homepage'));
    }
}

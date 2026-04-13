<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\NewsLocaleEnum;
use App\Models\NewsArticle;
use Illuminate\View\View;

class NewsArticleController extends Controller
{
    public function index(string $localePrefix): View
    {
        $locale = NewsLocaleEnum::fromPrefix($localePrefix);

        $articles = NewsArticle::published()
            ->with(['localizations' => fn ($q) => $q->where('locale', $locale->value)])
            ->orderByDesc('published_at')
            ->paginate(20);

        return view('news-articles.index', compact('articles', 'locale'));
    }

    public function show(string $localePrefix, string $slug): View
    {
        $locale = NewsLocaleEnum::fromPrefix($localePrefix);

        $article = NewsArticle::published()
            ->where($locale->slugColumn(), $slug)
            ->with('localizations')
            ->firstOrFail();

        $localization = $article->localization($locale->value);

        if (! $localization) {
            abort(404);
        }

        return view('news-articles.show', compact('article', 'localization', 'locale'));
    }
}

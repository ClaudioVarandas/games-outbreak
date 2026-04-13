<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin\News;

use App\Actions\News\PublishNewsArticle;
use App\Actions\News\ScheduleNewsArticle;
use App\Enums\NewsArticleStatusEnum;
use App\Enums\NewsLocaleEnum;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\News\UpdateNewsArticleRequest;
use App\Models\NewsArticle;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class NewsArticleController extends Controller
{
    public function index(Request $request): View
    {
        $query = NewsArticle::with(['import', 'localizations', 'author'])
            ->orderByDesc('created_at');

        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        if ($request->filled('source')) {
            $query->where('source_name', $request->input('source'));
        }

        $articles = $query->paginate(20)->withQueryString();
        $statuses = NewsArticleStatusEnum::cases();

        return view('admin.news-articles.index', compact('articles', 'statuses'));
    }

    public function edit(NewsArticle $newsArticle): View
    {
        $newsArticle->load('localizations', 'import');
        $locales = NewsLocaleEnum::cases();

        return view('admin.news-articles.edit', [
            'article' => $newsArticle,
            'locales' => $locales,
        ]);
    }

    public function update(UpdateNewsArticleRequest $request, NewsArticle $newsArticle): RedirectResponse
    {
        $data = $request->validated();

        $newsArticle->update([
            'featured_image_url' => $data['featured_image_url'] ?? $newsArticle->featured_image_url,
        ]);

        $slugUpdates = [];

        foreach ($data['localizations'] as $locData) {
            $newsArticle->localizations()->updateOrCreate(
                ['locale' => $locData['locale']],
                [
                    'title' => $locData['title'],
                    'summary_short' => $locData['summary_short'] ?? null,
                    'summary_medium' => $locData['summary_medium'] ?? null,
                    'body' => $locData['body'] ?? null,
                    'seo_title' => $locData['seo_title'] ?? null,
                    'seo_description' => $locData['seo_description'] ?? null,
                ]
            );

            $locale = NewsLocaleEnum::tryFrom($locData['locale']);
            if (! $locale) {
                continue;
            }

            $column = $locale->slugColumn();
            $explicitSlug = $locData['slug'] ?? null;

            if (! empty($explicitSlug)) {
                $slugUpdates[$column] = $explicitSlug;
            } elseif (empty($newsArticle->{$column})) {
                $slugUpdates[$column] = NewsArticle::generateUniqueSlug($locData['title'], $column);
            }
        }

        if ($slugUpdates) {
            $newsArticle->update($slugUpdates);
        }

        return redirect()->route('admin.news-articles.edit', $newsArticle)
            ->with('success', 'Article saved.');
    }

    public function publish(NewsArticle $newsArticle, PublishNewsArticle $action): RedirectResponse
    {
        $action->handle($newsArticle);

        return redirect()->route('admin.news-articles.edit', $newsArticle)
            ->with('success', 'Article published.');
    }

    public function schedule(Request $request, NewsArticle $newsArticle, ScheduleNewsArticle $action): RedirectResponse
    {
        $request->validate(['scheduled_at' => ['required', 'date', 'after:now']]);

        $action->handle($newsArticle, Carbon::parse($request->input('scheduled_at')));

        return redirect()->route('admin.news-articles.edit', $newsArticle)
            ->with('success', 'Article scheduled.');
    }

    public function destroy(NewsArticle $newsArticle): RedirectResponse
    {
        $newsArticle->delete();

        return redirect()->route('admin.news-articles.index')
            ->with('success', 'Article deleted.');
    }
}

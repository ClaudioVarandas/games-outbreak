<?php

namespace App\Http\Controllers;

use App\Models\News;
use Illuminate\View\View;

class NewsController extends Controller
{
    public function index(): View
    {
        $news = News::published()
            ->with('author')
            ->orderByDesc('published_at')
            ->paginate(20);

        return view('news.index', compact('news'));
    }

    public function show(News $news): View
    {
        if (! $news->isPublished() && ! auth()->user()?->isAdmin()) {
            abort(404);
        }

        $news->load('author');

        $relatedNews = News::published()
            ->where('id', '!=', $news->id)
            ->orderByDesc('published_at')
            ->limit(3)
            ->get();

        return view('news.show', compact('news', 'relatedNews'));
    }
}

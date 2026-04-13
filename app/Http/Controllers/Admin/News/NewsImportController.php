<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin\News;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\News\StoreNewsImportRequest;
use App\Jobs\News\ImportNewsUrlJob;
use App\Models\NewsImport;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class NewsImportController extends Controller
{
    public function index(): View
    {
        $imports = NewsImport::with('user', 'article')
            ->orderByDesc('created_at')
            ->paginate(20);

        return view('admin.news-imports.index', compact('imports'));
    }

    public function create(): View
    {
        $recentImports = NewsImport::with('user')
            ->orderByDesc('created_at')
            ->limit(10)
            ->get();

        return view('admin.news-imports.create', compact('recentImports'));
    }

    public function store(StoreNewsImportRequest $request): RedirectResponse
    {
        ImportNewsUrlJob::dispatch($request->validated('url'), auth()->id());

        return redirect()->route('admin.news-imports.index')
            ->with('success', 'Import queued successfully. It will be processed shortly.');
    }

    public function show(NewsImport $newsImport): View
    {
        $newsImport->load('user', 'article.localizations');

        return view('admin.news-imports.show', ['import' => $newsImport]);
    }
}

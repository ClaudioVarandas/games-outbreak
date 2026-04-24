<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin\News;

use App\Enums\NewsImportStatusEnum;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\News\StoreNewsImportRequest;
use App\Jobs\News\ExtractNewsArticleJob;
use App\Jobs\News\ImportNewsUrlJob;
use App\Models\NewsImport;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
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

    public function retry(NewsImport $newsImport): RedirectResponse
    {
        if (! $newsImport->isFailed()) {
            return back()->with('error', 'Only failed imports can be retried.');
        }

        $newsImport->update([
            'status' => NewsImportStatusEnum::Pending,
            'failure_reason' => null,
        ]);

        ExtractNewsArticleJob::dispatch($newsImport);

        return back()->with('success', 'Import retry queued.');
    }

    public function statuses(Request $request): JsonResponse
    {
        $ids = collect($request->query('ids', []))
            ->map(fn ($id) => (int) $id)
            ->filter()
            ->values();

        if ($ids->isEmpty()) {
            return response()->json([]);
        }

        $imports = NewsImport::whereIn('id', $ids)->get(['id', 'status', 'failure_reason']);

        return response()->json($imports->mapWithKeys(fn (NewsImport $import) => [
            $import->id => [
                'status' => $import->status->value,
                'label' => $import->status->label(),
                'color_class' => $import->status->colorClass(),
                'is_final' => $import->status->isFinal(),
                'is_failed' => $import->isFailed(),
                'failure_reason' => $import->failure_reason,
            ],
        ]));
    }
}

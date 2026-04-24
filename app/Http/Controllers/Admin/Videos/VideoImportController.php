<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin\Videos;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Videos\StoreVideoImportRequest;
use App\Jobs\Videos\ImportYoutubeVideoJob;
use App\Models\Video;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class VideoImportController extends Controller
{
    public function index(): View
    {
        $videos = Video::with('user')
            ->orderByDesc('created_at')
            ->paginate(20);

        return view('admin.videos.index', compact('videos'));
    }

    public function create(): View
    {
        $recentVideos = Video::with('user')
            ->orderByDesc('created_at')
            ->limit(10)
            ->get();

        return view('admin.videos.create', compact('recentVideos'));
    }

    public function store(StoreVideoImportRequest $request): RedirectResponse
    {
        ImportYoutubeVideoJob::dispatch($request->validated('url'), auth()->id());

        return redirect()->route('admin.videos.index')
            ->with('success', 'Video import queued. It will appear once YouTube metadata is fetched.');
    }

    public function show(Video $video): View
    {
        $video->load('user');

        return view('admin.videos.show', ['video' => $video]);
    }

    public function toggleFeatured(Video $video): RedirectResponse
    {
        DB::transaction(function () use ($video) {
            if ($video->is_featured) {
                $video->update(['is_featured' => false]);

                return;
            }

            Video::where('is_featured', true)->update(['is_featured' => false]);
            $video->update(['is_featured' => true]);
        });

        return back()->with('success', $video->fresh()->is_featured
            ? 'Video marked as featured.'
            : 'Video unmarked as featured.');
    }

    public function toggleActive(Video $video): RedirectResponse
    {
        $video->update(['is_active' => ! $video->is_active]);

        return back()->with('success', $video->fresh()->is_active
            ? 'Video activated.'
            : 'Video hidden.');
    }

    public function destroy(Video $video): RedirectResponse
    {
        $video->delete();

        return redirect()->route('admin.videos.index')
            ->with('success', 'Video deleted.');
    }
}

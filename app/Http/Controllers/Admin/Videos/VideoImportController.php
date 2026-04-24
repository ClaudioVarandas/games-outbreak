<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin\Videos;

use App\Actions\Videos\MaybeBroadcastVideo;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Videos\StoreVideoImportRequest;
use App\Http\Requests\Admin\Videos\UpdateVideoCategoryAssignmentRequest;
use App\Jobs\Videos\ImportYoutubeVideoJob;
use App\Models\Video;
use App\Models\VideoCategory;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class VideoImportController extends Controller
{
    public function index(): View
    {
        $videos = Video::with(['user', 'category'])
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
        ImportYoutubeVideoJob::dispatch(
            $request->validated('url'),
            auth()->id(),
            $request->boolean('should_broadcast', true),
        );

        return redirect()->route('admin.videos.index')
            ->with('success', 'Video import queued. It will appear once YouTube metadata is fetched.');
    }

    public function show(Video $video): View
    {
        $video->load(['user', 'category']);
        $categories = VideoCategory::active()->ordered()->get();

        return view('admin.videos.show', [
            'video' => $video,
            'categories' => $categories,
        ]);
    }

    public function updateCategory(UpdateVideoCategoryAssignmentRequest $request, Video $video): RedirectResponse
    {
        $video->update([
            'video_category_id' => $request->validated('video_category_id'),
        ]);

        return back()->with('success', $video->fresh()->video_category_id
            ? 'Category assigned.'
            : 'Category cleared.');
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

    public function toggleActive(Video $video, MaybeBroadcastVideo $maybeBroadcast): RedirectResponse
    {
        $video->update(['is_active' => ! $video->is_active]);

        $fresh = $video->fresh();

        if ($fresh->is_active) {
            $maybeBroadcast->handle($fresh);
        }

        return back()->with('success', $fresh->is_active
            ? 'Video activated.'
            : 'Video hidden.');
    }

    public function toggleShouldBroadcast(Video $video): RedirectResponse
    {
        $video->update(['should_broadcast' => ! $video->should_broadcast]);

        return back()->with('success', $video->fresh()->should_broadcast
            ? 'Broadcast enabled for this video.'
            : 'Broadcast disabled for this video.');
    }

    public function destroy(Video $video): RedirectResponse
    {
        $video->delete();

        return redirect()->route('admin.videos.index')
            ->with('success', 'Video deleted.');
    }
}

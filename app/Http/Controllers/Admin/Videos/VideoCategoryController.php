<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin\Videos;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Videos\StoreVideoCategoryRequest;
use App\Http\Requests\Admin\Videos\UpdateVideoCategoryRequest;
use App\Models\VideoCategory;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class VideoCategoryController extends Controller
{
    public function index(): View
    {
        $categories = VideoCategory::query()
            ->withCount('videos')
            ->ordered()
            ->get();

        return view('admin.video-categories.index', compact('categories'));
    }

    public function store(StoreVideoCategoryRequest $request): RedirectResponse
    {
        VideoCategory::create($request->validated());

        return back()->with('success', 'Category created.');
    }

    public function update(UpdateVideoCategoryRequest $request, VideoCategory $videoCategory): RedirectResponse
    {
        $videoCategory->update($request->validated());

        return back()->with('success', 'Category updated.');
    }

    public function destroy(VideoCategory $videoCategory): RedirectResponse
    {
        $inUse = $videoCategory->videos()->count();
        $videoCategory->delete();

        $message = $inUse > 0
            ? "Category deleted. {$inUse} video(s) no longer have a category."
            : 'Category deleted.';

        return back()->with('success', $message);
    }
}

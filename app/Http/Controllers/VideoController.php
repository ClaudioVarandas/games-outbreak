<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Video;
use Illuminate\View\View;

class VideoController extends Controller
{
    public function index(): View
    {
        $videos = Video::query()
            ->publicVisible()
            ->with('category')
            ->orderByDesc('created_at')
            ->paginate(20);

        return view('videos.index', compact('videos'));
    }
}

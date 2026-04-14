<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin\News;

use App\Http\Controllers\Controller;
use App\Models\NewsArticle;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class NewsArticleImageUploadController extends Controller
{
    public function __invoke(Request $request, NewsArticle $newsArticle): JsonResponse
    {
        $request->validate([
            'image' => ['required', 'image', 'max:5120'],
        ]);

        $path = Storage::disk('public')->putFile('news-article-images', $request->file('image'));
        $url = '/storage/'.$path;

        $newsArticle->update(['featured_image_url' => $url]);

        return response()->json(['url' => $url]);
    }
}

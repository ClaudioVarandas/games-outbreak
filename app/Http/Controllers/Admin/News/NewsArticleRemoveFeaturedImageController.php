<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin\News;

use App\Http\Controllers\Controller;
use App\Models\NewsArticle;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class NewsArticleRemoveFeaturedImageController extends Controller
{
    public function __invoke(Request $request, NewsArticle $newsArticle): JsonResponse
    {
        $newsArticle->update(['featured_image_url' => null]);

        return response()->json(['success' => true]);
    }
}

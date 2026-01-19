<?php

namespace App\Http\Controllers;

use App\Contracts\ContentExtractorInterface;
use App\Enums\NewsStatusEnum;
use App\Http\Requests\StoreNewsRequest;
use App\Http\Requests\UpdateNewsRequest;
use App\Models\News;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AdminNewsController extends Controller
{
    public function index(): View
    {
        $news = News::with('author')
            ->orderByDesc('created_at')
            ->paginate(20);

        return view('admin.news.index', compact('news'));
    }

    public function create(): View
    {
        $statuses = NewsStatusEnum::cases();

        return view('admin.news.create', compact('statuses'));
    }

    public function store(StoreNewsRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $data['user_id'] = auth()->id();

        if ($data['status'] === NewsStatusEnum::Published->value && empty($data['published_at'])) {
            $data['published_at'] = now();
        }

        $news = News::create($data);

        return redirect()->route('admin.news.edit', $news)
            ->with('success', 'News article created successfully.');
    }

    public function edit(News $news): View
    {
        $statuses = NewsStatusEnum::cases();

        return view('admin.news.edit', compact('news', 'statuses'));
    }

    public function update(UpdateNewsRequest $request, News $news): RedirectResponse
    {
        $data = $request->validated();

        $wasPublished = $news->status === NewsStatusEnum::Published;
        $willBePublished = $data['status'] === NewsStatusEnum::Published->value;

        if (! $wasPublished && $willBePublished && empty($data['published_at'])) {
            $data['published_at'] = now();
        }

        $news->update($data);

        return redirect()->route('admin.news.edit', $news)
            ->with('success', 'News article updated successfully.');
    }

    public function destroy(News $news): RedirectResponse
    {
        $news->delete();

        return redirect()->route('admin.news.index')
            ->with('success', 'News article deleted successfully.');
    }

    public function importFromUrl(Request $request, ContentExtractorInterface $extractor): JsonResponse
    {
        if (! config('features.news_url_import')) {
            return response()->json(['error' => 'URL import feature is disabled.'], 403);
        }

        $request->validate([
            'url' => ['required', 'url'],
        ]);

        try {
            $data = $extractor->extract($request->input('url'));
        } catch (\RuntimeException $e) {
            return response()->json([
                'error' => $e->getMessage(),
            ], 422);
        }

        if (empty($data['title']) && empty($data['content'])) {
            return response()->json([
                'error' => 'Could not extract content from the provided URL.',
            ], 422);
        }

        $tiptapContent = $this->markdownToTiptap($data['content'] ?? '');

        return response()->json([
            'success' => true,
            'data' => [
                'title' => $data['title'],
                'summary' => $data['summary'],
                'content' => $tiptapContent,
                'image_path' => $data['image'],
                'source_url' => $request->input('url'),
                'source_name' => $data['source_name'],
            ],
        ]);
    }

    protected function markdownToTiptap(string $markdown): array
    {
        $content = [];
        $lines = explode("\n", $markdown);
        $currentParagraph = '';

        foreach ($lines as $line) {
            $trimmedLine = trim($line);

            if (empty($trimmedLine)) {
                if (! empty($currentParagraph)) {
                    $content[] = [
                        'type' => 'paragraph',
                        'content' => [
                            ['type' => 'text', 'text' => $currentParagraph],
                        ],
                    ];
                    $currentParagraph = '';
                }

                continue;
            }

            if (preg_match('/^#{1,6}\s+(.+)$/', $trimmedLine, $matches)) {
                if (! empty($currentParagraph)) {
                    $content[] = [
                        'type' => 'paragraph',
                        'content' => [
                            ['type' => 'text', 'text' => $currentParagraph],
                        ],
                    ];
                    $currentParagraph = '';
                }

                $level = strlen(preg_replace('/[^#]/', '', $trimmedLine));
                $content[] = [
                    'type' => 'heading',
                    'attrs' => ['level' => min($level, 6)],
                    'content' => [
                        ['type' => 'text', 'text' => $matches[1]],
                    ],
                ];

                continue;
            }

            if (! empty($currentParagraph)) {
                $currentParagraph .= ' ';
            }
            $currentParagraph .= $trimmedLine;
        }

        if (! empty($currentParagraph)) {
            $content[] = [
                'type' => 'paragraph',
                'content' => [
                    ['type' => 'text', 'text' => $currentParagraph],
                ],
            ];
        }

        return [
            'type' => 'doc',
            'content' => $content ?: [
                ['type' => 'paragraph', 'content' => []],
            ],
        ];
    }
}

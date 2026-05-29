<?php

declare(strict_types=1);

namespace App\Console\Commands\News;

use App\Models\NewsArticleLocalization;
use App\Support\News\MarkdownToTiptapConverter;
use Illuminate\Console\Command;

class FixNewsMarkdownLinks extends Command
{
    protected $signature = 'news:fix-markdown-links {--dry-run : Report affected localizations without saving}';

    protected $description = 'Convert raw markdown links left inside stored Tiptap text nodes into proper link marks';

    public function handle(MarkdownToTiptapConverter $converter): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $changed = 0;
        $scanned = 0;

        NewsArticleLocalization::query()
            ->whereNotNull('body')
            ->chunkById(100, function ($localizations) use ($converter, $dryRun, &$changed, &$scanned): void {
                foreach ($localizations as $localization) {
                    $scanned++;

                    $body = $localization->body;
                    if (! is_array($body)) {
                        continue;
                    }

                    $fixed = $this->transformNode($body, $converter);

                    if ($fixed === $body) {
                        continue;
                    }

                    $changed++;
                    $this->line(sprintf('  [%d] %s (%s)', $localization->id, $localization->title, $localization->locale->value));

                    if (! $dryRun) {
                        $localization->body = $fixed;
                        $localization->save();
                    }
                }
            });

        $this->newLine();
        $this->info(sprintf(
            '%s %d of %d localization(s) with raw markdown links.',
            $dryRun ? 'Found' : 'Fixed',
            $changed,
            $scanned
        ));

        return self::SUCCESS;
    }

    /**
     * @param  array<string, mixed>  $node
     * @return array<string, mixed>
     */
    private function transformNode(array $node, MarkdownToTiptapConverter $converter): array
    {
        if (! isset($node['content']) || ! is_array($node['content'])) {
            return $node;
        }

        $newContent = [];

        foreach ($node['content'] as $child) {
            if ($this->isUnmarkedTextWithLink($child)) {
                array_push($newContent, ...$converter->parseInline($child['text']));

                continue;
            }

            $newContent[] = is_array($child) ? $this->transformNode($child, $converter) : $child;
        }

        $node['content'] = $newContent;

        return $node;
    }

    /**
     * @param  mixed  $child
     */
    private function isUnmarkedTextWithLink($child): bool
    {
        return is_array($child)
            && ($child['type'] ?? null) === 'text'
            && empty($child['marks'])
            && is_string($child['text'] ?? null)
            && preg_match('/\[[^\]]+\]\([^)\s]+\)/su', $child['text']) === 1;
    }
}

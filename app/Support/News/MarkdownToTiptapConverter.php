<?php

declare(strict_types=1);

namespace App\Support\News;

class MarkdownToTiptapConverter
{
    public function convert(string $markdown): array
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
                        'content' => [['type' => 'text', 'text' => $currentParagraph]],
                    ];
                    $currentParagraph = '';
                }

                continue;
            }

            if (preg_match('/^#{1,6}\s+(.+)$/', $trimmedLine, $matches)) {
                if (! empty($currentParagraph)) {
                    $content[] = [
                        'type' => 'paragraph',
                        'content' => [['type' => 'text', 'text' => $currentParagraph]],
                    ];
                    $currentParagraph = '';
                }
                $level = strlen(preg_replace('/[^#]/', '', $trimmedLine));
                $content[] = [
                    'type' => 'heading',
                    'attrs' => ['level' => min($level, 6)],
                    'content' => [['type' => 'text', 'text' => $matches[1]]],
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
                'content' => [['type' => 'text', 'text' => $currentParagraph]],
            ];
        }

        return [
            'type' => 'doc',
            'content' => $content ?: [['type' => 'paragraph', 'content' => []]],
        ];
    }
}

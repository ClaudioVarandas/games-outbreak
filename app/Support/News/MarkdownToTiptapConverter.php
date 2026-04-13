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
                        'content' => $this->parseInline($currentParagraph),
                    ];
                    $currentParagraph = '';
                }

                continue;
            }

            if (preg_match('/^(#{1,6})\s+(.+)$/', $trimmedLine, $matches)) {
                if (! empty($currentParagraph)) {
                    $content[] = [
                        'type' => 'paragraph',
                        'content' => $this->parseInline($currentParagraph),
                    ];
                    $currentParagraph = '';
                }

                $level = min(strlen($matches[1]), 6);
                $content[] = [
                    'type' => 'heading',
                    'attrs' => ['level' => $level],
                    'content' => $this->parseInline($matches[2]),
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
                'content' => $this->parseInline($currentParagraph),
            ];
        }

        return [
            'type' => 'doc',
            'content' => $content ?: [['type' => 'paragraph', 'content' => []]],
        ];
    }

    /** Parse inline markdown (**bold**, *italic*, ***both***) into Tiptap text nodes with marks. */
    private function parseInline(string $text): array
    {
        $parts = preg_split(
            '/(\*\*\*.+?\*\*\*|\*\*.+?\*\*|\*.+?\*|_.+?_)/su',
            $text,
            -1,
            PREG_SPLIT_DELIM_CAPTURE
        );

        $nodes = [];

        foreach ($parts as $part) {
            if ($part === '') {
                continue;
            }

            if (preg_match('/^\*\*\*(.+)\*\*\*$/su', $part, $m)) {
                $nodes[] = ['type' => 'text', 'text' => $m[1], 'marks' => [['type' => 'bold'], ['type' => 'italic']]];
            } elseif (preg_match('/^\*\*(.+)\*\*$/su', $part, $m)) {
                $nodes[] = ['type' => 'text', 'text' => $m[1], 'marks' => [['type' => 'bold']]];
            } elseif (preg_match('/^\*(.+)\*$/su', $part, $m) || preg_match('/^_(.+)_$/su', $part, $m)) {
                $nodes[] = ['type' => 'text', 'text' => $m[1], 'marks' => [['type' => 'italic']]];
            } else {
                $nodes[] = ['type' => 'text', 'text' => $part];
            }
        }

        return $nodes ?: [['type' => 'text', 'text' => '']];
    }
}
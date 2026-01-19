@php
    if (!function_exists('renderTiptapNode')) {
        function renderTiptapNode($node) {
        if (!is_array($node)) return '';

        $html = '';
        $type = $node['type'] ?? '';
        $content = $node['content'] ?? [];
        $attrs = $node['attrs'] ?? [];

        switch ($type) {
            case 'doc':
                foreach ($content as $child) {
                    $html .= renderTiptapNode($child);
                }
                break;

            case 'paragraph':
                $innerHtml = '';
                foreach ($content as $child) {
                    $innerHtml .= renderTiptapNode($child);
                }
                $html .= '<p>' . ($innerHtml ?: '&nbsp;') . '</p>';
                break;

            case 'heading':
                $level = $attrs['level'] ?? 2;
                $innerHtml = '';
                foreach ($content as $child) {
                    $innerHtml .= renderTiptapNode($child);
                }
                $html .= "<h{$level}>{$innerHtml}</h{$level}>";
                break;

            case 'bulletList':
                $innerHtml = '';
                foreach ($content as $child) {
                    $innerHtml .= renderTiptapNode($child);
                }
                $html .= '<ul>' . $innerHtml . '</ul>';
                break;

            case 'orderedList':
                $innerHtml = '';
                foreach ($content as $child) {
                    $innerHtml .= renderTiptapNode($child);
                }
                $html .= '<ol>' . $innerHtml . '</ol>';
                break;

            case 'listItem':
                $innerHtml = '';
                foreach ($content as $child) {
                    $innerHtml .= renderTiptapNode($child);
                }
                $html .= '<li>' . $innerHtml . '</li>';
                break;

            case 'blockquote':
                $innerHtml = '';
                foreach ($content as $child) {
                    $innerHtml .= renderTiptapNode($child);
                }
                $html .= '<blockquote>' . $innerHtml . '</blockquote>';
                break;

            case 'image':
                $src = e($attrs['src'] ?? '');
                $alt = e($attrs['alt'] ?? '');
                $html .= "<img src=\"{$src}\" alt=\"{$alt}\" class=\"max-w-full rounded-lg\" loading=\"lazy\">";
                break;

            case 'text':
                $text = e($node['text'] ?? '');
                $marks = $node['marks'] ?? [];

                foreach ($marks as $mark) {
                    $markType = $mark['type'] ?? '';
                    switch ($markType) {
                        case 'bold':
                            $text = '<strong>' . $text . '</strong>';
                            break;
                        case 'italic':
                            $text = '<em>' . $text . '</em>';
                            break;
                        case 'strike':
                            $text = '<s>' . $text . '</s>';
                            break;
                        case 'link':
                            $href = e($mark['attrs']['href'] ?? '#');
                            $text = "<a href=\"{$href}\" target=\"_blank\" rel=\"noopener noreferrer\" class=\"text-orange-500 hover:text-orange-400 underline\">{$text}</a>";
                            break;
                    }
                }
                $html .= $text;
                break;

            case 'hardBreak':
                $html .= '<br>';
                break;
        }

        return $html;
        }
    }
@endphp

{!! renderTiptapNode($content) !!}

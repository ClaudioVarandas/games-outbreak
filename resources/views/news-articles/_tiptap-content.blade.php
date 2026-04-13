@php
    if (!function_exists('renderTiptapNode')) {
        function renderTiptapNode($node) {
            if (!is_array($node)) return '';

            $html  = '';
            $type    = $node['type']    ?? '';
            $content = $node['content'] ?? [];
            $attrs   = $node['attrs']   ?? [];

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
                    if ($innerHtml === '') break; // skip blank paragraphs
                    $html .= '<p class="mb-6 text-[1.025rem] leading-[1.875] text-slate-300">'
                           . $innerHtml
                           . '</p>';
                    break;

                case 'heading':
                    $level = min((int) ($attrs['level'] ?? 2), 6);
                    $innerHtml = '';
                    foreach ($content as $child) {
                        $innerHtml .= renderTiptapNode($child);
                    }
                    $classes = match ($level) {
                        1 => 'mt-12 mb-4 text-2xl font-bold leading-tight tracking-tight text-slate-50 border-b border-white/10 pb-3',
                        2 => 'mt-10 mb-4 text-xl  font-bold leading-snug  tracking-tight text-slate-100 border-b border-white/[0.07] pb-2',
                        3 => 'mt-8  mb-3 text-lg   font-semibold leading-snug text-slate-200',
                        default => 'mt-6 mb-2 text-base font-semibold text-slate-200',
                    };
                    $html .= "<h{$level} class=\"{$classes}\">{$innerHtml}</h{$level}>";
                    break;

                case 'bulletList':
                    $innerHtml = '';
                    foreach ($content as $child) {
                        $innerHtml .= renderTiptapNode($child);
                    }
                    $html .= '<ul class="mb-6 ml-1 space-y-2 list-none">' . $innerHtml . '</ul>';
                    break;

                case 'orderedList':
                    $innerHtml = '';
                    foreach ($content as $child) {
                        $innerHtml .= renderTiptapNode($child);
                    }
                    $html .= '<ol class="mb-6 ml-1 space-y-2 list-decimal list-inside marker:text-orange-500/70 marker:font-semibold">' . $innerHtml . '</ol>';
                    break;

                case 'listItem':
                    $innerHtml = '';
                    foreach ($content as $child) {
                        $innerHtml .= renderTiptapNode($child);
                    }
                    // Strip the wrapping <p> tags inside list items for tighter layout
                    $innerHtml = preg_replace('/<p[^>]*>(.*?)<\/p>/su', '$1', $innerHtml);
                    $html .= '<li class="flex gap-2.5 items-baseline text-[1.0rem] leading-relaxed text-slate-300">'
                           . '<span class="mt-[0.45rem] h-1.5 w-1.5 shrink-0 rounded-full bg-orange-500/70"></span>'
                           . '<span>' . $innerHtml . '</span>'
                           . '</li>';
                    break;

                case 'blockquote':
                    $innerHtml = '';
                    foreach ($content as $child) {
                        $innerHtml .= renderTiptapNode($child);
                    }
                    $html .= '<blockquote class="my-8 border-l-[3px] border-orange-500/60 pl-5 italic text-slate-400 text-[1.0rem] leading-relaxed">'
                           . $innerHtml
                           . '</blockquote>';
                    break;

                case 'image':
                    $src = e($attrs['src'] ?? '');
                    $alt = e($attrs['alt'] ?? '');
                    $html .= "<img src=\"{$src}\" alt=\"{$alt}\" class=\"my-8 w-full rounded-[1rem] shadow-lg\" loading=\"lazy\">";
                    break;

                case 'text':
                    $raw   = $node['text'] ?? '';
                    $marks = $node['marks'] ?? [];

                    // Fallback: text node still contains raw inline markdown (legacy stored data)
                    if (empty($marks) && (str_contains($raw, '**') || (str_contains($raw, '*') && !str_contains($raw, ' * ')))) {
                        $escaped = e($raw);
                        $escaped = preg_replace('/\*\*\*(.+?)\*\*\*/su', '<strong><em>$1</em></strong>', $escaped);
                        $escaped = preg_replace('/\*\*(.+?)\*\*/su',     '<strong>$1</strong>',           $escaped);
                        $escaped = preg_replace('/\*(.+?)\*/su',         '<em>$1</em>',                   $escaped);
                        $escaped = preg_replace('/(?<![a-zA-Z])_(.+?)_(?![a-zA-Z])/su', '<em>$1</em>',   $escaped);
                        $html .= $escaped;
                        break;
                    }

                    $text = e($raw);

                    foreach ($marks as $mark) {
                        switch ($mark['type'] ?? '') {
                            case 'bold':
                                $text = '<strong class="font-semibold text-slate-100">' . $text . '</strong>';
                                break;
                            case 'italic':
                                $text = '<em>' . $text . '</em>';
                                break;
                            case 'strike':
                                $text = '<s class="text-slate-500">' . $text . '</s>';
                                break;
                            case 'link':
                                $href = e($mark['attrs']['href'] ?? '#');
                                $text = "<a href=\"{$href}\" target=\"_blank\" rel=\"noopener noreferrer\""
                                      . " class=\"text-orange-400 hover:text-orange-300 underline underline-offset-2 transition-colors\">"
                                      . $text . '</a>';
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

<div class="article-body">
    {!! renderTiptapNode($content) !!}
</div>
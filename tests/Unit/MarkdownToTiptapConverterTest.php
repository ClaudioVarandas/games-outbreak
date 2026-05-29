<?php

use App\Support\News\MarkdownToTiptapConverter;

beforeEach(function () {
    $this->converter = new MarkdownToTiptapConverter;
});

it('converts a markdown link into a text node with a link mark', function () {
    $doc = $this->converter->convert('Source: [Phoronix](https://www.phoronix.com/news/Linux-7-0-Released)');

    $nodes = $doc['content'][0]['content'];

    expect($nodes[0])->toBe(['type' => 'text', 'text' => 'Source: '])
        ->and($nodes[1])->toBe([
            'type' => 'text',
            'text' => 'Phoronix',
            'marks' => [['type' => 'link', 'attrs' => ['href' => 'https://www.phoronix.com/news/Linux-7-0-Released']]],
        ]);
});

it('converts multiple links within the same paragraph', function () {
    $doc = $this->converter->convert('Source: [Phoronix](https://a.test), [Kernel Newbies](https://b.test)');

    $nodes = $doc['content'][0]['content'];

    $linkNodes = array_values(array_filter($nodes, fn ($n) => isset($n['marks'][0]['type']) && $n['marks'][0]['type'] === 'link'));

    expect($linkNodes)->toHaveCount(2)
        ->and($linkNodes[0]['text'])->toBe('Phoronix')
        ->and($linkNodes[0]['marks'][0]['attrs']['href'])->toBe('https://a.test')
        ->and($linkNodes[1]['text'])->toBe('Kernel Newbies')
        ->and($linkNodes[1]['marks'][0]['attrs']['href'])->toBe('https://b.test');
});

it('keeps bold and italic parsing intact alongside links', function () {
    $doc = $this->converter->convert('A **bold** and a [link](https://x.test) together');

    $nodes = $doc['content'][0]['content'];

    expect(collect($nodes)->firstWhere('text', 'bold')['marks'][0]['type'])->toBe('bold')
        ->and(collect($nodes)->firstWhere('text', 'link')['marks'][0]['type'])->toBe('link');
});

it('leaves plain text without links untouched', function () {
    $doc = $this->converter->convert('Just a plain sentence.');

    expect($doc['content'][0]['content'])->toBe([['type' => 'text', 'text' => 'Just a plain sentence.']]);
});

it('exposes parseInline for reuse on raw inline strings', function () {
    $nodes = $this->converter->parseInline('Read [more](https://m.test) here');

    expect($nodes[1])->toBe([
        'type' => 'text',
        'text' => 'more',
        'marks' => [['type' => 'link', 'attrs' => ['href' => 'https://m.test']]],
    ]);
});

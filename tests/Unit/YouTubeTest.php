<?php

use App\Support\YouTube;

it('extracts the video id from youtube urls', function (?string $url, ?string $expected) {
    expect(YouTube::idFromUrl($url))->toBe($expected);
})->with([
    'watch url' => ['https://www.youtube.com/watch?v=dQw4w9WgXcQ', 'dQw4w9WgXcQ'],
    'watch url with extra params' => ['https://www.youtube.com/watch?v=dQw4w9WgXcQ&t=42s', 'dQw4w9WgXcQ'],
    'short url' => ['https://youtu.be/dQw4w9WgXcQ', 'dQw4w9WgXcQ'],
    'twitch url' => ['https://www.twitch.tv/somechannel', null],
    'random string' => ['not a url', null],
    'empty string' => ['', null],
    'null' => [null, null],
]);

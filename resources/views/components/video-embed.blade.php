@props(['url' => null, 'class' => ''])

@php
    $embedUrl = null;
    $platform = null;

    if ($url) {
        // YouTube: https://www.youtube.com/watch?v=VIDEO_ID or https://youtu.be/VIDEO_ID
        if (preg_match('/(?:youtube\.com\/watch\?v=|youtu\.be\/)([a-zA-Z0-9_-]+)/', $url, $matches)) {
            $embedUrl = 'https://www.youtube.com/embed/' . $matches[1] . '?rel=0&modestbranding=1';
            $platform = 'youtube';
        }
        // Twitch VOD: https://www.twitch.tv/videos/VIDEO_ID
        elseif (preg_match('/twitch\.tv\/videos\/(\d+)/', $url, $matches)) {
            $parent = parse_url(config('app.url'), PHP_URL_HOST);
            $embedUrl = 'https://player.twitch.tv/?video=' . $matches[1] . '&parent=' . $parent;
            $platform = 'twitch';
        }
        // Twitch Channel: https://www.twitch.tv/CHANNEL
        elseif (preg_match('/twitch\.tv\/([a-zA-Z0-9_]+)/', $url, $matches)) {
            $parent = parse_url(config('app.url'), PHP_URL_HOST);
            $embedUrl = 'https://player.twitch.tv/?channel=' . $matches[1] . '&parent=' . $parent;
            $platform = 'twitch';
        }
    }
@endphp

@if($embedUrl)
    <div {{ $attributes->merge(['class' => 'aspect-video w-full rounded-xl overflow-hidden shadow-2xl ' . $class]) }}>
        <iframe
            src="{{ $embedUrl }}"
            class="w-full h-full"
            frameborder="0"
            allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture"
            allowfullscreen
            loading="lazy"
            title="{{ $platform === 'youtube' ? 'YouTube video player' : 'Twitch video player' }}"
        ></iframe>
    </div>
@endif

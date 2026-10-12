{!! '<?xml version="1.0" encoding="UTF-8"?>' !!}
<sitemapindex xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">
@foreach ($entries as $entry)
    <sitemap>
        <loc>{{ $entry['loc'] }}</loc>
@if ($entry['lastmod'] ?? null)
        <lastmod>{{ $entry['lastmod'] }}</lastmod>
@endif
    </sitemap>
@endforeach
</sitemapindex>

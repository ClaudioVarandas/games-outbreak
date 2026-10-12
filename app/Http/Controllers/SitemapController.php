<?php

namespace App\Http\Controllers;

use App\Services\SitemapService;
use Illuminate\Http\Response;

class SitemapController extends Controller
{
    private const CACHE_SECONDS = 3600;

    public function __construct(private readonly SitemapService $sitemaps) {}

    public function index(): Response
    {
        return $this->xml('sitemaps.index', 'sitemap:index', fn (): array => $this->sitemaps->index());
    }

    public function pages(): Response
    {
        return $this->xml('sitemaps.urlset', 'sitemap:pages', fn (): array => $this->sitemaps->pages());
    }

    public function lists(): Response
    {
        return $this->xml('sitemaps.urlset', 'sitemap:lists', fn (): array => $this->sitemaps->lists());
    }

    public function news(): Response
    {
        return $this->xml('sitemaps.urlset', 'sitemap:news', fn (): array => $this->sitemaps->news());
    }

    public function games(int $page): Response
    {
        abort_if($page < 1 || $page > max(1, $this->sitemaps->gamesSitemapCount()), 404);

        return $this->xml('sitemaps.urlset', "sitemap:games:{$page}", fn (): array => $this->sitemaps->games($page));
    }

    /**
     * @param  callable(): array<int, array<string, mixed>>  $entries
     */
    private function xml(string $view, string $cacheKey, callable $entries): Response
    {
        $body = cache()->remember($cacheKey, self::CACHE_SECONDS, fn (): string => view($view, ['entries' => $entries()])->render());

        return response($body, 200, ['Content-Type' => 'application/xml; charset=UTF-8']);
    }
}

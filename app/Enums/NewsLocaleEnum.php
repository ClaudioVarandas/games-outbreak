<?php

declare(strict_types=1);

namespace App\Enums;

use App\Models\NewsArticle;

enum NewsLocaleEnum: string
{
    case En = 'en';
    case PtPt = 'pt-PT';
    case PtBr = 'pt-BR';

    public function label(): string
    {
        return match ($this) {
            self::En => 'English',
            self::PtPt => 'Português (Portugal)',
            self::PtBr => 'Português (Brasil)',
        };
    }

    public function shortLabel(): string
    {
        return match ($this) {
            self::En => 'EN',
            self::PtPt => 'PT',
            self::PtBr => 'BR',
        };
    }

    /** URL-safe locale prefix (e.g. 'en', 'pt-pt', 'pt-br'). */
    public function slugPrefix(): string
    {
        return match ($this) {
            self::En => 'en',
            self::PtPt => 'pt-pt',
            self::PtBr => 'pt-br',
        };
    }

    /** Path segment used in URLs for this locale ('news' vs 'noticias'). */
    public function pathSegment(): string
    {
        return match ($this) {
            self::En => 'news',
            self::PtPt, self::PtBr => 'noticias',
        };
    }

    /** Database column storing the slug for this locale. */
    public function slugColumn(): string
    {
        return match ($this) {
            self::En => 'slug_en',
            self::PtPt => 'slug_pt_pt',
            self::PtBr => 'slug_pt_br',
        };
    }

    /** Named route for the news listing page of this locale. */
    public function indexRouteName(): string
    {
        return match ($this) {
            self::En => 'news-articles.en.index',
            self::PtPt, self::PtBr => 'news-articles.index',
        };
    }

    /** Named route for a news article detail page of this locale. */
    public function showRouteName(): string
    {
        return match ($this) {
            self::En => 'news-articles.en.show',
            self::PtPt, self::PtBr => 'news-articles.show',
        };
    }

    /** Generate the URL for the news listing page. */
    public function indexUrl(): string
    {
        return match ($this) {
            self::En => route('news-articles.en.index'),
            self::PtPt, self::PtBr => route('news-articles.index', $this->slugPrefix()),
        };
    }

    /** Generate the URL for a specific news article. */
    public function articleUrl(NewsArticle $article): string
    {
        $slug = $article->{$this->slugColumn()};

        return match ($this) {
            self::En => route('news-articles.en.show', $slug),
            self::PtPt, self::PtBr => route('news-articles.show', [$this->slugPrefix(), $slug]),
        };
    }

    /** Resolve from URL locale prefix (e.g. 'en', 'pt-pt', 'pt-br'). */
    public static function fromPrefix(string $prefix): self
    {
        return match ($prefix) {
            'en' => self::En,
            'pt-pt' => self::PtPt,
            'pt-br' => self::PtBr,
            default => abort(404),
        };
    }

    /** Resolve from app.locale config (used as default). */
    public static function fromAppLocale(): self
    {
        return match (config('app.locale')) {
            'pt-PT', 'pt_PT', 'pt-pt' => self::PtPt,
            'pt-BR', 'pt_BR', 'pt-br' => self::PtBr,
            default => self::En,
        };
    }

    /** Resolve from Accept-Language header value, falling back to app locale. */
    public static function fromBrowserLocale(?string $acceptLanguage): self
    {
        if (! $acceptLanguage) {
            return self::fromAppLocale();
        }

        $map = [
            'en' => self::En,
            'en-us' => self::En,
            'en-gb' => self::En,
            'pt' => self::PtPt,
            'pt-pt' => self::PtPt,
            'pt-br' => self::PtBr,
        ];

        foreach (array_map('trim', explode(',', $acceptLanguage)) as $tag) {
            $lang = strtolower(trim(explode(';', $tag)[0]));

            if (isset($map[$lang])) {
                return $map[$lang];
            }

            $base = explode('-', $lang)[0];

            if (isset($map[$base])) {
                return $map[$base];
            }
        }

        return self::fromAppLocale();
    }
}

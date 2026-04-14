<?php

use App\Enums\NewsLocaleEnum;

describe('NewsLocaleEnum::fromBrowserLocale()', function () {
    it('maps en-US to En', function () {
        expect(NewsLocaleEnum::fromBrowserLocale('en-US'))->toBe(NewsLocaleEnum::En);
    });

    it('maps en-GB to En', function () {
        expect(NewsLocaleEnum::fromBrowserLocale('en-GB,en;q=0.8'))->toBe(NewsLocaleEnum::En);
    });

    it('maps unknown en variant via base language to En', function () {
        expect(NewsLocaleEnum::fromBrowserLocale('en-AU,en;q=0.9'))->toBe(NewsLocaleEnum::En);
    });

    it('maps pt-PT to PtPt', function () {
        expect(NewsLocaleEnum::fromBrowserLocale('pt-PT'))->toBe(NewsLocaleEnum::PtPt);
    });

    it('maps generic pt to PtPt', function () {
        expect(NewsLocaleEnum::fromBrowserLocale('pt'))->toBe(NewsLocaleEnum::PtPt);
    });

    it('maps pt-BR to PtBr', function () {
        expect(NewsLocaleEnum::fromBrowserLocale('pt-BR'))->toBe(NewsLocaleEnum::PtBr);
    });

    it('respects order — first tag wins', function () {
        expect(NewsLocaleEnum::fromBrowserLocale('pt-BR,pt;q=0.9,en;q=0.8'))->toBe(NewsLocaleEnum::PtBr);
    });

    it('returns first matched tag even when lower-priority tags are known', function () {
        // en-AU base matches 'en' — maps to En
        expect(NewsLocaleEnum::fromBrowserLocale('en-AU,pt-br;q=0.5'))->toBe(NewsLocaleEnum::En);
    });
});

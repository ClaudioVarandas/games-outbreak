<?php

use App\Enums\GlobalFeatureEnum;
use App\Enums\NewsLocaleEnum;
use App\Enums\NewsVisibilityEnum;
use App\Http\Middleware\EnsureNewsFeatureEnabled;
use App\Models\NewsArticle;
use App\Models\NewsArticleLocalization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function publishHomepageArticle(): void
{
    $article = NewsArticle::factory()->published()->create();

    NewsArticleLocalization::factory()->for($article, 'article')->create([
        'locale' => NewsLocaleEnum::PtPt,
        'title' => 'The Big Feature',
    ]);
}

it('defaults to admin-only news with the homepage block on', function () {
    expect(NewsVisibilityEnum::current())->toBe(NewsVisibilityEnum::AdminOnly)
        ->and(GlobalFeatureEnum::HomepageNews->value())->toBeTrue();
});

it('prints the current state and changes nothing without options', function () {
    GlobalFeatureEnum::News->set(NewsVisibilityEnum::Public->value);

    $this->artisan('news:feature')
        ->expectsOutput('News visibility: public')
        ->expectsOutput('Homepage news block: on')
        ->assertSuccessful();

    expect(NewsVisibilityEnum::current())->toBe(NewsVisibilityEnum::Public);
});

it('changes each flag independently', function () {
    $this->artisan('news:feature', ['--visibility' => 'public'])->assertSuccessful();
    $this->artisan('news:feature', ['--homepage' => 'off'])->assertSuccessful();

    expect(NewsVisibilityEnum::current())->toBe(NewsVisibilityEnum::Public)
        ->and(GlobalFeatureEnum::HomepageNews->value())->toBeFalse();

    $this->artisan('news:feature', ['--homepage' => 'on'])->assertSuccessful();

    expect(GlobalFeatureEnum::HomepageNews->value())->toBeTrue();
});

it('rejects invalid values without changing either flag', function (array $options) {
    $this->artisan('news:feature', $options)->assertExitCode(2);

    expect(NewsVisibilityEnum::current())->toBe(NewsVisibilityEnum::AdminOnly)
        ->and(GlobalFeatureEnum::HomepageNews->value())->toBeTrue();
})->with([
    'unknown visibility' => [['--visibility' => 'true', '--homepage' => 'off']],
    'unknown homepage value' => [['--visibility' => 'public', '--homepage' => 'false']],
]);

it('applies visibility to guests, users and admins', function (NewsVisibilityEnum $visibility, bool $guest, bool $user, bool $admin) {
    GlobalFeatureEnum::News->set($visibility->value);

    expect(EnsureNewsFeatureEnabled::isVisibleTo(null))->toBe($guest)
        ->and(EnsureNewsFeatureEnabled::isVisibleTo(User::factory()->create()))->toBe($user)
        ->and(EnsureNewsFeatureEnabled::isVisibleTo(User::factory()->create(['is_admin' => true])))->toBe($admin);
})->with([
    'public' => [NewsVisibilityEnum::Public, true, true, true],
    'admin' => [NewsVisibilityEnum::AdminOnly, false, false, true],
    'disabled' => [NewsVisibilityEnum::Disabled, false, false, false],
]);

it('treats an unrecognised stored visibility as disabled', function () {
    GlobalFeatureEnum::News->set('true');

    expect(NewsVisibilityEnum::current())->toBe(NewsVisibilityEnum::Disabled);
});

it('hides only the homepage block when the homepage flag is off', function () {
    GlobalFeatureEnum::News->set(NewsVisibilityEnum::Public->value);
    GlobalFeatureEnum::HomepageNews->set(false);
    publishHomepageArticle();

    $this->withSession(['locale' => 'pt-pt'])->get(route('homepage'))
        ->assertSuccessful()
        ->assertDontSeeText('The Big Feature');

    $this->get(route('news-articles.index', 'pt-pt'))
        ->assertSuccessful()
        ->assertSeeText('The Big Feature');
});

it('keeps the homepage block hidden when news is not visible even if the homepage flag is on', function () {
    GlobalFeatureEnum::News->set(NewsVisibilityEnum::Disabled->value);
    publishHomepageArticle();

    $this->withSession(['locale' => 'pt-pt'])->get(route('homepage'))
        ->assertSuccessful()
        ->assertDontSeeText('The Big Feature');

    $this->get(route('news-articles.index', 'pt-pt'))->assertNotFound();
});

<?php

use App\Enums\NewsLocaleEnum;
use App\Models\Game;
use App\Models\GameList;
use App\Models\NewsArticle;
use App\Models\NewsArticleLocalization;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('renders the homepage sections with the neon homepage structure', function () {
    config(['features.news' => false]);

    $response = $this->get(route('homepage'));

    $response->assertSuccessful();
    $response->assertSeeTextInOrder([
        "This Week's Choices",
        'Events',
        'Upcoming Releases',
        'Latest Added Games',
    ]);
    $response->assertDontSeeText('Featured News');
});

it('renders both upcoming releases carousel controls', function () {
    config(['features.news' => false]);

    Game::factory()->create([
        'first_release_date' => now()->addDays(2),
    ]);

    $response = $this->get(route('homepage'));

    $response->assertSuccessful();
    $response->assertSee('aria-label="Previous upcoming releases"', false);
    $response->assertSee('aria-label="Next upcoming releases"', false);
});

it('shows the homepage hero when news is enabled and published news exists', function () {
    config(['features.news' => true]);

    $article = NewsArticle::factory()->published()->create([
        'featured_image_url' => 'https://example.com/image.jpg',
    ]);

    NewsArticleLocalization::factory()->for($article, 'article')->create([
        'locale' => NewsLocaleEnum::PtPt,
        'title' => 'The Big Feature',
        'summary_short' => 'Lead story summary.',
    ]);

    NewsArticle::factory()->published()->count(4)->create()->each(function (NewsArticle $a) {
        NewsArticleLocalization::factory()->for($a, 'article')->create(['locale' => NewsLocaleEnum::PtPt]);
    });

    $response = $this->withSession(['locale' => 'pt-pt'])->get(route('homepage'));

    $response->assertSuccessful();
    $response->assertSeeText('Notícias em Destaque');
    $response->assertSeeText('The Big Feature');
    $response->assertSeeText('Ver Todas as Notícias');
});

it('omits the homepage hero when news is enabled but no published articles exist', function () {
    config(['features.news' => true]);

    NewsArticle::factory()->count(2)->create();

    $response = $this->get(route('homepage'));

    $response->assertSuccessful();
    $response->assertDontSeeText('Featured News');
});

it('limits this weeks choices to ten games', function () {
    config(['features.news' => false]);

    $yearlyList = GameList::factory()->system()->yearly()->active()->create([
        'start_at' => now()->startOfYear(),
        'end_at' => now()->endOfYear(),
    ]);

    $releaseDate = now()->startOfWeek()->addDays(2)->toDateString();

    Game::factory()->count(12)->create()->each(function (Game $game) use ($yearlyList, $releaseDate) {
        $yearlyList->games()->attach($game->id, [
            'release_date' => $releaseDate,
        ]);
    });

    $response = $this->get(route('homepage'));

    $response->assertSuccessful();
    expect($response['thisWeekGames'])->toHaveCount(10);
});

it('keeps the latest added list capped at twelve games', function () {
    config(['features.news' => false]);

    Game::factory()->count(15)->create();

    $response = $this->get(route('homepage'));

    $response->assertSuccessful();
    expect($response['latestAddedGames'])->toHaveCount(12);
});

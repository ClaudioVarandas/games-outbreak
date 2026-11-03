<?php

declare(strict_types=1);

namespace App\Console\Commands\News;

use App\Enums\GlobalFeatureEnum;
use App\Enums\NewsVisibilityEnum;
use Illuminate\Console\Command;

class NewsFeatureCommand extends Command
{
    protected $signature = 'news:feature
        {--visibility= : Who can see news — public|admin|disabled}
        {--homepage= : Show the news block on the homepage — on|off}';

    protected $description = 'Show or change the news feature flags. Without options, prints the current state.';

    public function handle(): int
    {
        $visibilityOption = $this->option('visibility');
        $homepageOption = $this->option('homepage');

        $visibility = $visibilityOption === null ? null : NewsVisibilityEnum::tryFrom($visibilityOption);

        if ($visibilityOption !== null && $visibility === null) {
            $this->error("Unknown visibility '{$visibilityOption}'. Allowed: public, admin, disabled.");

            return self::INVALID;
        }

        if ($homepageOption !== null && ! in_array($homepageOption, ['on', 'off'], true)) {
            $this->error("Unknown homepage value '{$homepageOption}'. Allowed: on, off.");

            return self::INVALID;
        }

        if ($visibility !== null) {
            GlobalFeatureEnum::News->set($visibility->value);
        }

        if ($homepageOption !== null) {
            GlobalFeatureEnum::HomepageNews->set($homepageOption === 'on');
        }

        $this->line('News visibility: '.NewsVisibilityEnum::current()->value);
        $this->line('Homepage news block: '.(GlobalFeatureEnum::HomepageNews->value() ? 'on' : 'off'));

        return self::SUCCESS;
    }
}

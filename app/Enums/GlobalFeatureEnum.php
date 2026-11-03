<?php

namespace App\Enums;

use Laravel\Pennant\Feature;

enum GlobalFeatureEnum: string
{
    case News = 'news';
    case HomepageNews = 'homepage_news';

    private const SCOPE = 'global';

    public function defaultValue(): string|bool
    {
        return match ($this) {
            self::News => NewsVisibilityEnum::AdminOnly->value,
            self::HomepageNews => true,
        };
    }

    public function value(): mixed
    {
        return Feature::for(self::SCOPE)->value($this->value);
    }

    public function set(string|bool $value): void
    {
        $value === false
            ? Feature::for(self::SCOPE)->deactivate($this->value)
            : Feature::for(self::SCOPE)->activate($this->value, $value);
    }
}

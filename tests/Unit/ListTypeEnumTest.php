<?php

namespace Tests\Unit;

use App\Enums\ListTypeEnum;
use PHPUnit\Framework\TestCase;

class ListTypeEnumTest extends TestCase
{
    public function test_indie_games_label_returns_correct_value(): void
    {
        $this->assertEquals('Indie Games', ListTypeEnum::INDIE_GAMES->label());
    }

    public function test_indie_games_is_not_unique_per_user(): void
    {
        $this->assertFalse(ListTypeEnum::INDIE_GAMES->isUniquePerUser());
    }

    public function test_indie_games_is_system_list_type(): void
    {
        $this->assertTrue(ListTypeEnum::INDIE_GAMES->isSystemListType());
    }

    public function test_from_value_returns_indie_games(): void
    {
        $result = ListTypeEnum::fromValue('indie-games');

        $this->assertNotNull($result);
        $this->assertEquals(ListTypeEnum::INDIE_GAMES, $result);
    }

    public function test_indie_games_value_is_correct(): void
    {
        $this->assertEquals('indie-games', ListTypeEnum::INDIE_GAMES->value);
    }

    public function test_all_system_list_types_return_true(): void
    {
        $systemTypes = [
            ListTypeEnum::MONTHLY,
            ListTypeEnum::SEASONED,
            ListTypeEnum::INDIE_GAMES,
        ];

        foreach ($systemTypes as $type) {
            $this->assertTrue(
                $type->isSystemListType(),
                "Expected {$type->value} to be a system list type"
            );
        }
    }

    public function test_non_system_list_types_return_false(): void
    {
        $nonSystemTypes = [
            ListTypeEnum::REGULAR,
            ListTypeEnum::BACKLOG,
            ListTypeEnum::WISHLIST,
        ];

        foreach ($nonSystemTypes as $type) {
            $this->assertFalse(
                $type->isSystemListType(),
                "Expected {$type->value} not to be a system list type"
            );
        }
    }
}

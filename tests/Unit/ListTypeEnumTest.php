<?php

namespace Tests\Unit;

use App\Enums\ListTypeEnum;
use PHPUnit\Framework\TestCase;

class ListTypeEnumTest extends TestCase
{
    public function test_yearly_label_returns_correct_value(): void
    {
        $this->assertEquals('Yearly', ListTypeEnum::YEARLY->label());
    }

    public function test_yearly_is_not_unique_per_user(): void
    {
        $this->assertFalse(ListTypeEnum::YEARLY->isUniquePerUser());
    }

    public function test_yearly_is_system_list_type(): void
    {
        $this->assertTrue(ListTypeEnum::YEARLY->isSystemListType());
    }

    public function test_from_value_returns_yearly(): void
    {
        $result = ListTypeEnum::fromValue('yearly');

        $this->assertNotNull($result);
        $this->assertEquals(ListTypeEnum::YEARLY, $result);
    }

    public function test_yearly_value_is_correct(): void
    {
        $this->assertEquals('yearly', ListTypeEnum::YEARLY->value);
    }

    public function test_all_system_list_types_return_true(): void
    {
        $systemTypes = [
            ListTypeEnum::YEARLY,
            ListTypeEnum::SEASONED,
            ListTypeEnum::EVENTS,
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

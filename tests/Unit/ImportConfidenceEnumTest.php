<?php

namespace Tests\Unit;

use App\Enums\ImportConfidenceEnum;
use PHPUnit\Framework\TestCase;

class ImportConfidenceEnumTest extends TestCase
{
    public function test_values_round_trip(): void
    {
        foreach (['high', 'medium', 'low'] as $value) {
            $case = ImportConfidenceEnum::from($value);
            $this->assertEquals($value, $case->value);
        }
    }

    public function test_every_case_has_label_and_badge_class(): void
    {
        foreach (ImportConfidenceEnum::cases() as $case) {
            $this->assertNotEmpty($case->label());
            $this->assertNotEmpty($case->badgeClass());
        }
    }

    public function test_unknown_value_returns_null(): void
    {
        $this->assertNull(ImportConfidenceEnum::tryFrom('bogus'));
    }
}

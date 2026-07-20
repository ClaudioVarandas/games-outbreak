<?php

namespace Tests\Unit;

use App\Enums\ImportSourceEnum;
use PHPUnit\Framework\TestCase;

class ImportSourceEnumTest extends TestCase
{
    public function test_every_case_has_label_and_badge_class(): void
    {
        foreach (ImportSourceEnum::cases() as $case) {
            $this->assertNotEmpty($case->label());
            $this->assertNotEmpty($case->badgeClass());
        }
    }

    public function test_known_source_helpers_resolve_case_values(): void
    {
        $this->assertEquals('IGDB', ImportSourceEnum::labelFor('igdb'));
        $this->assertEquals(ImportSourceEnum::Steam->badgeClass(), ImportSourceEnum::badgeClassFor('steam'));
    }

    public function test_unknown_source_falls_back_gracefully(): void
    {
        $this->assertEquals('Metacritic', ImportSourceEnum::labelFor('metacritic'));
        $this->assertStringContainsString('bg-gray-100', ImportSourceEnum::badgeClassFor('metacritic'));
    }
}

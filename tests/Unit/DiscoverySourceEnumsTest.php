<?php

namespace Tests\Unit;

use App\Enums\DiscoverySourceKindEnum;
use App\Enums\DiscoverySourceOriginEnum;
use App\Enums\DiscoverySourcePurposeEnum;
use App\Enums\DiscoverySourceStatusEnum;
use PHPUnit\Framework\TestCase;

class DiscoverySourceEnumsTest extends TestCase
{
    public function test_every_case_has_label_and_badge_class(): void
    {
        $enums = [
            DiscoverySourceKindEnum::cases(),
            DiscoverySourcePurposeEnum::cases(),
            DiscoverySourceStatusEnum::cases(),
            DiscoverySourceOriginEnum::cases(),
        ];

        foreach ($enums as $cases) {
            foreach ($cases as $case) {
                $this->assertNotEmpty($case->label());
                $this->assertNotEmpty($case->badgeClass());
            }
        }
    }

    public function test_purpose_coverage_helpers(): void
    {
        $this->assertTrue(DiscoverySourcePurposeEnum::News->coversNews());
        $this->assertFalse(DiscoverySourcePurposeEnum::News->coversReleases());
        $this->assertTrue(DiscoverySourcePurposeEnum::Releases->coversReleases());
        $this->assertFalse(DiscoverySourcePurposeEnum::Releases->coversNews());
        $this->assertTrue(DiscoverySourcePurposeEnum::Both->coversNews());
        $this->assertTrue(DiscoverySourcePurposeEnum::Both->coversReleases());
    }

    public function test_only_active_status_is_usable(): void
    {
        foreach (DiscoverySourceStatusEnum::cases() as $status) {
            $this->assertSame($status === DiscoverySourceStatusEnum::Active, $status->isUsable());
        }
    }
}

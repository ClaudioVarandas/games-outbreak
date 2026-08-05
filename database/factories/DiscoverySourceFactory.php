<?php

namespace Database\Factories;

use App\Enums\DiscoverySourceKindEnum;
use App\Enums\DiscoverySourceOriginEnum;
use App\Enums\DiscoverySourcePurposeEnum;
use App\Enums\DiscoverySourceStatusEnum;
use App\Models\DiscoverySource;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DiscoverySource>
 */
class DiscoverySourceFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $domain = $this->faker->unique()->domainName();

        return [
            'name' => $this->faker->company(),
            'kind' => DiscoverySourceKindEnum::Press,
            'purpose' => DiscoverySourcePurposeEnum::Releases,
            'status' => DiscoverySourceStatusEnum::Active,
            'origin' => DiscoverySourceOriginEnum::Admin,
            'url' => 'https://'.$domain,
            'locator' => $domain,
        ];
    }

    public function pending(): static
    {
        return $this->state(fn (): array => [
            'status' => DiscoverySourceStatusEnum::Pending,
        ]);
    }

    public function agentProposed(): static
    {
        return $this->state(fn (): array => [
            'status' => DiscoverySourceStatusEnum::Pending,
            'origin' => DiscoverySourceOriginEnum::Agent,
            'confidence' => 'high',
            'evidence' => 'Covered three releases the registry missed last run.',
        ]);
    }

    public function dead(): static
    {
        return $this->state(fn (): array => [
            'runs_count' => 4,
            'items_found_total' => 0,
            'consecutive_failures' => 3,
        ]);
    }
}

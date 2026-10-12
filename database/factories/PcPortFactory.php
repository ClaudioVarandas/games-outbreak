<?php

namespace Database\Factories;

use App\Enums\PcPortCategoryEnum;
use App\Enums\PcPortStatusEnum;
use App\Enums\PcPortTechniqueEnum;
use App\Models\PcPort;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PcPort>
 */
class PcPortFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $title = $this->faker->unique()->words(3, true);

        return [
            'slug' => str($title)->slug()->toString(),
            'game_title' => str($title)->title()->toString(),
            'original_platform' => $this->faker->randomElement(['Xbox 360', 'PlayStation 3', 'Nintendo 64', 'PlayStation 2']),
            'project_name' => str($title)->title()->append(' Recompiled')->toString(),
            'technique' => PcPortTechniqueEnum::StaticRecompilation->value,
            'category' => PcPortCategoryEnum::PcPort,
            'status' => PcPortStatusEnum::Active,
            'windows_support' => 'true',
            'linux_support' => 'false',
            'latest_version' => 'v'.$this->faker->semver(),
            'last_activity' => $this->faker->dateTimeBetween('-6 months'),
            'last_checked_at' => now(),
            'added_at' => now(),
            'notes' => $this->faker->sentence(),
            'repo_url' => 'https://github.com/'.$this->faker->userName().'/'.str($title)->slug(),
        ];
    }

    public function radar(): static
    {
        return $this->state(fn (): array => [
            'category' => PcPortCategoryEnum::Radar,
            'technique' => PcPortTechniqueEnum::Decompilation->value,
            'status' => PcPortStatusEnum::Discovered,
        ]);
    }

    public function mod(): static
    {
        return $this->state(fn (): array => [
            'category' => PcPortCategoryEnum::Mod,
            'technique' => PcPortTechniqueEnum::TexturePack->value,
        ]);
    }

    public function tool(): static
    {
        return $this->state(fn (): array => [
            'original_platform' => null,
            'technique' => PcPortTechniqueEnum::LauncherTool->value,
        ]);
    }

    public function released(): static
    {
        return $this->state(fn (): array => [
            'status' => PcPortStatusEnum::Released,
        ]);
    }
}

<?php

namespace Database\Seeders;

use App\Enums\DiscoverySourceKindEnum;
use App\Enums\DiscoverySourceOriginEnum;
use App\Enums\DiscoverySourcePurposeEnum;
use App\Enums\DiscoverySourceStatusEnum;
use App\Models\DiscoverySource;
use Illuminate\Database\Seeder;

class DiscoverySourceSeeder extends Seeder
{
    public function run(): void
    {
        foreach ($this->sources() as $source) {
            DiscoverySource::updateOrCreate(
                ['locator' => $source['locator']],
                $source + [
                    'status' => DiscoverySourceStatusEnum::Active,
                    'origin' => DiscoverySourceOriginEnum::Seed,
                ]
            );
        }
    }

    /**
     * @return list<array{name: string, kind: DiscoverySourceKindEnum, purpose: DiscoverySourcePurposeEnum, url: string|null, locator: string}>
     */
    private function sources(): array
    {
        $calendars = [
            ['Gematsu releases', 'https://www.gematsu.com/tag/release-dates'],
            ['IGN release calendar', 'https://www.ign.com/upcoming/games'],
            ['GamesRadar schedule', 'https://www.gamesradar.com/video-game-release-dates/'],
            ['Push Square releases', 'https://www.pushsquare.com/games/browse?releases=upcoming'],
            ['Nintendo Life releases', 'https://www.nintendolife.com/games/browse?releases=upcoming'],
            ['Pure Xbox releases', 'https://www.purexbox.com/games/browse?releases=upcoming'],
            ['Steam upcoming', 'https://store.steampowered.com/explore/upcoming/'],
        ];

        $press = [
            ['IGN', 'ign.com'],
            ['GameSpot', 'gamespot.com'],
            ['PC Gamer', 'pcgamer.com'],
            ['Eurogamer', 'eurogamer.net'],
            ['Polygon', 'polygon.com'],
            ['Rock Paper Shotgun', 'rockpapershotgun.com'],
            ['Destructoid', 'destructoid.com'],
        ];

        $youtubeChannels = [
            ['gameranx', '@gameranxTV'],
            ['IGN', '@IGN'],
            ['GameSpot Trailers', '@gamespottrailers'],
            ['PlayStation', '@PlayStation'],
            ['Nintendo', '@NintendoAmerica'],
            ['Xbox', '@Xbox'],
            ['Best Indie Games', '@BestIndieGames'],
        ];

        $xAccounts = [
            ['Wario64', 'Wario64'],
            ['Gematsu', 'gematsu'],
            ['Shinobi602', 'shinobi602'],
            ['IdleSloth84', 'IdleSloth84'],
        ];

        $sources = [];

        foreach ($calendars as [$name, $url]) {
            $sources[] = [
                'name' => $name,
                'kind' => DiscoverySourceKindEnum::Calendar,
                'purpose' => DiscoverySourcePurposeEnum::Releases,
                'url' => $url,
                'locator' => $url,
            ];
        }

        foreach ($press as [$name, $domain]) {
            $sources[] = [
                'name' => $name,
                'kind' => DiscoverySourceKindEnum::Press,
                'purpose' => DiscoverySourcePurposeEnum::Both,
                'url' => 'https://www.'.$domain,
                'locator' => $domain,
            ];
        }

        foreach ($youtubeChannels as [$name, $handle]) {
            $sources[] = [
                'name' => $name,
                'kind' => DiscoverySourceKindEnum::Youtube,
                'purpose' => DiscoverySourcePurposeEnum::Releases,
                'url' => 'https://www.youtube.com/'.$handle,
                'locator' => mb_strtolower($handle),
            ];
        }

        foreach ($xAccounts as [$name, $handle]) {
            $sources[] = [
                'name' => $name,
                'kind' => DiscoverySourceKindEnum::X,
                'purpose' => DiscoverySourcePurposeEnum::Releases,
                'url' => 'https://x.com/'.$handle,
                'locator' => '@'.mb_strtolower($handle),
            ];
        }

        return $sources;
    }
}

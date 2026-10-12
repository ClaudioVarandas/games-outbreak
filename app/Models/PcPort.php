<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\PcPortCategoryEnum;
use App\Enums\PcPortSectionEnum;
use App\Enums\PcPortStatusEnum;
use App\Enums\PcPortSupportStateEnum;
use App\Enums\PcPortTechniqueEnum;
use Carbon\CarbonInterface;
use Database\Factories\PcPortFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PcPort extends Model
{
    /** @use HasFactory<PcPortFactory> */
    use HasFactory;

    /**
     * Long-tail CSV columns that are only shown in the detail modal; stored together in `details`.
     */
    public const DETAIL_KEYS = [
        'playability',
        'boot_milestone',
        'next_version',
        'next_version_status',
        'renderer_backend',
        'fps_options',
        'save_support',
        'keyboard_mouse',
        'controller',
        'mod_support',
        'local_achievements',
        'achievements_count',
        'launcher',
        'requires_original_game',
        'required_version',
        'features',
        'ppu_recompiled',
        'spu_recompiled',
        'vulkan_support',
        'public_release',
    ];

    public const OPERATING_SYSTEMS = [
        'windows' => 'Windows',
        'linux' => 'Linux',
        'macos' => 'macOS',
    ];

    protected $fillable = [
        'slug',
        'game_title',
        'original_platform',
        'project_name',
        'previous_project_name',
        'framework',
        'technique',
        'category',
        'status',
        'windows_support',
        'linux_support',
        'macos_support',
        'latest_version',
        'latest_public_version',
        'last_activity',
        'last_checked_at',
        'added_at',
        'notes',
        'repo_url',
        'homepage_url',
        'primary_source',
        'framework_repo_url',
        'cover_source_url',
        'cover_path',
        'details',
    ];

    protected function casts(): array
    {
        return [
            'category' => PcPortCategoryEnum::class,
            'status' => PcPortStatusEnum::class,
            'last_activity' => 'date',
            'last_checked_at' => 'date',
            'added_at' => 'date',
            'details' => 'array',
        ];
    }

    /**
     * Covers on the ports page come only from SteamGridDB.
     */
    public static function isSteamGridDbUrl(string $url): bool
    {
        $host = strtolower((string) parse_url($url, PHP_URL_HOST));

        return $host === 'steamgriddb.com' || str_ends_with($host, '.steamgriddb.com');
    }

    public function section(): PcPortSectionEnum
    {
        $platform = strtolower(trim((string) $this->original_platform));

        if ($platform === '' || in_array($platform, ['multi', 'various'], true) || $this->technique === PcPortTechniqueEnum::LauncherTool->value) {
            return PcPortSectionEnum::Tools;
        }

        return PcPortSectionEnum::fromCategory($this->category);
    }

    /**
     * @return list<string>
     */
    public function platforms(): array
    {
        return collect(explode(',', (string) $this->original_platform))
            ->map(fn (string $platform): string => trim((string) preg_replace('/\s*\/\s*PS1$/', '', $platform)))
            ->filter()
            ->values()
            ->all();
    }

    public function displayVersion(): ?string
    {
        return $this->latest_public_version ?: $this->latest_version;
    }

    public function activityDate(): ?CarbonInterface
    {
        return $this->last_activity ?? $this->last_checked_at ?? $this->added_at;
    }

    public function techniqueLabel(): ?string
    {
        return PcPortTechniqueEnum::tryFrom((string) $this->technique)?->label() ?? $this->technique;
    }

    public function detail(string $key): ?string
    {
        $value = $this->details[$key] ?? null;

        return $value === null || $value === '' ? null : (string) $value;
    }

    public function coverUrl(): ?string
    {
        return $this->cover_path ? asset('storage/'.$this->cover_path) : null;
    }

    /**
     * @return list<array{label: string, state: string, stateLabel: string, classes: string}>
     */
    public function operatingSystemSupport(): array
    {
        return collect(self::OPERATING_SYSTEMS)
            ->map(function (string $label, string $key): array {
                $state = PcPortSupportStateEnum::fromCsvValue($this->{$key.'_support'});

                return [
                    'label' => $label,
                    'state' => $state->value,
                    'stateLabel' => __($state->label()),
                    'classes' => $state->pillClasses(),
                ];
            })
            ->values()
            ->all();
    }

    /**
     * Label/value pairs for the detail modal, skipping empty ones.
     *
     * @return list<array{label: string, value: string}>
     */
    public function detailRows(): array
    {
        $yesNo = fn (?string $value): ?string => match ($value) {
            'true' => __('Yes'),
            'false' => __('No'),
            default => $value,
        };

        $nextVersion = $this->detail('next_version');
        if ($nextVersion && $this->detail('next_version_status')) {
            $nextVersion .= ' ('.str_replace('_', ' ', $this->detail('next_version_status')).')';
        }

        $requires = $this->detail('required_version')
            ?? ($this->detail('requires_original_game') === 'true' ? __('Original copy of the game') : null);

        $rows = [
            'Project' => $this->project_name,
            'Formerly' => $this->previous_project_name,
            'Platform' => $this->original_platform,
            'Technique' => $this->techniqueLabel(),
            'Framework' => $this->framework,
            'Status' => __($this->status->label()),
            'Playability' => $this->detail('playability'),
            'Milestone' => $this->detail('boot_milestone'),
            'Version' => $this->displayVersion(),
            'Next' => $nextVersion,
            'Renderer' => $this->detail('renderer_backend'),
            'FPS' => $this->detail('fps_options'),
            'Saves' => $yesNo($this->detail('save_support')),
            'Keyboard/mouse' => $yesNo($this->detail('keyboard_mouse')),
            'Controller' => $yesNo($this->detail('controller')),
            'Mods' => $yesNo($this->detail('mod_support')),
            'Achievements' => $this->detail('achievements_count') ?? $yesNo($this->detail('local_achievements')),
            'Launcher' => $yesNo($this->detail('launcher')),
            'Requires' => $requires,
            'Extras' => $this->detail('features'),
            'Last activity' => $this->last_activity?->format('M d, Y'),
            'Checked' => $this->last_checked_at?->format('M d, Y'),
        ];

        return collect($rows)
            ->filter(fn (?string $value): bool => filled($value))
            ->map(fn (string $value, string $label): array => ['label' => __($label), 'value' => $value])
            ->values()
            ->all();
    }

    /**
     * @return list<array{label: string, url: string, primary: bool}>
     */
    public function links(): array
    {
        return collect([
            ['label' => __('Repository'), 'url' => $this->repo_url, 'primary' => true],
            ['label' => __('Source / releases'), 'url' => $this->primary_source !== $this->repo_url ? $this->primary_source : null, 'primary' => false],
            ['label' => __('Website'), 'url' => $this->homepage_url, 'primary' => false],
            ['label' => __('Framework'), 'url' => $this->framework_repo_url, 'primary' => false],
        ])
            ->filter(fn (array $link): bool => filled($link['url']))
            ->values()
            ->all();
    }

    /**
     * Everything the client-side tracker (Alpine) needs to filter, sort and render one entry.
     *
     * @return array<string, mixed>
     */
    public function toTrackerArray(): array
    {
        $section = $this->section();

        return [
            'slug' => $this->slug,
            'title' => $this->game_title,
            'project' => $this->project_name,
            'platform' => $this->original_platform ?: 'Multi',
            'platforms' => $this->platforms(),
            'section' => $section->value,
            'playable' => $this->status->isPlayable(),
            'flag' => $section->flagLabel() ? __($section->flagLabel()) : null,
            'status' => $this->status->value,
            'statusLabel' => __($this->status->label()),
            'statusClasses' => $this->status->pillClasses(),
            'technique' => $this->techniqueLabel(),
            'version' => $this->displayVersion(),
            'activity' => $this->activityDate()?->toDateString() ?? '',
            'notes' => $this->notes,
            'cover' => $this->coverUrl(),
            'os' => $this->operatingSystemSupport(),
            'supports' => collect(self::OPERATING_SYSTEMS)->keys()->filter(fn (string $key): bool => $this->{$key.'_support'} === 'true')->values()->all(),
            'rows' => $this->detailRows(),
            'links' => $this->links(),
            'haystack' => str(implode(' ', [$this->game_title, $this->project_name, $this->previous_project_name, $this->framework, $this->notes, $this->original_platform]))->lower()->toString(),
        ];
    }
}

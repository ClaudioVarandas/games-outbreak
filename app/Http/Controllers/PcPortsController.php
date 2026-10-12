<?php

namespace App\Http\Controllers;

use App\Enums\PcPortMetricEnum;
use App\Enums\PcPortSectionEnum;
use App\Enums\PcPortStatusEnum;
use App\Models\PcPort;
use App\Support\PcPortStructuredData;
use Illuminate\View\View;

class PcPortsController extends Controller
{
    public function __invoke(): View
    {
        $ports = PcPort::orderBy('game_title')->get();
        $nativePorts = $ports->filter(fn (PcPort $port): bool => $port->section() === PcPortSectionEnum::NativePorts);
        $platforms = $ports->flatMap(fn (PcPort $port): array => $port->platforms())->unique()->sort()->values();

        $stats = collect(PcPortMetricEnum::cases())->map(fn (PcPortMetricEnum $metric): array => [
            'metric' => $metric,
            'value' => match ($metric) {
                PcPortMetricEnum::Entries => $ports->count(),
                PcPortMetricEnum::NativePorts => $nativePorts->count(),
                PcPortMetricEnum::Playable => $ports->filter(fn (PcPort $port): bool => $port->status->isPlayable())->count(),
                PcPortMetricEnum::Platforms => $platforms->count(),
            },
        ]);

        $statuses = collect(PcPortStatusEnum::filterOptions())
            ->filter(fn (string $label, string $value): bool => $ports->contains(fn (PcPort $port): bool => PcPortStatusEnum::matchesFilter($port->status, $value)));

        $pageTitle = __('Unofficial PC Ports');
        $pageDescription = __('Community static recompilations, decompilations and reimplementations of console games for PC.');

        return view('ports.index', [
            'pageTitle' => $pageTitle,
            'pageDescription' => $pageDescription,
            'structuredData' => PcPortStructuredData::for($ports, $pageTitle, $pageDescription),
            'ports' => $ports->map(fn (PcPort $port): array => $port->toTrackerArray())->values(),
            'stats' => $stats,
            'platforms' => $platforms,
            'statuses' => $statuses,
            'sections' => PcPortSectionEnum::cases(),
            'lastChecked' => $ports->max('last_checked_at'),
        ]);
    }
}

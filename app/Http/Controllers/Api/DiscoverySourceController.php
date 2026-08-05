<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Enums\DiscoverySourceKindEnum;
use App\Enums\DiscoverySourceOriginEnum;
use App\Enums\DiscoverySourcePurposeEnum;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\ProposeSourcesRequest;
use App\Http\Requests\Api\SourceRunReportRequest;
use App\Models\DiscoverySource;
use App\Services\DiscoverySourceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class DiscoverySourceController extends Controller
{
    public function __construct(private readonly DiscoverySourceService $sourceService) {}

    /**
     * Active sources for agent sweeps, filterable by the purpose they cover.
     */
    public function index(Request $request): JsonResponse
    {
        $request->validate([
            'purpose' => ['nullable', Rule::enum(DiscoverySourcePurposeEnum::class)],
            'kind' => ['nullable', Rule::enum(DiscoverySourceKindEnum::class)],
        ]);

        $purpose = DiscoverySourcePurposeEnum::tryFrom((string) $request->query('purpose'));

        $sources = DiscoverySource::usable()
            ->when($purpose, function ($query) use ($purpose) {
                $covering = array_filter(DiscoverySourcePurposeEnum::cases(), fn (DiscoverySourcePurposeEnum $case): bool => match ($purpose) {
                    DiscoverySourcePurposeEnum::News => $case->coversNews(),
                    DiscoverySourcePurposeEnum::Releases => $case->coversReleases(),
                    DiscoverySourcePurposeEnum::Both => $case === DiscoverySourcePurposeEnum::Both,
                });

                $query->whereIn('purpose', array_map(fn (DiscoverySourcePurposeEnum $case): string => $case->value, $covering));
            })
            ->when($request->query('kind'), fn ($query, $kind) => $query->where('kind', $kind))
            ->orderBy('kind')
            ->orderBy('name')
            ->get()
            ->map(fn (DiscoverySource $source): array => [
                'id' => $source->id,
                'name' => $source->name,
                'kind' => $source->kind->value,
                'purpose' => $source->purpose->value,
                'url' => $source->url,
                'locator' => $source->locator,
                'score' => $source->score(),
            ]);

        return response()->json(['sources' => $sources]);
    }

    /**
     * Agent-proposed sources land as pending rows; the admin confirms them on
     * the discovery-sources page.
     */
    public function propose(ProposeSourcesRequest $request): JsonResponse
    {
        $results = collect($request->validated('sources'))->map(function (array $item): array {
            $result = $this->sourceService->propose($item + ['origin' => DiscoverySourceOriginEnum::Agent]);

            if ($result['status'] === 'invalid') {
                return [
                    'url' => $item['url'] ?? null,
                    'status' => 'invalid',
                    'error' => $result['error'] ?? 'Invalid source.',
                ];
            }

            return [
                'url' => $item['url'] ?? null,
                'status' => $result['status'],
                'id' => $result['source']->id,
                'locator' => $result['source']->locator,
                'needs_enrichment' => $result['source']->needs_enrichment,
            ];
        });

        return response()->json([
            'review_url' => route('admin.discovery-sources.index'),
            'results' => $results->values(),
        ]);
    }

    /**
     * Per-run yield stats reported by discovery sweeps.
     */
    public function runReport(SourceRunReportRequest $request): JsonResponse
    {
        $updated = $this->sourceService->applyRunReport($request->validated('sources'));

        return response()->json(['updated' => $updated]);
    }
}

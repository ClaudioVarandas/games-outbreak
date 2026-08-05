<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Enums\DiscoverySourceOriginEnum;
use App\Enums\DiscoverySourcePurposeEnum;
use App\Enums\DiscoverySourceStatusEnum;
use App\Http\Controllers\Controller;
use App\Models\DiscoverySource;
use App\Services\DiscoverySourceService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class DiscoverySourceController extends Controller
{
    public function __construct(private readonly DiscoverySourceService $sourceService) {}

    public function index(): View
    {
        $sources = DiscoverySource::orderBy('kind')->orderBy('name')->get();

        return view('admin.discovery-sources.index', [
            'pendingSources' => $sources->filter(fn (DiscoverySource $source): bool => $source->status === DiscoverySourceStatusEnum::Pending)->values(),
            'activeSources' => $sources->filter(fn (DiscoverySource $source): bool => $source->status === DiscoverySourceStatusEnum::Active)->values(),
            'inactiveSources' => $sources->filter(fn (DiscoverySource $source): bool => in_array($source->status, [DiscoverySourceStatusEnum::Disabled, DiscoverySourceStatusEnum::Rejected], true))->values(),
        ]);
    }

    /**
     * "Throw links here": one URL per line, classified by cheap heuristics into
     * pending sources; whatever stays ambiguous is flagged for agent enrichment.
     */
    public function intake(Request $request): RedirectResponse
    {
        $request->validate([
            'links' => ['required', 'string', 'max:10000'],
            'purpose' => ['nullable', Rule::enum(DiscoverySourcePurposeEnum::class)],
        ]);

        $urls = collect(preg_split('/\s+/', (string) $request->input('links')))
            ->map(fn (string $line): string => trim($line))
            ->filter()
            ->unique()
            ->values();

        $created = 0;
        $duplicates = 0;
        $invalid = [];

        foreach ($urls as $url) {
            $result = $this->sourceService->propose([
                'url' => $url,
                'purpose' => $request->input('purpose'),
                'origin' => DiscoverySourceOriginEnum::Admin,
            ]);

            match ($result['status']) {
                'created' => $created++,
                'duplicate' => $duplicates++,
                'invalid' => $invalid[] = $url,
            };
        }

        $message = sprintf('%d source(s) added as pending, %d duplicate(s) skipped.', $created, $duplicates);

        if ($invalid !== []) {
            $message .= ' Not recognised: '.implode(', ', array_slice($invalid, 0, 5)).(count($invalid) > 5 ? '…' : '');
        }

        return redirect()->route('admin.discovery-sources.index')->with($invalid === [] ? 'success' : 'error', $message);
    }

    public function update(Request $request, DiscoverySource $discoverySource): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'purpose' => ['required', Rule::enum(DiscoverySourcePurposeEnum::class)],
        ]);

        $discoverySource->update($validated);

        return redirect()->route('admin.discovery-sources.index')->with('success', 'Source updated.');
    }

    public function confirm(DiscoverySource $discoverySource): RedirectResponse
    {
        return $this->setStatus($discoverySource, DiscoverySourceStatusEnum::Active, 'Source confirmed.');
    }

    public function reject(DiscoverySource $discoverySource): RedirectResponse
    {
        return $this->setStatus($discoverySource, DiscoverySourceStatusEnum::Rejected, 'Source rejected. It stays on file so the same locator cannot be re-proposed.');
    }

    public function disable(DiscoverySource $discoverySource): RedirectResponse
    {
        return $this->setStatus($discoverySource, DiscoverySourceStatusEnum::Disabled, 'Source disabled.');
    }

    public function enable(DiscoverySource $discoverySource): RedirectResponse
    {
        return $this->setStatus($discoverySource, DiscoverySourceStatusEnum::Active, 'Source enabled.');
    }

    /**
     * Bulk confirm/reject for the pending block's selection.
     */
    public function bulk(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'action' => ['required', Rule::in(['confirm', 'reject'])],
            'source_ids' => ['required', 'array', 'min:1'],
            'source_ids.*' => ['integer'],
        ]);

        $status = $validated['action'] === 'confirm'
            ? DiscoverySourceStatusEnum::Active
            : DiscoverySourceStatusEnum::Rejected;

        $updated = DiscoverySource::pending()
            ->whereIn('id', $validated['source_ids'])
            ->update(['status' => $status->value]);

        return redirect()->route('admin.discovery-sources.index')
            ->with('success', sprintf('%d source(s) %sed.', $updated, $validated['action']));
    }

    public function destroy(DiscoverySource $discoverySource): RedirectResponse
    {
        $discoverySource->delete();

        return redirect()->route('admin.discovery-sources.index')->with('success', 'Source deleted.');
    }

    private function setStatus(DiscoverySource $discoverySource, DiscoverySourceStatusEnum $status, string $message): RedirectResponse
    {
        $discoverySource->update(['status' => $status]);

        return redirect()->route('admin.discovery-sources.index')->with('success', $message);
    }
}

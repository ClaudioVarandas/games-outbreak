<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\DiscoverySourceKindEnum;
use App\Enums\DiscoverySourceOriginEnum;
use App\Enums\DiscoverySourcePurposeEnum;
use App\Enums\DiscoverySourceStatusEnum;
use Database\Factories\DiscoverySourceFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DiscoverySource extends Model
{
    /** @use HasFactory<DiscoverySourceFactory> */
    use HasFactory;

    protected $attributes = [
        'runs_count' => 0,
        'items_found_total' => 0,
        'items_promoted' => 0,
        'items_rejected' => 0,
        'consecutive_failures' => 0,
    ];

    protected $fillable = [
        'name',
        'kind',
        'purpose',
        'status',
        'origin',
        'url',
        'locator',
        'confidence',
        'evidence',
        'needs_enrichment',
        'runs_count',
        'items_found_total',
        'items_promoted',
        'items_rejected',
        'consecutive_failures',
        'last_run_at',
        'last_success_at',
    ];

    protected function casts(): array
    {
        return [
            'kind' => DiscoverySourceKindEnum::class,
            'purpose' => DiscoverySourcePurposeEnum::class,
            'status' => DiscoverySourceStatusEnum::class,
            'origin' => DiscoverySourceOriginEnum::class,
            'needs_enrichment' => 'boolean',
            'runs_count' => 'integer',
            'items_found_total' => 'integer',
            'items_promoted' => 'integer',
            'items_rejected' => 'integer',
            'consecutive_failures' => 'integer',
            'last_run_at' => 'datetime',
            'last_success_at' => 'datetime',
        ];
    }

    public function scopeUsable(Builder $query): Builder
    {
        return $query->where('status', DiscoverySourceStatusEnum::Active->value);
    }

    public function scopePending(Builder $query): Builder
    {
        return $query->where('status', DiscoverySourceStatusEnum::Pending->value);
    }

    /**
     * Share of found items the admin actually promoted; null until the source
     * has produced anything (neutral, not zero).
     */
    public function score(): ?float
    {
        if ($this->items_found_total === 0) {
            return null;
        }

        return round($this->items_promoted / $this->items_found_total, 2);
    }

    public function looksDead(): bool
    {
        return $this->consecutive_failures >= 3
            || ($this->runs_count >= 3 && $this->items_found_total === 0);
    }
}

<?php

namespace App\Models;

use App\Enums\NewsStatusEnum;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class News extends Model
{
    use HasFactory;

    protected $fillable = [
        'title',
        'slug',
        'image_path',
        'summary',
        'content',
        'status',
        'source_url',
        'source_name',
        'tags',
        'user_id',
        'published_at',
    ];

    protected function casts(): array
    {
        return [
            'content' => 'array',
            'tags' => 'array',
            'status' => NewsStatusEnum::class,
            'published_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (News $news) {
            if (empty($news->slug)) {
                $news->slug = static::generateUniqueSlug($news->title);
            }
        });
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    // Scopes
    public function scopePublished(Builder $query): Builder
    {
        return $query->where('status', NewsStatusEnum::Published)
            ->whereNotNull('published_at')
            ->where('published_at', '<=', now());
    }

    public function scopeDraft(Builder $query): Builder
    {
        return $query->where('status', NewsStatusEnum::Draft);
    }

    public function scopeArchived(Builder $query): Builder
    {
        return $query->where('status', NewsStatusEnum::Archived);
    }

    // Helpers
    public function isPublished(): bool
    {
        return $this->status === NewsStatusEnum::Published
            && $this->published_at !== null
            && $this->published_at->lte(now());
    }

    public function isDraft(): bool
    {
        return $this->status === NewsStatusEnum::Draft;
    }

    public function isArchived(): bool
    {
        return $this->status === NewsStatusEnum::Archived;
    }

    public function getImageUrlAttribute(): ?string
    {
        if (! $this->image_path) {
            return null;
        }

        if (str_starts_with($this->image_path, 'http://') || str_starts_with($this->image_path, 'https://')) {
            return $this->image_path;
        }

        if (str_starts_with($this->image_path, '/')) {
            return asset($this->image_path);
        }

        return asset('storage/news/'.$this->image_path);
    }

    public function getFormattedPublishedAtAttribute(): ?string
    {
        return $this->published_at?->format('M j, Y');
    }

    public function getReadingTimeAttribute(): int
    {
        $content = $this->getPlainTextContent();
        $wordCount = str_word_count($content);

        return max(1, (int) ceil($wordCount / 200));
    }

    public function getPlainTextContent(): string
    {
        if (! is_array($this->content)) {
            return '';
        }

        return $this->extractTextFromTiptap($this->content);
    }

    protected function extractTextFromTiptap(array $node): string
    {
        $text = '';

        if (isset($node['text'])) {
            $text .= $node['text'].' ';
        }

        if (isset($node['content']) && is_array($node['content'])) {
            foreach ($node['content'] as $child) {
                $text .= $this->extractTextFromTiptap($child);
            }
        }

        return $text;
    }

    protected static function generateUniqueSlug(string $title): string
    {
        $slug = str()->slug($title);
        $originalSlug = $slug;
        $counter = 1;

        while (static::where('slug', $slug)->exists()) {
            $slug = $originalSlug.'-'.$counter;
            $counter++;
        }

        return $slug;
    }
}

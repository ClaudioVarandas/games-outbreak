<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Observers\UserObserver;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

#[ObservedBy([UserObserver::class])]
class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'is_admin',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_admin' => 'boolean',
        ];
    }

    public function gameLists(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(GameList::class);
    }

    public function isAdmin(): bool
    {
        return $this->is_admin ?? false;
    }

    public function canCreateSystemLists(): bool
    {
        return $this->isAdmin();
    }

    public function getOrCreateBacklogList(): GameList
    {
        return $this->gameLists()
            ->firstOrCreate(
                [
                    'user_id' => $this->id,
                    'list_type' => \App\Enums\ListTypeEnum::BACKLOG->value,
                ],
                [
                    'name' => 'Backlog',
                    'description' => 'Games I plan to play',
                    'slug' => 'backlog-user-' . $this->id,
                    'is_public' => false,
                    'is_system' => false,
                ]
            );
    }

    public function getOrCreateWishlistList(): GameList
    {
        return $this->gameLists()
            ->firstOrCreate(
                [
                    'user_id' => $this->id,
                    'list_type' => \App\Enums\ListTypeEnum::WISHLIST->value,
                ],
                [
                    'name' => 'Wishlist',
                    'description' => 'Games I want to buy',
                    'slug' => 'wishlist-user-' . $this->id,
                    'is_public' => false,
                    'is_system' => false,
                ]
            );
    }

    public function ensureSpecialLists(): void
    {
        $this->getOrCreateBacklogList();
        $this->getOrCreateWishlistList();
    }
}

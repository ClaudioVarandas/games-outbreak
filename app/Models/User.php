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
        'username',
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

    public function userGames(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(UserGame::class);
    }

    public function gameCollection(): \Illuminate\Database\Eloquent\Relations\HasOne
    {
        return $this->hasOne(UserGameCollection::class);
    }

    public function getOrCreateGameCollection(): UserGameCollection
    {
        return $this->gameCollection ?? $this->gameCollection()->create([
            'name' => $this->username."'s Games",
        ]);
    }

    public function isAdmin(): bool
    {
        return $this->is_admin ?? false;
    }

    public function canCreateSystemLists(): bool
    {
        return $this->isAdmin();
    }
}

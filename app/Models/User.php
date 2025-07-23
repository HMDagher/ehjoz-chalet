<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Spatie\Permission\Traits\HasRoles;
use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;

final class User extends Authenticatable implements FilamentUser, MustVerifyEmail
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable, HasRoles;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'phone',
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

    public function canAccessPanel(Panel $panel): bool
    {
        if ($panel->getId() === 'admin') {
            return $this->hasRole('admin');
        }

        if ($panel->getId() === 'chalet') {
            return $this->hasRole('owner');
        }

        if ($panel->getId() === 'customer') {
            return $this->hasRole('customer');
        }

        return false;
    }

    /**
     * Get the bookings for the user.
     */
    public function bookings()
    {
        return $this->hasMany(Booking::class);
    }

    /**
     * Get the chalets owned by the user.
     */
    public function chalets()
    {
        return $this->hasMany(Chalet::class, 'owner_id');
    }

    /**
     * Get the chalet owned by the user.
     */
    public function chalet()
    {
        return $this->hasOne(Chalet::class, 'owner_id');
    }

    /**
     * Get the reviews written by the user.
     */
    public function reviews()
    {
        return $this->hasMany(Review::class);
    }

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
        ];
    }
}

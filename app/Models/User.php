<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

#[Fillable(['nama', 'email', 'password', 'role', 'no_hp', 'wilayah', 'last_latitude', 'last_longitude', 'last_location_updated_at'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable, HasApiTokens;

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
            'last_location_updated_at' => 'datetime',
        ];
    }

    public function karungs(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(Karung::class, 'created_by');
    }

    public function pakets(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(Paket::class, 'kurir_id');
    }

    public function rutes(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(RuteHarian::class, 'kurir_id');
    }
}


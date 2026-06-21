<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use PHPOpenSourceSaver\JWTAuth\Contracts\JWTSubject;

class User extends Authenticatable implements JWTSubject
{
    use HasFactory, Notifiable;

    protected $fillable = [
        'name', 'email', 'password', 'role',
        'age', 'gender', 'weight_kg', 'height_cm',
        'activity_level', 'goal', 'target_weight',
        'bmr', 'tdee', 'target_calories', 'target_protein', 'target_carbs', 'target_fat',
        'diet_type', 'allergies',
    ];

    protected $hidden = ['password', 'remember_token'];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password'          => 'hashed',
            'allergies'         => 'array',
        ];
    }

    public function getJWTIdentifier()
    {
        return $this->getKey();
    }

    public function getJWTCustomClaims(): array
    {
        return ['role' => $this->role];
    }

    public function recommendations()
    {
        return $this->hasMany(Recommendation::class);
    }

    public function weightHistory()
    {
        return $this->hasMany(WeightHistory::class);
    }

    public function refreshTokens()
    {
        return $this->hasMany(RefreshToken::class);
    }

    public function isAdmin(): bool
    {
        return $this->role === 'admin';
    }
}

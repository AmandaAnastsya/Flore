<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Database\Eloquent\Relations\HasMany;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name', 'email', 'phone', 'date_of_birth', 'anniversary_date', 'role', 'password',
        'reminders_enabled', 'reminder_days',
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
        'date_of_birth' => 'date',
        'anniversary_date' => 'date',
        'reminders_enabled' => 'boolean',
        'reminder_days' => 'integer',
        'password' => 'hashed',
    ];
    }
    public function isAdmin(): bool
{
    return $this->role === 'admin';
}
public function orders(): HasMany
{
    return $this->hasMany(Order::class);
}

public function reminders(): HasMany
{
    return $this->hasMany(Reminder::class);
}

public function specialMoments(): HasMany
{
    return $this->hasMany(SpecialMoment::class);
}

public function testimonials(): HasMany
{
    return $this->hasMany(Testimonial::class);
}
}

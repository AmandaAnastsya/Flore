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

    /** URL foto profil, atau null bila belum diunggah (tampilan memakai inisial). */
    public function avatarUrl(): ?string
    {
        return $this->avatar ? asset('storage/' . $this->avatar) : null;
    }

    /** Dua huruf pertama nama, mis. "Clarissa Valery" → "CV". */
    public function initials(): string
    {
        return collect(explode(' ', trim($this->name)))->filter()->take(2)
            ->map(fn ($w) => mb_strtoupper(mb_substr($w, 0, 1)))->join('') ?: '?';
    }

    /** Akun yang sudah dihapus pemiliknya (data dianonimkan, riwayat pesanan disimpan untuk pembukuan). */
    public function isDeleted(): bool
    {
        return str_ends_with((string) $this->email, '@deleted.flore.invalid');
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

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;

class User extends Authenticatable
{
    protected $fillable = ['name', 'username', 'password', 'photo', 'is_admin', 'email', 'google_id', 'google_avatar', 'must_set_password'];

    protected $hidden = ['password', 'remember_token', 'temp_password', 'google_id'];

    protected function casts(): array
    {
        return [
            'password' => 'hashed',
            'is_admin' => 'boolean',
            'is_blocked' => 'boolean',
            'must_set_password' => 'boolean',
            'temp_password_expires_at' => 'datetime',
            'reset_requested_at' => 'datetime',
        ];
    }

    /** Benar bila akun ini dibuat/ditautkan ke Google. */
    public function usesGoogle(): bool
    {
        return !empty($this->google_id);
    }

    /** Kata sandi sementara 8 karakter, tanpa huruf/angka yang mudah tertukar. */
    public static function randomPassword(): string
    {
        $alphabet = 'abcdefghjkmnpqrstuvwxyz23456789';
        $temp = '';
        for ($i = 0; $i < 8; $i++) {
            $temp .= $alphabet[random_int(0, strlen($alphabet) - 1)];
        }

        return $temp;
    }

    public function reports(): HasMany
    {
        return $this->hasMany(Report::class);
    }
}

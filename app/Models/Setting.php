<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Pengaturan aplikasi berbentuk pasangan kunci–nilai (nilai disimpan sebagai JSON). */
class Setting extends Model
{
    protected $primaryKey = 'key';
    public $incrementing = false;
    protected $keyType = 'string';
    protected $fillable = ['key', 'value'];

    public static function read(string $key): ?array
    {
        $raw = static::query()->whereKey($key)->value('value');

        return $raw ? json_decode($raw, true) : null;
    }

    public static function write(string $key, ?array $value): void
    {
        if ($value === null) {
            static::query()->whereKey($key)->delete();

            return;
        }
        static::query()->updateOrCreate(['key' => $key], ['value' => json_encode($value)]);
    }
}

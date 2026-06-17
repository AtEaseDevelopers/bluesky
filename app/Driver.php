<?php

namespace App;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Hash;

class Driver extends Model
{
    use HasFactory;

    protected $fillable = [
        'lorry_number',
        'name',
        'phone',
        'pin_hash',
        'api_token',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    protected $hidden = [
        'pin_hash',
        'api_token',
    ];

    public function verifyPin(string $pin): bool
    {
        return $this->pin_hash && Hash::check($pin, $this->pin_hash);
    }

    public function issueApiToken(): string
    {
        $plainToken = bin2hex(random_bytes(32));
        $this->update(['api_token' => hash('sha256', $plainToken)]);

        return $plainToken;
    }

    public static function findByToken(?string $plainToken): ?self
    {
        if (!$plainToken) {
            return null;
        }

        return static::where('api_token', hash('sha256', $plainToken))
            ->where('is_active', true)
            ->first();
    }
}

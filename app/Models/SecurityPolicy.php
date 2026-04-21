<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SecurityPolicy extends Model
{
    use HasFactory;

    protected $fillable = [
        'password_min_length',
        'password_requires_uppercase',
        'password_requires_number',
        'password_requires_symbol',
        'max_failed_logins',
        'lockout_minutes',
        'session_timeout_minutes',
        'enforce_2fa_for_admins',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'password_requires_uppercase' => 'boolean',
            'password_requires_number' => 'boolean',
            'password_requires_symbol' => 'boolean',
            'enforce_2fa_for_admins' => 'boolean',
        ];
    }
}

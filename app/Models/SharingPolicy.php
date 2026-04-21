<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SharingPolicy extends Model
{
    use HasFactory;

    protected $fillable = [
        'internal_sharing_enabled',
        'allow_external_links',
        'default_link_expiry_days',
        'max_share_duration_days',
        'require_password_for_external_links',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'internal_sharing_enabled' => 'boolean',
            'allow_external_links' => 'boolean',
            'require_password_for_external_links' => 'boolean',
        ];
    }
}

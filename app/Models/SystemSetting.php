<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SystemSetting extends Model
{
    use HasFactory;

    protected $fillable = [
        'company_name',
        'company_website',
        'support_email',
        'support_phone',
        'footer_address',
        'maintenance_mode',
        'read_only_mode',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'maintenance_mode' => 'boolean',
            'read_only_mode' => 'boolean',
        ];
    }
}

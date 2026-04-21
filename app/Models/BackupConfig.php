<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class BackupConfig extends Model
{
    use HasFactory;

    protected $fillable = [
        'enabled',
        'database_frequency',
        'files_frequency',
        'retention_period',
        'last_backup_at',
        'last_backup_status',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'enabled' => 'boolean',
            'last_backup_at' => 'datetime',
        ];
    }
}

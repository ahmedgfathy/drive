<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StorageQuota extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'quota_bytes',
        'used_bytes',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}

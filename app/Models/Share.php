<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class Share extends Model
{
    use HasFactory;

    protected $fillable = [
        'shareable_type',
        'shareable_id',
        'channel',
        'target_type',
        'target_user_id',
        'target_name',
        'target_email',
        'target_department',
        'permission',
        'granted_by',
        'expires_at',
        'public_token',
        'public_password',
        'allow_download',
    ];

    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime',
            'allow_download' => 'boolean',
        ];
    }

    public function shareable(): MorphTo
    {
        return $this->morphTo();
    }

    public function targetUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'target_user_id');
    }

    public function grantedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'granted_by');
    }
}

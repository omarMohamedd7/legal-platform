<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Notification extends Model
{
    use HasUuids;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'id',
        'user_id',
        'type',
        'message',
        'data',
        'read',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'data' => 'array',
        'read' => 'boolean',
    ];

    /**
     * Get the user that owns the notification.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}

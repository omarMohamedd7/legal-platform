<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class ConsultationRequest extends Model
{
    use HasFactory;

    /**
     * Status constants for consultation requests
     */
    public const STATUS_PENDING = 'pending';
    public const STATUS_PAID = 'paid';
    public const STATUS_CANCELLED = 'cancelled';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<string>
     */
    protected $fillable = [
        'client_id',
        'lawyer_id',
        'price',
        'status',
    ];

    /**
     * Get the client who requested the consultation.
     *
     * @return BelongsTo
     */
    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class, 'client_id', 'client_id');
    }

    /**
     * Get the lawyer who was requested for consultation.
     *
     * @return BelongsTo
     */
    public function lawyer(): BelongsTo
    {
        return $this->belongsTo(Lawyer::class, 'lawyer_id', 'lawyer_id');
    }
    
    /**
     * Get the payment associated with this consultation request.
     *
     * @return HasOne
     */
    public function payment(): HasOne
    {
        return $this->hasOne(Payment::class, 'consultation_request_id');
    }
    
    /**
     * Get the chat associated with this consultation request.
     *
     * @return HasOne
     */
    public function chat(): HasOne
    {
        return $this->hasOne(Chat::class, 'consultation_request_id');
    }
    
    /**
     * Check if the consultation request is in pending status.
     *
     * @return bool
     */
    public function isPending(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }
    
    /**
     * Check if the consultation request is paid.
     *
     * @return bool
     */
    public function isPaid(): bool
    {
        return $this->status === self::STATUS_PAID;
    }
    
    /**
     * Check if the consultation request is cancelled.
     *
     * @return bool
     */
    public function isCancelled(): bool
    {
        return $this->status === self::STATUS_CANCELLED;
    }
} 
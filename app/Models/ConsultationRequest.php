<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ConsultationRequest extends Model
{
    use HasFactory;

    protected $fillable = [
        'client_id',
        'lawyer_id',
        'price',
        'status',
    ];

    /**
     * Get the client who requested the consultation.
     */
    public function client()
    {
        return $this->belongsTo(User::class, 'client_id');
    }

    /**
     * Get the lawyer who was requested for consultation.
     */
    public function lawyer()
    {
        return $this->belongsTo(User::class, 'lawyer_id');
    }
    
    /**
     * Get the payment associated with this consultation request.
     */
    public function payment()
    {
        return $this->hasOne(Payment::class, 'consultation_request_id');
    }
} 
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Payment extends Model
{
    use HasFactory;

    protected $primaryKey = 'payment_id';
    
    protected $fillable = [
        'consultation_request_id',
        'amount',
        'payment_method',
        'status',
        'transaction_id',
    ];

    /**
     * Get the consultation request that owns the payment.
     */
    public function consultationRequest()
    {
        return $this->belongsTo(ConsultationRequest::class, 'consultation_request_id');
    }
} 
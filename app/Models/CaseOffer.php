<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CaseOffer extends Model
{
    use HasFactory;
    
    protected $primaryKey = 'offer_id';
    
    protected $fillable = [
        'published_case_id',
        'lawyer_id',
        'price',
        'description',
        'status', // Pending, Accepted, Rejected
    ];
    
    protected $casts = [
        'price' => 'decimal:2',
    ];
    
    // علاقة مع القضية المنشورة
    public function publishedCase()
    {
        return $this->belongsTo(PublishedCase::class, 'published_case_id', 'published_case_id');
    }
    
    // علاقة مع المحامي
    public function lawyer()
    {
        return $this->belongsTo(Lawyer::class, 'lawyer_id', 'lawyer_id');
    }
} 
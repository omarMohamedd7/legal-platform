<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Lawyer extends Model
{
    use HasFactory;
    protected $primaryKey = 'lawyer_id';


    protected $fillable = [
        'user_id',
        'phone_number',
        'specialization',
        'city',
        'consult_fee',
        'bio',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function legalCases()
    {
        return $this->hasMany(LegalCase::class);
    }
    public function caseRequests()
    {
        return $this->hasMany(CaseRequest::class, 'lawyer_id');
    }
    
    // علاقة مع عروض المحامي على القضايا المنشورة
    public function caseOffers()
    {
        return $this->hasMany(CaseOffer::class, 'lawyer_id');
    }
    
    // علاقة مع جلسات المحكمة
    public function courtSessions()
    {
        return $this->hasMany(CourtSession::class, 'lawyer_id');
    }
}


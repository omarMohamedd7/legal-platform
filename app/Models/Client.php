<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Client extends Model
{
    use HasFactory;
    protected $primaryKey = 'client_id';

    protected $fillable = [
        'user_id',
        'phone_number',
        'city'
        
    ];

    // علاقته مع User
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
    return $this->hasMany(CaseRequest::class, 'client_id');
}

// علاقة مع القضايا المنشورة للعامة
public function publishedCases()
{
    return $this->hasMany(PublishedCase::class, 'client_id');
}
}


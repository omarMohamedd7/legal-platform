<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Judge extends Model
{
    use HasFactory;
    protected $primaryKey = 'judge_id';
    protected $fillable = [
        'user_id',
        'court_name',
        'specialization',
        
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
}


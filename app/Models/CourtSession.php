<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CourtSession extends Model
{
    use HasFactory;

    protected $fillable = [
        'legal_case_id',
        'lawyer_id',
        'court_name',
        'session_date',
        'session_time',
        'notes',
        'status',
    ];

    /**
     * Get the legal case that owns the court session.
     */
    public function legalCase()
    {
        return $this->belongsTo(LegalCase::class, 'legal_case_id', 'case_id');
    }

    /**
     * Get the lawyer that owns the court session.
     */
    public function lawyer()
    {
        return $this->belongsTo(Lawyer::class, 'lawyer_id', 'lawyer_id');
    }
} 
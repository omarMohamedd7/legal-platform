<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CourtSession extends Model
{
    use HasFactory;

    protected $primaryKey = 'session_id';

    protected $fillable = [
        'legal_case_id',
        'session_date',
        'session_time',
        'court_name',
        'judge_name',
        'session_notes',
        'attachments',
        'session_status',
        'added_by_id',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array
     */
    protected $casts = [
        'session_date' => 'date',
        'attachments' => 'array',
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

    /**
     * Get the user who added the court session.
     */
    public function addedBy()
    {
        return $this->belongsTo(User::class, 'added_by_id');
    }
} 
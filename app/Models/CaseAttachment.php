<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CaseAttachment extends Model
{
    use HasFactory;

    protected $fillable = [
        'case_id',
        'file_path',
        'file_type',
        'original_filename',
    ];

    /**
     * Get the legal case that the attachment belongs to.
     */
    public function legalCase()
    {
        return $this->belongsTo(LegalCase::class, 'case_id', 'case_id');
    }
} 
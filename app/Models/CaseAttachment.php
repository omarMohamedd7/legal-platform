<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;

class CaseAttachment extends Model
{
    use HasFactory;
    
    protected $primaryKey = 'attachment_id';

    protected $fillable = [
        'case_id',
        'file_path',
        'file_type',
        'original_filename',
        'uploaded_by_id',
    ];

    /**
     * Get the legal case that the attachment belongs to.
     */
    public function legalCase()
    {
        return $this->belongsTo(LegalCase::class, 'case_id', 'case_id');
    }
    
    /**
     * Get the user who uploaded the file.
     */
    public function uploadedBy()
    {
        return $this->belongsTo(User::class, 'uploaded_by_id');
    }
    
    /**
     * Get the full URL for the attachment.
     */
    public function getFileUrlAttribute()
    {
        return URL::to(Storage::url($this->file_path));
    }
    
    /**
     * The accessors to append to the model's array form.
     */
    protected $appends = ['file_url'];
} 
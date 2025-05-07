<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
class LegalCase extends Model
{
    use HasFactory;
    protected $table = 'cases';
    protected $primaryKey = 'case_id';
    protected $fillable = [
        'case_number',
        'plaintiff_name',
        'defendant_name',
        'case_type',
        'description',
        'status',
        'created_by_id',
        'assigned_lawyer_id',
        'case_publish_id',      
    ];

    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by_id');
    }

    public function assignedLawyer()
    {
        return $this->belongsTo(Lawyer::class, 'assigned_lawyer_id');
    }

    public function casePublish()
    {
        return $this->belongsTo(Judge::class, 'case_publish_id');
    }
    
    // علاقة مع طلب التوكيل المباشر
    public function caseRequest()
    {
        return $this->hasOne(CaseRequest::class, 'case_id', 'case_id');
    }
    
    // علاقة مع النشر العام للقضية
    public function publishedCase()
    {
        return $this->hasOne(PublishedCase::class, 'case_id', 'case_id');
    }
    
    // علاقة مع جلسات المحكمة
    public function courtSessions()
    {
        return $this->hasMany(CourtSession::class, 'legal_case_id', 'case_id');
    }
    
    // علاقة مع مرفقات القضية
    public function attachments()
    {
        return $this->hasMany(CaseAttachment::class, 'case_id', 'case_id');
    }
}

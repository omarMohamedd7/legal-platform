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
        'attachments',
        'created_by_id',
        'assigned_lawyer_id',
        'case_publish_id',      
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array
     */
    protected $casts = [
        'attachments' => 'array',
    ];

    public function createdBy()
    {
        return $this->belongsTo(Client::class, 'created_by_id');
    }

    public function assignedLawyer()
    {
        return $this->belongsTo(Lawyer::class, 'assigned_lawyer_id');
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
    
   
}

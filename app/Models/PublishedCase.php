<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PublishedCase extends Model
{
    use HasFactory;
    
    /**
     * The primary key for the model.
     *
     * @var string
     */
    protected $primaryKey = 'published_case_id';
    
    /**
     * The attributes that are mass assignable.
     *
     * @var array<string>
     */
    protected $fillable = [
        'case_id',
        'client_id',
        'status',
        'target_city',
        'target_specialization',
    ];
    
    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
    ];
    
    /**
     * Get the legal case that is published.
     * This relationship provides access to the case details without duplicating the data.
     */
    public function legalCase()
    {
        return $this->belongsTo(LegalCase::class, 'case_id', 'case_id');
    }
    
    /**
     * Get the client who published the case.
     */
    public function client()
    {
        return $this->belongsTo(Client::class, 'client_id', 'client_id');
    }
    
    /**
     * Get the offers submitted for this published case.
     */
    public function offers()
    {
        return $this->hasMany(CaseOffer::class, 'published_case_id');
    }
}

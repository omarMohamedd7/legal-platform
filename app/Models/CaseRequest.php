<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CaseRequest extends Model
{
    use HasFactory;

    protected $primaryKey = 'request_id';

    protected $fillable = [
        'client_id',
        'lawyer_id',
        'case_id',
        'status',
    ];

    /**
     * The attributes that should be cast.
     *
     */
   

    public function client()
    {
        return $this->belongsTo(Client::class, 'client_id', 'client_id');
    }

    public function lawyer()
    {
        return $this->belongsTo(Lawyer::class, 'lawyer_id', 'lawyer_id');
    }

    public function case()
    {
        return $this->belongsTo(LegalCase::class, 'case_id', 'case_id');
    }
}

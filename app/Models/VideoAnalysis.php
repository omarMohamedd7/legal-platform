<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;

class VideoAnalysis extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'judge_id',
        'file_path',
        'status',
        'result',
        'notes',
    ];

    /**
     * Get the judge that owns the video analysis.
     */
    public function judge()
    {
        return $this->belongsTo(Judge::class, 'judge_id', 'judge_id');
    }

    /**
     * Get the video URL.
     *
     * @return string
     */
    public function getVideoUrlAttribute()
    {
        return URL::to(Storage::url($this->file_path));
    }

    /**
     * The accessors to append to the model's array form.
     *
     * @var array
     */
    protected $appends = ['video_url'];
} 
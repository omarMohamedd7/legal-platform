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
        'video_name',
        'duration',
        'analysis_date',
        'prediction',
        'confidence',
        'summary',
        'notes',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array
     */
    protected $casts = [
        'analysis_date' => 'datetime',
        'confidence' => 'float',
        'duration' => 'integer',
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
     * Format the duration as human-readable.
     *
     * @return string
     */
    public function getFormattedDurationAttribute()
    {
        if (!$this->duration) {
            return null;
        }
        
        $minutes = floor($this->duration / 60);
        $seconds = $this->duration % 60;
        
        return sprintf('%02d:%02d', $minutes, $seconds);
    }

    /**
     * The accessors to append to the model's array form.
     *
     * @var array
     */
    protected $appends = ['video_url', 'formatted_duration'];
} 
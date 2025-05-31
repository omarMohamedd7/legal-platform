<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Carbon\Carbon;

class JudgeTask extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'judge_id',
        'title',
        'description',
        'date',
        'time',
        'status',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array
     */
    protected $casts = [
        'date' => 'date',
        'time' => 'datetime:H:i',
    ];

    /**
     * Get the judge that owns the task.
     */
    public function judge()
    {
        return $this->belongsTo(Judge::class, 'judge_id', 'judge_id');
    }

    /**
     * Check if the task is overdue (date and time have passed).
     *
     * @return bool
     */
    public function isOverdue()
    {
        $taskDateTime = Carbon::parse($this->date->format('Y-m-d') . ' ' . $this->time->format('H:i:s'));
        return $taskDateTime->isPast();
    }

    /**
     * Auto-mark task as completed if it's overdue.
     */
    public function checkAndUpdateStatus()
    {
        if ($this->status === 'pending' && $this->isOverdue()) {
            $this->status = 'completed';
            $this->save();
        }
    }
} 
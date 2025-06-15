<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Contact extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'contact_user_id',
        'last_message_date'
    ];

    protected $casts = [
        'last_message_date' => 'datetime'
    ];

    // This is a virtual attribute to match the frontend model
    protected $appends = ['name', 'role', 'last_message'];

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function contactUser()
    {
        return $this->belongsTo(User::class, 'contact_user_id');
    }

    public function messages()
    {
        return Message::where(function ($query) {
            $query->where('sender_id', $this->user_id)
                  ->where('receiver_id', $this->contact_user_id);
        })->orWhere(function ($query) {
            $query->where('sender_id', $this->contact_user_id)
                  ->where('receiver_id', $this->user_id);
        });
    }

    public function getNameAttribute()
    {
        return $this->contactUser->name;
    }

    public function getRoleAttribute()
    {
        return $this->contactUser->role;
    }

    public function getLastMessageAttribute()
    {
        return Message::where(function ($query) {
            $query->where('sender_id', $this->user_id)
                  ->where('receiver_id', $this->contact_user_id);
        })->orWhere(function ($query) {
            $query->where('sender_id', $this->contact_user_id)
                  ->where('receiver_id', $this->user_id);
        })
        ->latest()
        ->first();
    }
} 
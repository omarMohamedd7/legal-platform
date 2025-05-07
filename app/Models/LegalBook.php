<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;

class LegalBook extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'title',
        'author',
        'category',
        'description',
        'file_path',
        'image_path',
    ];

    /**
     * Get the download URL for the book.
     *
     * @return string
     */
    public function getDownloadUrlAttribute()
    {
        return URL::to(Storage::url($this->file_path));
    }

    /**
     * Get the image URL for the book cover.
     *
     * @return string|null
     */
    public function getImageUrlAttribute()
    {
        if ($this->image_path) {
            return URL::to(Storage::url($this->image_path));
        }
        return null;
    }

    /**
     * The accessors to append to the model's array form.
     *
     * @var array
     */
    protected $appends = ['download_url', 'image_url'];
} 
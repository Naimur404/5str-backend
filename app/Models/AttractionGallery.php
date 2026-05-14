<?php

namespace App\Models;

use App\Support\R2Storage;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class AttractionGallery extends Model
{
    use HasFactory;

    protected $fillable = [
        'attraction_id',
        'image_url',
        'image_path',
        'title',
        'description',
        'alt_text',
        'is_cover',
        'sort_order',
        'image_type',
        'meta_data',
        'uploaded_by',
        'is_active',
    ];

    protected $casts = [
        'is_cover' => 'boolean',
        'sort_order' => 'integer',
        'meta_data' => 'array',
        'is_active' => 'boolean',
    ];

    protected $appends = [
        'full_image_url',
        'thumbnail_url',
    ];

    /**
     * Relationships
     */
    public function attraction()
    {
        return $this->belongsTo(Attraction::class);
    }

    public function uploader()
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    /**
     * Accessors
     */
    public function getFullImageUrlAttribute()
    {
        if ($this->image_path) {
            return R2Storage::urlFromValue($this->image_path);
        }

        return R2Storage::urlFromValue($this->image_url);
    }

    public function getThumbnailUrlAttribute()
    {
        $sourcePath = R2Storage::pathFromUrl($this->image_path);
        if ($sourcePath) {
            $pathInfo = pathinfo($sourcePath);
            $thumbnailPath = $pathInfo['dirname'] . '/thumbs/' . $pathInfo['filename'] . '_thumb.' . $pathInfo['extension'];

            if (Storage::disk(R2Storage::DISK)->exists($thumbnailPath)) {
                return Storage::disk(R2Storage::DISK)->url($thumbnailPath);
            }
        }

        return $this->full_image_url;
    }

    /**
     * Scopes
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeCover($query)
    {
        return $query->where('is_cover', true);
    }

    public function scopeGallery($query)
    {
        return $query->where('image_type', 'gallery');
    }

    public function scopeOrdered($query)
    {
        return $query->orderBy('sort_order')->orderBy('created_at');
    }

    /**
     * Set as cover image (ensures only one cover per attraction)
     */
    public function setAsCover()
    {
        // Remove cover status from other images
        static::where('attraction_id', $this->attraction_id)
              ->where('id', '!=', $this->id)
              ->update(['is_cover' => false]);
        
        // Set this as cover
        $this->update(['is_cover' => true]);
        
        return $this;
    }

    /**
     * Get image dimensions from meta_data
     */
    public function getDimensions()
    {
        return $this->meta_data['dimensions'] ?? null;
    }

    /**
     * Get file size from meta_data
     */
    public function getFileSize()
    {
        return $this->meta_data['file_size'] ?? null;
    }
}

<?php

namespace App\Models;

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
        // If image_path exists and the file exists, use it
        if ($this->image_path && Storage::exists($this->image_path)) {
            return Storage::url($this->image_path);
        }
        
        // If image_url looks like a file path (no http/https), treat it as a storage path
        if ($this->image_url && !str_starts_with($this->image_url, 'http')) {
            if (Storage::exists($this->image_url)) {
                return Storage::url($this->image_url);
            }
        }
        
        // Otherwise, return the image_url as is (external URL)
        return $this->image_url;
    }

    public function getThumbnailUrlAttribute()
    {
        // Generate thumbnail URL based on image path or URL
        if ($this->image_path) {
            $pathInfo = pathinfo($this->image_path);
            $thumbnailPath = $pathInfo['dirname'] . '/thumbs/' . $pathInfo['filename'] . '_thumb.' . $pathInfo['extension'];
            
            if (Storage::exists($thumbnailPath)) {
                return Storage::url($thumbnailPath);
            }
        }
        
        // Fallback to original image
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

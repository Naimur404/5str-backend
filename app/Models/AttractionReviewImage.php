<?php

namespace App\Models;

use App\Support\R2Storage;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class AttractionReviewImage extends Model
{
    use HasFactory;

    protected $fillable = [
        'review_id',
        'image_path',
        'image_url',
        'original_filename',
        'mime_type',
        'file_size',
        'image_dimensions',
        'caption',
        'alt_text',
        'sort_order',
        'is_primary',
    ];

    protected $casts = [
        'image_dimensions' => 'array',
        'file_size' => 'integer',
        'sort_order' => 'integer',
        'is_primary' => 'boolean',
    ];

    protected $appends = [
        'full_url',
        'thumbnail_url',
    ];

    /**
     * Relationships
     */
    public function review()
    {
        return $this->belongsTo(AttractionReview::class, 'review_id');
    }

    /**
     * Accessors
     */
    public function getFullUrlAttribute()
    {
        if ($this->image_url) {
            return R2Storage::urlFromValue($this->image_url);
        }

        return R2Storage::urlFromValue($this->image_path);
    }

    public function getThumbnailUrlAttribute()
    {
        $sourcePath = R2Storage::pathFromUrl($this->image_path);
        if (! $sourcePath) {
            return $this->full_url;
        }

        $thumbnailPath = str_replace('.', '_thumb.', $sourcePath);

        if (R2Storage::storage()->exists($thumbnailPath)) {
            return R2Storage::storage()->url($thumbnailPath);
        }

        return $this->full_url;
    }

    public function getImageWidthAttribute()
    {
        return $this->image_dimensions['width'] ?? null;
    }

    public function getImageHeightAttribute()
    {
        return $this->image_dimensions['height'] ?? null;
    }

    public function getFileSizeFormattedAttribute()
    {
        if (!$this->file_size) {
            return null;
        }
        
        $units = ['B', 'KB', 'MB', 'GB'];
        $bytes = $this->file_size;
        
        for ($i = 0; $bytes > 1024 && $i < count($units) - 1; $i++) {
            $bytes /= 1024;
        }
        
        return round($bytes, 2) . ' ' . $units[$i];
    }

    /**
     * Scopes
     */
    public function scopeByReview($query, $reviewId)
    {
        return $query->where('review_id', $reviewId);
    }

    public function scopePrimary($query)
    {
        return $query->where('is_primary', true);
    }

    public function scopeOrdered($query)
    {
        return $query->orderBy('sort_order')->orderBy('created_at');
    }

    /**
     * Helper Methods
     */
    public function makePrimary()
    {
        // Remove primary flag from other images in the same review
        self::where('review_id', $this->review_id)
            ->where('id', '!=', $this->id)
            ->update(['is_primary' => false]);
        
        // Set this image as primary
        $this->update(['is_primary' => true]);
        
        return $this;
    }

    /**
     * Boot method
     */
    protected static function boot()
    {
        parent::boot();
        
        // When deleting an image, remove the file from storage
        static::deleting(function ($image) {
            $path = R2Storage::pathFromUrl($image->image_path);
            if ($path && R2Storage::storage()->exists($path)) {
                R2Storage::storage()->delete($path);

                $thumbnailPath = str_replace('.', '_thumb.', $path);
                if (R2Storage::storage()->exists($thumbnailPath)) {
                    R2Storage::storage()->delete($thumbnailPath);
                }
            }
        });
    }
}

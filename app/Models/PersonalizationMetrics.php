<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PersonalizationMetrics extends Model
{
    use HasFactory;

    protected $fillable = [
        'experiment_name',
        'user_id',
        'variant',
        'event_type',
        'event_data'
    ];

    protected $casts = [
        'event_data' => 'array'
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}

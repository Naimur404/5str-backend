<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Append-only behaviour event powering personalisation and analytics.
 * See `database/migrations/.../create_user_events_table.php` for the
 * column rationale.
 */
class UserEvent extends Model
{
    use HasFactory;

    protected $table = 'user_events';
    public $timestamps = false;

    protected $fillable = [
        'anon_id',
        'user_id',
        'event',
        'target_type',
        'target_id',
        'category_id',
        'lat_approx',
        'lng_approx',
        'user_area',
        'source',
        'payload',
        'session_id',
        'app_version',
        'platform',
        'dedup_key',
        'created_at',
    ];

    protected $casts = [
        'user_id' => 'integer',
        'target_id' => 'integer',
        'category_id' => 'integer',
        'lat_approx' => 'float',
        'lng_approx' => 'float',
        'payload' => 'array',
        'created_at' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}

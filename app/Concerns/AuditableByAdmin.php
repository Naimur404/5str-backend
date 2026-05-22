<?php

namespace App\Concerns;

use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * Standard audit-trail wiring for moderation models.
 *
 * Drop this trait onto any Eloquent model that an admin can mutate; the
 * activity_log table will get one row per create/update/delete with the
 * acting user as `causer_id` (set by Spatie's auth resolver), only the
 * dirty attributes captured, and the model class as `log_name`.
 *
 * The host class can override `auditableAttributes()` to expose a
 * specific allow-list of fields. Defaults to `$fillable`.
 *
 * @phpstan-require-extends \Illuminate\Database\Eloquent\Model
 */
trait AuditableByAdmin
{
    use LogsActivity;

    public function getActivitylogOptions(): LogOptions
    {
        $attributes = method_exists($this, 'auditableAttributes')
            ? $this->auditableAttributes()
            : ($this->fillable ?? []);

        return LogOptions::defaults()
            ->logOnly($attributes)
            ->logOnlyDirty()
            ->dontLogIfAttributesChangedOnly(['updated_at', 'deleted_at'])
            ->useLogName('audit');
    }
}

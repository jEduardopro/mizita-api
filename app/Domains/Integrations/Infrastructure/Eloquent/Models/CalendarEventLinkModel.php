<?php

declare(strict_types=1);

namespace App\Domains\Integrations\Infrastructure\Eloquent\Models;

use App\Domains\Appointments\Infrastructure\Eloquent\Models\AppointmentModel;
use App\Domains\Businesses\Infrastructure\Eloquent\Models\BusinessModel;
use App\Domains\Integrations\Infrastructure\Eloquent\Factories\CalendarEventLinkModelFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable(['uuid', 'business_id', 'calendar_connection_id', 'appointment_id', 'external_event_id'])]
class CalendarEventLinkModel extends Model
{
    use HasFactory;
    use HasUuids;
    use SoftDeletes;

    protected $table = 'calendar_event_links';

    /**
     * @return array<int, string>
     */
    public function uniqueIds(): array
    {
        return ['uuid'];
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    /**
     * @return BelongsTo<BusinessModel, $this>
     */
    public function business(): BelongsTo
    {
        return $this->belongsTo(BusinessModel::class, 'business_id');
    }

    /**
     * @return BelongsTo<CalendarConnectionModel, $this>
     */
    public function connection(): BelongsTo
    {
        return $this->belongsTo(CalendarConnectionModel::class, 'calendar_connection_id')->withTrashed();
    }

    /**
     * @return BelongsTo<AppointmentModel, $this>
     */
    public function appointment(): BelongsTo
    {
        return $this->belongsTo(AppointmentModel::class, 'appointment_id')->withTrashed();
    }

    protected static function newFactory(): CalendarEventLinkModelFactory
    {
        return CalendarEventLinkModelFactory::new();
    }
}

<?php

declare(strict_types=1);

namespace App\Domains\Notifications\Infrastructure\Eloquent\Models;

use App\Domains\Appointments\Infrastructure\Eloquent\Models\AppointmentModel;
use App\Domains\Businesses\Infrastructure\Eloquent\Models\BusinessModel;
use App\Domains\Notifications\Infrastructure\Eloquent\Casts\UtcInstant;
use App\Domains\Notifications\Infrastructure\Eloquent\Factories\StaffNotificationModelFactory;
use App\Domains\Notifications\ValueObjects\NotificationType;
use App\Domains\Staff\Infrastructure\Eloquent\Models\StaffMemberModel;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable(['uuid', 'business_id', 'recipient_staff_member_id', 'type', 'appointment_id', 'subject_staff_member_id', 'read_at', 'created_at', 'updated_at'])]
class StaffNotificationModel extends Model
{
    use HasFactory;
    use HasUuids;
    use SoftDeletes;

    protected $table = 'staff_notifications';

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
     * @return BelongsTo<StaffMemberModel, $this>
     */
    public function recipient(): BelongsTo
    {
        return $this->belongsTo(StaffMemberModel::class, 'recipient_staff_member_id')->withTrashed();
    }

    /**
     * @return BelongsTo<AppointmentModel, $this>
     */
    public function appointment(): BelongsTo
    {
        return $this->belongsTo(AppointmentModel::class, 'appointment_id')->withTrashed();
    }

    /**
     * @return BelongsTo<StaffMemberModel, $this>
     */
    public function subject(): BelongsTo
    {
        return $this->belongsTo(StaffMemberModel::class, 'subject_staff_member_id')->withTrashed();
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => NotificationType::class,
            'read_at' => UtcInstant::class,
            'created_at' => UtcInstant::class,
            'updated_at' => UtcInstant::class,
        ];
    }

    protected static function newFactory(): StaffNotificationModelFactory
    {
        return StaffNotificationModelFactory::new();
    }
}

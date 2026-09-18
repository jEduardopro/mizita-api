<?php

declare(strict_types=1);

namespace App\Domains\Appointments\Infrastructure\Eloquent\Models;

use App\Domains\Appointments\Infrastructure\Eloquent\Casts\UtcInstant;
use App\Domains\Appointments\Infrastructure\Eloquent\Factories\AppointmentModelFactory;
use App\Domains\Businesses\Infrastructure\Eloquent\Models\BusinessModel;
use App\Domains\Customers\Infrastructure\Eloquent\Models\CustomerModel;
use App\Domains\Services\Infrastructure\Eloquent\Models\ServiceModel;
use App\Domains\Staff\Infrastructure\Eloquent\Models\StaffMemberModel;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable(['uuid', 'business_id', 'customer_id', 'service_id', 'staff_member_id', 'starts_at', 'ends_at', 'notes'])]
class AppointmentModel extends Model
{
    use HasFactory;
    use HasUuids;
    use SoftDeletes;

    protected $table = 'appointments';

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

    public function businessKey(): int
    {
        return (int) $this->business_id;
    }

    /**
     * @return BelongsTo<BusinessModel, $this>
     */
    public function business(): BelongsTo
    {
        return $this->belongsTo(BusinessModel::class, 'business_id');
    }

    /**
     * @return BelongsTo<CustomerModel, $this>
     */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(CustomerModel::class, 'customer_id')->withTrashed();
    }

    /**
     * @return BelongsTo<ServiceModel, $this>
     */
    public function service(): BelongsTo
    {
        return $this->belongsTo(ServiceModel::class, 'service_id')->withTrashed();
    }

    /**
     * @return BelongsTo<StaffMemberModel, $this>
     */
    public function staffMember(): BelongsTo
    {
        return $this->belongsTo(StaffMemberModel::class, 'staff_member_id')->withTrashed();
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'starts_at' => UtcInstant::class,
            'ends_at' => UtcInstant::class,
        ];
    }

    protected static function newFactory(): AppointmentModelFactory
    {
        return AppointmentModelFactory::new();
    }
}

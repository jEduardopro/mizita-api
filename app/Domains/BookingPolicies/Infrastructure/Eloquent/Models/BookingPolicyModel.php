<?php

declare(strict_types=1);

namespace App\Domains\BookingPolicies\Infrastructure\Eloquent\Models;

use App\Domains\BookingPolicies\Infrastructure\Eloquent\Factories\BookingPolicyModelFactory;
use App\Domains\BookingPolicies\ValueObjects\ContactFieldRequirement;
use App\Domains\Businesses\Infrastructure\Eloquent\Models\BusinessModel;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable([
    'uuid',
    'business_id',
    'lead_time_minutes',
    'booking_window_minutes',
    'slot_granularity_minutes',
    'cancellation_window_minutes',
    'policy_message',
    'display_on_booking_page',
    'phone_field',
    'email_field',
    'address_field',
])]
class BookingPolicyModel extends Model
{
    use HasFactory;
    use HasUuids;
    use SoftDeletes;

    protected $table = 'booking_policies';

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
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'lead_time_minutes' => 'integer',
            'booking_window_minutes' => 'integer',
            'slot_granularity_minutes' => 'integer',
            'cancellation_window_minutes' => 'integer',
            'display_on_booking_page' => 'boolean',
            'phone_field' => ContactFieldRequirement::class,
            'email_field' => ContactFieldRequirement::class,
            'address_field' => ContactFieldRequirement::class,
        ];
    }

    protected static function newFactory(): BookingPolicyModelFactory
    {
        return BookingPolicyModelFactory::new();
    }
}

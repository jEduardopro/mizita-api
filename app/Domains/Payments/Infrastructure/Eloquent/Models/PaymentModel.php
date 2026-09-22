<?php

declare(strict_types=1);

namespace App\Domains\Payments\Infrastructure\Eloquent\Models;

use App\Domains\Appointments\Infrastructure\Eloquent\Models\AppointmentModel;
use App\Domains\Businesses\Infrastructure\Eloquent\Models\BusinessModel;
use App\Domains\Payments\Infrastructure\Eloquent\Factories\PaymentModelFactory;
use App\Domains\Payments\ValueObjects\DiscountType;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable([
    'uuid',
    'business_id',
    'appointment_id',
    'currency_code',
    'subtotal_cents',
    'discount_type',
    'discount_value',
    'discount_amount_cents',
    'total_cents',
    'paid_cents',
])]
class PaymentModel extends Model
{
    use HasFactory;
    use HasUuids;
    use SoftDeletes;

    protected $table = 'payments';

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
     * @return BelongsTo<AppointmentModel, $this>
     */
    public function appointment(): BelongsTo
    {
        return $this->belongsTo(AppointmentModel::class, 'appointment_id')->withTrashed();
    }

    /**
     * @return HasMany<PaymentItemModel, $this>
     */
    public function items(): HasMany
    {
        return $this->hasMany(PaymentItemModel::class, 'payment_id')
            ->orderBy('position')
            ->orderBy('id');
    }

    /**
     * @return HasMany<PaymentTransactionModel, $this>
     */
    public function transactions(): HasMany
    {
        return $this->hasMany(PaymentTransactionModel::class, 'payment_id')
            ->orderBy('processed_at')
            ->orderBy('id');
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'discount_type' => DiscountType::class,
            'subtotal_cents' => 'integer',
            'discount_value' => 'integer',
            'discount_amount_cents' => 'integer',
            'total_cents' => 'integer',
            'paid_cents' => 'integer',
        ];
    }

    protected static function newFactory(): PaymentModelFactory
    {
        return PaymentModelFactory::new();
    }
}

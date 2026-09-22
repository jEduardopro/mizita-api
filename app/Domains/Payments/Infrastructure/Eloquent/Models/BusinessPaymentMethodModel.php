<?php

declare(strict_types=1);

namespace App\Domains\Payments\Infrastructure\Eloquent\Models;

use App\Domains\Businesses\Infrastructure\Eloquent\Models\BusinessModel;
use App\Domains\Payments\Infrastructure\Eloquent\Factories\BusinessPaymentMethodModelFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable([
    'uuid',
    'business_id',
    'payment_method_id',
    'enabled',
    'position',
])]
class BusinessPaymentMethodModel extends Model
{
    use HasFactory;
    use HasUuids;
    use SoftDeletes;

    protected $table = 'business_payment_methods';

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
     * @return BelongsTo<PaymentMethodModel, $this>
     */
    public function paymentMethod(): BelongsTo
    {
        return $this->belongsTo(PaymentMethodModel::class, 'payment_method_id')->withTrashed();
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'enabled' => 'boolean',
            'position' => 'integer',
        ];
    }

    protected static function newFactory(): BusinessPaymentMethodModelFactory
    {
        return BusinessPaymentMethodModelFactory::new();
    }
}

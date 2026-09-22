<?php

declare(strict_types=1);

namespace App\Domains\Payments\Infrastructure\Eloquent\Models;

use App\Domains\Payments\Infrastructure\Eloquent\Casts\UtcInstant;
use App\Domains\Payments\Infrastructure\Eloquent\Factories\PaymentTransactionModelFactory;
use App\Models\User;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable([
    'uuid',
    'payment_id',
    'payment_method_id',
    'amount_cents',
    'external_reference',
    'processed_at',
    'voided_at',
    'voided_by_account_id',
])]
class PaymentTransactionModel extends Model
{
    use HasFactory;
    use HasUuids;
    use SoftDeletes;

    protected $table = 'payment_transactions';

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
     * @return BelongsTo<PaymentModel, $this>
     */
    public function payment(): BelongsTo
    {
        return $this->belongsTo(PaymentModel::class, 'payment_id');
    }

    /**
     * @return BelongsTo<PaymentMethodModel, $this>
     */
    public function paymentMethod(): BelongsTo
    {
        return $this->belongsTo(PaymentMethodModel::class, 'payment_method_id')->withTrashed();
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function voidedByAccount(): BelongsTo
    {
        return $this->belongsTo(User::class, 'voided_by_account_id');
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'amount_cents' => 'integer',
            'processed_at' => UtcInstant::class,
            'voided_at' => UtcInstant::class,
        ];
    }

    protected static function newFactory(): PaymentTransactionModelFactory
    {
        return PaymentTransactionModelFactory::new();
    }
}

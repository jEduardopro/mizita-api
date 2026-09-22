<?php

declare(strict_types=1);

namespace App\Domains\Payments\Infrastructure\Eloquent\Models;

use App\Domains\Payments\Infrastructure\Eloquent\Factories\PaymentItemModelFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable([
    'uuid',
    'payment_id',
    'name',
    'amount_cents',
    'position',
])]
class PaymentItemModel extends Model
{
    use HasFactory;
    use HasUuids;
    use SoftDeletes;

    protected $table = 'payment_items';

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
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'amount_cents' => 'integer',
            'position' => 'integer',
        ];
    }

    protected static function newFactory(): PaymentItemModelFactory
    {
        return PaymentItemModelFactory::new();
    }
}

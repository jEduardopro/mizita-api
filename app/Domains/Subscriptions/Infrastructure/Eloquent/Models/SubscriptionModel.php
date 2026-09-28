<?php

declare(strict_types=1);

namespace App\Domains\Subscriptions\Infrastructure\Eloquent\Models;

use App\Domains\Businesses\Infrastructure\Eloquent\Models\BusinessModel;
use App\Domains\Subscriptions\Infrastructure\Eloquent\Casts\UtcInstant;
use App\Domains\Subscriptions\Infrastructure\Eloquent\Factories\SubscriptionModelFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable([
    'uuid',
    'business_id',
    'plan',
    'status',
    'starts_at',
    'ends_at',
    'price_amount',
    'price_currency',
])]
class SubscriptionModel extends Model
{
    use HasFactory;
    use HasUuids;
    use SoftDeletes;

    protected $table = 'subscriptions';

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
        return $this->belongsTo(BusinessModel::class, 'business_id')->withTrashed();
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'starts_at' => UtcInstant::class,
            'ends_at' => UtcInstant::class,
            'price_amount' => 'integer',
        ];
    }

    protected static function newFactory(): SubscriptionModelFactory
    {
        return SubscriptionModelFactory::new();
    }
}

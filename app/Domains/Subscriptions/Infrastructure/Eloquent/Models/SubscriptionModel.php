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
    'plan_id',
    'status',
    'stripe_customer_id',
    'stripe_subscription_id',
    'started_at',
    'current_period_ends_at',
    'canceled_at',
    'payment_failed_at',
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
     * @return BelongsTo<PlanModel, $this>
     */
    public function plan(): BelongsTo
    {
        return $this->belongsTo(PlanModel::class, 'plan_id')->withTrashed();
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'started_at' => UtcInstant::class,
            'current_period_ends_at' => UtcInstant::class,
            'canceled_at' => UtcInstant::class,
            'payment_failed_at' => UtcInstant::class,
        ];
    }

    protected static function newFactory(): SubscriptionModelFactory
    {
        return SubscriptionModelFactory::new();
    }
}

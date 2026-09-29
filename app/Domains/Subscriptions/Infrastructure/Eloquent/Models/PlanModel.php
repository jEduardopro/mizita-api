<?php

declare(strict_types=1);

namespace App\Domains\Subscriptions\Infrastructure\Eloquent\Models;

use App\Domains\Subscriptions\Infrastructure\Eloquent\Factories\PlanModelFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable([
    'uuid',
    'key',
    'name',
    'price_amount',
    'price_currency',
    'billing_interval',
    'trial_days',
    'stripe_price_id',
])]
class PlanModel extends Model
{
    use HasFactory;
    use HasUuids;
    use SoftDeletes;

    protected $table = 'plans';

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
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'price_amount' => 'integer',
            'trial_days' => 'integer',
        ];
    }

    protected static function newFactory(): PlanModelFactory
    {
        return PlanModelFactory::new();
    }
}

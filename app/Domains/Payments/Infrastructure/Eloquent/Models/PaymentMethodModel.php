<?php

declare(strict_types=1);

namespace App\Domains\Payments\Infrastructure\Eloquent\Models;

use App\Domains\Payments\Infrastructure\Eloquent\Factories\PaymentMethodModelFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable([
    'uuid',
    'code',
    'position',
    'active',
    'requires_integration',
])]
class PaymentMethodModel extends Model
{
    use HasFactory;
    use HasUuids;
    use SoftDeletes;

    protected $table = 'payment_methods';

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
            'position' => 'integer',
            'active' => 'boolean',
            'requires_integration' => 'boolean',
        ];
    }

    protected static function newFactory(): PaymentMethodModelFactory
    {
        return PaymentMethodModelFactory::new();
    }
}

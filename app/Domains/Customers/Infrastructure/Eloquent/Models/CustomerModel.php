<?php

declare(strict_types=1);

namespace App\Domains\Customers\Infrastructure\Eloquent\Models;

use App\Domains\Customers\Infrastructure\Eloquent\Factories\CustomerModelFactory;
use App\Shared\Infrastructure\Concerns\BelongsToBusiness;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable(['uuid', 'business_id', 'name', 'email', 'phone'])]
class CustomerModel extends Model
{
    use BelongsToBusiness;
    use HasFactory;
    use HasUuids;
    use SoftDeletes;

    protected $table = 'customers';

    /**
     * Overridden so the primary key stays an auto-incrementing int; uuid carries
     * the public identity.
     *
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

        ];
    }

    protected static function newFactory(): CustomerModelFactory
    {
        return CustomerModelFactory::new();
    }
}

<?php

declare(strict_types=1);

namespace App\Domains\Availability\Infrastructure\Eloquent\Models;

use App\Domains\Availability\Infrastructure\Eloquent\Factories\ScheduleRuleModelFactory;
use App\Domains\Businesses\Infrastructure\Eloquent\Models\BusinessModel;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable(['uuid', 'business_id', 'owner_type', 'owner_id', 'weekday', 'starts_at', 'ends_at'])]
class ScheduleRuleModel extends Model
{
    use HasFactory;
    use HasUuids;
    use SoftDeletes;

    protected $table = 'schedule_rules';

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
            'weekday' => 'integer',
        ];
    }

    protected static function newFactory(): ScheduleRuleModelFactory
    {
        return ScheduleRuleModelFactory::new();
    }
}

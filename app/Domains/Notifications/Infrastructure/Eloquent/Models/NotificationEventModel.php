<?php

declare(strict_types=1);

namespace App\Domains\Notifications\Infrastructure\Eloquent\Models;

use App\Domains\Businesses\Infrastructure\Eloquent\Models\BusinessModel;
use App\Domains\Notifications\Infrastructure\Eloquent\Casts\UtcInstant;
use App\Domains\Notifications\Infrastructure\Eloquent\Factories\NotificationEventModelFactory;
use App\Domains\Notifications\ValueObjects\NotificationSubjectType;
use App\Domains\Notifications\ValueObjects\NotificationType;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable(['uuid', 'business_id', 'type', 'subject_type', 'subject_id', 'payload', 'idempotency_key', 'occurred_at', 'created_at', 'updated_at'])]
class NotificationEventModel extends Model
{
    use HasFactory;
    use HasUuids;
    use SoftDeletes;

    protected $table = 'notification_events';

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
            'type' => NotificationType::class,
            'subject_type' => NotificationSubjectType::class,
            'payload' => 'array',
            'occurred_at' => UtcInstant::class,
            'created_at' => UtcInstant::class,
            'updated_at' => UtcInstant::class,
        ];
    }

    protected static function newFactory(): NotificationEventModelFactory
    {
        return NotificationEventModelFactory::new();
    }
}

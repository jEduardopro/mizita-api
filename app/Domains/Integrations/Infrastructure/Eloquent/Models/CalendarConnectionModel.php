<?php

declare(strict_types=1);

namespace App\Domains\Integrations\Infrastructure\Eloquent\Models;

use App\Domains\Businesses\Infrastructure\Eloquent\Models\BusinessModel;
use App\Domains\Integrations\Infrastructure\Eloquent\Casts\UtcInstant;
use App\Domains\Integrations\Infrastructure\Eloquent\Factories\CalendarConnectionModelFactory;
use App\Domains\Integrations\ValueObjects\CalendarProvider;
use App\Domains\Integrations\ValueObjects\ConnectionStatus;
use App\Domains\Staff\Infrastructure\Eloquent\Models\StaffMemberModel;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable([
    'uuid',
    'business_id',
    'staff_member_id',
    'provider',
    'account_email',
    'access_token',
    'refresh_token',
    'access_token_expires_at',
    'external_calendar_id',
    'status',
    'connected_at',
])]
#[Hidden(['access_token', 'refresh_token'])]
class CalendarConnectionModel extends Model
{
    use HasFactory;
    use HasUuids;
    use SoftDeletes;

    protected $table = 'calendar_connections';

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
     * @return BelongsTo<StaffMemberModel, $this>
     */
    public function staffMember(): BelongsTo
    {
        return $this->belongsTo(StaffMemberModel::class, 'staff_member_id')->withTrashed();
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'provider' => CalendarProvider::class,
            'status' => ConnectionStatus::class,
            'access_token' => 'encrypted',
            'refresh_token' => 'encrypted',
            'access_token_expires_at' => UtcInstant::class,
            'connected_at' => UtcInstant::class,
        ];
    }

    protected static function newFactory(): CalendarConnectionModelFactory
    {
        return CalendarConnectionModelFactory::new();
    }
}

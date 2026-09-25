<?php

declare(strict_types=1);

namespace App\Domains\Integrations\Infrastructure\Eloquent;

use App\Domains\Integrations\Contracts\CalendarEventLinkRepository;
use App\Domains\Integrations\Entities\CalendarEventLink;
use App\Domains\Integrations\Infrastructure\Eloquent\Mappers\CalendarEventLinkMapper;
use App\Domains\Integrations\Infrastructure\Eloquent\Models\CalendarEventLinkModel;
use App\Shared\Contracts\BusinessTeamKey;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Facades\DB;
use RuntimeException;

final class EloquentCalendarEventLinkRepository implements CalendarEventLinkRepository
{
    private const BUSINESSES_TABLE = 'businesses';

    private const CONNECTIONS_TABLE = 'calendar_connections';

    private const APPOINTMENTS_TABLE = 'appointments';

    private const RELATIONS = ['connection:id,uuid', 'appointment:id,uuid'];

    public function __construct(
        private readonly CalendarEventLinkMapper $mapper,
        private readonly BusinessTeamKey $businessKeys,
    ) {}

    /**
     * @return list<CalendarEventLink>
     */
    public function forAppointment(string $businessId, string $appointmentId): array
    {
        return $this->ofBusiness($businessId)
            ->with(self::RELATIONS)
            ->whereIn('appointment_id', static fn (QueryBuilder $query) => $query
                ->select('id')
                ->from(self::APPOINTMENTS_TABLE)
                ->where('uuid', $appointmentId))
            ->orderBy('id')
            ->get()
            ->map(fn (CalendarEventLinkModel $model): CalendarEventLink => $this->mapper->toEntity($model, $businessId))
            ->values()
            ->all();
    }

    /**
     * @return list<string>
     */
    public function appointmentIdsLinkedTo(string $businessId, string $connectionId): array
    {
        return DB::table(self::APPOINTMENTS_TABLE)
            ->whereIn('id', $this->ofConnection($businessId, $connectionId)->select('appointment_id'))
            ->orderBy('id')
            ->pluck('uuid')
            ->map(static fn (mixed $uuid): string => (string) $uuid)
            ->values()
            ->all();
    }

    public function save(CalendarEventLink $link): void
    {
        $businessKey = $this->businessKeys->teamKeyFor($link->businessId);

        CalendarEventLinkModel::query()->updateOrCreate(
            ['uuid' => $link->id],
            $this->mapper->toAttributes(
                $link,
                $businessKey,
                $this->keyIn(self::CONNECTIONS_TABLE, $businessKey, $link->connectionId),
                $this->keyIn(self::APPOINTMENTS_TABLE, $businessKey, $link->appointmentId),
            ),
        );
    }

    public function delete(string $businessId, string $id): void
    {
        $this->ofBusiness($businessId)->where('uuid', $id)->delete();
    }

    public function deleteForConnection(string $businessId, string $connectionId): void
    {
        $this->ofConnection($businessId, $connectionId)->delete();
    }

    /**
     * @return Builder<CalendarEventLinkModel>
     */
    private function ofConnection(string $businessId, string $connectionId): Builder
    {
        return $this->ofBusiness($businessId)
            ->whereIn('calendar_connection_id', static fn (QueryBuilder $query) => $query
                ->select('id')
                ->from(self::CONNECTIONS_TABLE)
                ->where('uuid', $connectionId));
    }

    /**
     * @return Builder<CalendarEventLinkModel>
     */
    private function ofBusiness(string $businessId): Builder
    {
        return CalendarEventLinkModel::query()->whereIn(
            'business_id',
            static fn (QueryBuilder $query) => $query
                ->select('id')
                ->from(self::BUSINESSES_TABLE)
                ->where('uuid', $businessId),
        );
    }

    private function keyIn(string $table, int $businessKey, string $uuid): int
    {
        $key = DB::table($table)
            ->where('business_id', $businessKey)
            ->where('uuid', $uuid)
            ->value('id');

        if ($key === null) {
            throw new RuntimeException("No row in [{$table}] carries uuid [{$uuid}] for this business.");
        }

        return (int) $key;
    }
}

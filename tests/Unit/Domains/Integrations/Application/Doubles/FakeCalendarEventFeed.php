<?php

declare(strict_types=1);

namespace Tests\Unit\Domains\Integrations\Application\Doubles;

use App\Domains\Integrations\Contracts\CalendarEventFeed;
use App\Domains\Integrations\Entities\CalendarConnection;
use App\Domains\Integrations\ValueObjects\ExternalCalendarEvent;
use DateTimeImmutable;
use Throwable;

final class FakeCalendarEventFeed implements CalendarEventFeed
{
    /**
     * @var list<array{connectionId: string, from: DateTimeImmutable, to: DateTimeImmutable}>
     */
    public array $requests = [];

    /**
     * @var list<ExternalCalendarEvent>
     */
    private array $events;

    private ?Throwable $failure = null;

    public function __construct(ExternalCalendarEvent ...$events)
    {
        $this->events = array_values($events);
    }

    public function failWith(Throwable $failure): self
    {
        $this->failure = $failure;

        return $this;
    }

    public function eventsBetween(CalendarConnection $connection, DateTimeImmutable $from, DateTimeImmutable $to): array
    {
        $this->requests[] = ['connectionId' => $connection->id, 'from' => $from, 'to' => $to];

        if ($this->failure !== null) {
            throw $this->failure;
        }

        return $this->events;
    }
}

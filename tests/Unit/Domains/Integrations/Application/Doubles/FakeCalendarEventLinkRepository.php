<?php

declare(strict_types=1);

namespace Tests\Unit\Domains\Integrations\Application\Doubles;

use App\Domains\Integrations\Contracts\CalendarEventLinkRepository;
use App\Domains\Integrations\Entities\CalendarEventLink;
use Tests\Support\FakeTransactionManager;

final class FakeCalendarEventLinkRepository implements CalendarEventLinkRepository
{
    /**
     * @var array<string, CalendarEventLink>
     */
    private array $links = [];

    /**
     * @var list<array{businessId: string, appointmentId: string}>
     */
    public array $lookups = [];

    /**
     * @var list<array{businessId: string, connectionId: string}>
     */
    public array $connectionLookups = [];

    /**
     * @var list<array{id: string, businessId: string, connectionId: string, appointmentId: string, externalEventId: string}>
     */
    public array $saved = [];

    /**
     * @var list<array{businessId: string, id: string}>
     */
    public array $deleted = [];

    /**
     * @var list<array{businessId: string, connectionId: string, insideTransaction: bool}>
     */
    public array $deletedForConnection = [];

    public function __construct(
        private readonly IntegrationsJournal $journal = new IntegrationsJournal,
        private readonly ?FakeTransactionManager $transactions = null,
    ) {}

    public function store(CalendarEventLink ...$links): self
    {
        foreach ($links as $link) {
            $this->links[$link->id] = $link;
        }

        return $this;
    }

    /**
     * @return list<CalendarEventLink>
     */
    public function all(): array
    {
        return array_values($this->links);
    }

    public function forAppointment(string $businessId, string $appointmentId): array
    {
        $this->journal->record('links.forAppointment');
        $this->lookups[] = ['businessId' => $businessId, 'appointmentId' => $appointmentId];

        return array_values(array_filter(
            $this->links,
            static fn (CalendarEventLink $link): bool => $link->businessId === $businessId && $link->appointmentId === $appointmentId,
        ));
    }

    public function appointmentIdsLinkedTo(string $businessId, string $connectionId): array
    {
        $this->journal->record('links.appointmentIdsLinkedTo');
        $this->connectionLookups[] = ['businessId' => $businessId, 'connectionId' => $connectionId];

        $appointmentIds = [];

        foreach ($this->links as $link) {
            if ($link->businessId === $businessId && $link->belongsTo($connectionId)) {
                $appointmentIds[] = $link->appointmentId;
            }
        }

        return array_values(array_unique($appointmentIds));
    }

    public function save(CalendarEventLink $link): void
    {
        $this->journal->record('links.save');

        $this->links[$link->id] = $link;
        $this->saved[] = [
            'id' => $link->id,
            'businessId' => $link->businessId,
            'connectionId' => $link->connectionId,
            'appointmentId' => $link->appointmentId,
            'externalEventId' => $link->externalEventId(),
        ];
    }

    public function delete(string $businessId, string $id): void
    {
        $this->journal->record('links.delete');
        $this->deleted[] = ['businessId' => $businessId, 'id' => $id];

        if (($this->links[$id] ?? null)?->businessId === $businessId) {
            unset($this->links[$id]);
        }
    }

    public function deleteForConnection(string $businessId, string $connectionId): void
    {
        $this->journal->record('links.deleteForConnection');
        $this->deletedForConnection[] = [
            'businessId' => $businessId,
            'connectionId' => $connectionId,
            'insideTransaction' => $this->transactions?->isRunning() === true,
        ];

        $this->links = array_filter(
            $this->links,
            static fn (CalendarEventLink $link): bool => $link->businessId !== $businessId || ! $link->belongsTo($connectionId),
        );
    }
}

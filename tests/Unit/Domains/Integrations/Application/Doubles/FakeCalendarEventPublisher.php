<?php

declare(strict_types=1);

namespace Tests\Unit\Domains\Integrations\Application\Doubles;

use App\Domains\Integrations\Contracts\CalendarEventPublisher;
use App\Domains\Integrations\Entities\CalendarConnection;
use App\Domains\Integrations\Exceptions\CalendarAuthorizationRevoked;
use App\Domains\Integrations\ValueObjects\CalendarEventDraft;
use Throwable;

final class FakeCalendarEventPublisher implements CalendarEventPublisher
{
    /**
     * @var list<array{connectionId: string, knownEventId: ?string, draft: CalendarEventDraft}>
     */
    public array $published = [];

    /**
     * @var list<array{connectionId: string, externalEventId: string}>
     */
    public array $withdrawn = [];

    /**
     * @var list<string>
     */
    private array $answers = [];

    /**
     * @var list<string>
     */
    private array $revokedConnectionIds = [];

    private ?Throwable $withdrawFailure = null;

    public function __construct(
        private readonly IntegrationsJournal $journal = new IntegrationsJournal,
    ) {}

    public function answerWith(string ...$externalEventIds): self
    {
        $this->answers = array_values($externalEventIds);

        return $this;
    }

    public function revokeFor(string $connectionId): self
    {
        $this->revokedConnectionIds[] = $connectionId;

        return $this;
    }

    public function failWithdrawingWith(Throwable $failure): self
    {
        $this->withdrawFailure = $failure;

        return $this;
    }

    public function publish(CalendarConnection $connection, ?string $knownEventId, CalendarEventDraft $draft): string
    {
        $this->journal->record('publisher.publish');
        $this->refuseWhenRevoked($connection);

        $this->published[] = ['connectionId' => $connection->id, 'knownEventId' => $knownEventId, 'draft' => $draft];

        return array_shift($this->answers) ?? $knownEventId ?? IntegrationsFixtures::EXTERNAL_EVENT_ID;
    }

    public function withdraw(CalendarConnection $connection, string $externalEventId): void
    {
        $this->journal->record('publisher.withdraw');
        $this->refuseWhenRevoked($connection);

        if ($this->withdrawFailure !== null) {
            throw $this->withdrawFailure;
        }

        $this->withdrawn[] = ['connectionId' => $connection->id, 'externalEventId' => $externalEventId];
    }

    private function refuseWhenRevoked(CalendarConnection $connection): void
    {
        if (in_array($connection->id, $this->revokedConnectionIds, true)) {
            throw CalendarAuthorizationRevoked::forConnection($connection->id);
        }
    }
}

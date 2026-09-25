<?php

declare(strict_types=1);

namespace Tests\Unit\Domains\Integrations\Application\Doubles;

use App\Domains\Integrations\Contracts\CalendarConnectionRepository;
use App\Domains\Integrations\Entities\CalendarConnection;
use App\Domains\Integrations\Exceptions\CalendarConnectionNotFound;
use App\Domains\Integrations\ValueObjects\CalendarProvider;
use App\Domains\Integrations\ValueObjects\CalendarTokens;
use App\Domains\Integrations\ValueObjects\ConnectionStatus;
use Tests\Support\FakeTransactionManager;
use Throwable;

final class FakeCalendarConnectionRepository implements CalendarConnectionRepository
{
    /**
     * @var array<string, CalendarConnection>
     */
    private array $live = [];

    /**
     * @var array<string, CalendarConnection>
     */
    private array $disconnected = [];

    /**
     * @var array<string, string>
     */
    private array $persistedAccountEmails = [];

    /**
     * @var list<array{businessId: string, staffMemberId: string, provider: CalendarProvider}>
     */
    public array $staffMemberLookups = [];

    /**
     * @var list<array{businessId: string, id: string}>
     */
    public array $businessLookups = [];

    /**
     * @var list<array{provider: CalendarProvider, accountEmail: string}>
     */
    public array $accountLookups = [];

    /**
     * @var list<array{connection: CalendarConnection, status: ConnectionStatus, tokens: CalendarTokens}>
     */
    public array $savedWithTokens = [];

    /**
     * @var list<array{id: string, status: ConnectionStatus}>
     */
    public array $updated = [];

    /**
     * @var list<array{businessId: string, id: string, insideTransaction: bool}>
     */
    public array $deleted = [];

    private ?Throwable $saveFailure = null;

    private ?Throwable $deleteFailure = null;

    private ?Throwable $accountLookupFailure = null;

    public function __construct(
        private readonly IntegrationsJournal $journal = new IntegrationsJournal,
        private readonly ?FakeTransactionManager $transactions = null,
    ) {}

    public function store(CalendarConnection ...$connections): self
    {
        foreach ($connections as $connection) {
            $this->persist($connection);
        }

        return $this;
    }

    public function storeDisconnected(CalendarConnection ...$connections): self
    {
        foreach ($connections as $connection) {
            $this->disconnected[$connection->id] = $connection;
        }

        return $this;
    }

    public function failSavingWith(Throwable $failure): void
    {
        $this->saveFailure = $failure;
    }

    public function failDeletingWith(Throwable $failure): void
    {
        $this->deleteFailure = $failure;
    }

    public function failAccountLookupWith(Throwable $failure): void
    {
        $this->accountLookupFailure = $failure;
    }

    public function stored(string $id): ?CalendarConnection
    {
        return $this->live[$id] ?? null;
    }

    public function findForStaffMember(string $businessId, string $staffMemberId, CalendarProvider $provider): ?CalendarConnection
    {
        $this->journal->record('connections.findForStaffMember');
        $this->staffMemberLookups[] = ['businessId' => $businessId, 'staffMemberId' => $staffMemberId, 'provider' => $provider];

        foreach ($this->live as $connection) {
            if ($connection->businessId === $businessId
                && $connection->staffMemberId === $staffMemberId
                && $connection->provider === $provider) {
                return $connection;
            }
        }

        return null;
    }

    public function findInBusiness(string $businessId, string $id): ?CalendarConnection
    {
        $this->journal->record('connections.findInBusiness');
        $this->businessLookups[] = ['businessId' => $businessId, 'id' => $id];

        $connection = $this->live[$id] ?? null;

        return $connection?->businessId === $businessId ? $connection : null;
    }

    public function findDisconnected(string $businessId, string $id): ?CalendarConnection
    {
        $this->journal->record('connections.findDisconnected');

        $connection = $this->disconnected[$id] ?? null;

        return $connection?->businessId === $businessId ? $connection : null;
    }

    public function existsLiveForAccount(CalendarProvider $provider, string $accountEmail): bool
    {
        $this->journal->record('connections.existsLiveForAccount');
        $this->accountLookups[] = ['provider' => $provider, 'accountEmail' => $accountEmail];

        if ($this->accountLookupFailure !== null) {
            throw $this->accountLookupFailure;
        }

        foreach ($this->live as $id => $connection) {
            if ($connection->provider === $provider
                && mb_strtolower($this->persistedAccountEmails[$id]) === mb_strtolower(trim($accountEmail))) {
                return true;
            }
        }

        return false;
    }

    public function saveWithTokens(CalendarConnection $connection, CalendarTokens $tokens): void
    {
        $this->journal->record('connections.saveWithTokens');

        if ($this->saveFailure !== null) {
            throw $this->saveFailure;
        }

        $this->persist($connection);
        $this->savedWithTokens[] = ['connection' => $connection, 'status' => $connection->status(), 'tokens' => $tokens];
    }

    public function update(CalendarConnection $connection): void
    {
        $this->journal->record('connections.update');

        if (! isset($this->live[$connection->id])) {
            throw CalendarConnectionNotFound::withId($connection->id);
        }

        $this->persist($connection);
        $this->updated[] = ['id' => $connection->id, 'status' => $connection->status()];
    }

    public function delete(string $businessId, string $id): void
    {
        $this->journal->record('connections.delete');

        if ($this->deleteFailure !== null) {
            throw $this->deleteFailure;
        }

        if (($this->live[$id] ?? null)?->businessId !== $businessId) {
            throw CalendarConnectionNotFound::withId($id);
        }

        $this->disconnected[$id] = $this->live[$id];
        unset($this->live[$id], $this->persistedAccountEmails[$id]);
        $this->deleted[] = [
            'businessId' => $businessId,
            'id' => $id,
            'insideTransaction' => $this->transactions?->isRunning() === true,
        ];
    }

    private function persist(CalendarConnection $connection): void
    {
        $this->live[$connection->id] = $connection;
        $this->persistedAccountEmails[$connection->id] = $connection->accountEmail();
    }
}

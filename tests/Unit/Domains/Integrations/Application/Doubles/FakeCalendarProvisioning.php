<?php

declare(strict_types=1);

namespace Tests\Unit\Domains\Integrations\Application\Doubles;

use App\Domains\Integrations\Contracts\CalendarProvisioning;
use App\Domains\Integrations\Entities\CalendarConnection;
use App\Domains\Integrations\ValueObjects\BusinessCalendarProfile;
use App\Domains\Integrations\ValueObjects\CalendarGrant;
use Throwable;

final class FakeCalendarProvisioning implements CalendarProvisioning
{
    /**
     * @var list<array{grant: CalendarGrant, business: BusinessCalendarProfile}>
     */
    public array $created = [];

    /**
     * @var list<array{grant: CalendarGrant, externalCalendarId: string, business: BusinessCalendarProfile}>
     */
    public array $adopted = [];

    /**
     * @var list<string>
     */
    public array $deletedCalendars = [];

    /**
     * @var list<string>
     */
    public array $revokedAuthorizations = [];

    /**
     * @var list<array{grant: CalendarGrant, externalCalendarId: string}>
     */
    public array $discarded = [];

    /**
     * @var list<CalendarGrant>
     */
    public array $revokedGrants = [];

    private ?string $adoptedCalendarId = null;

    private ?Throwable $provisioningFailure = null;

    private ?Throwable $discardFailure = null;

    private ?Throwable $grantRevocationFailure = null;

    public function __construct(
        private readonly IntegrationsJournal $journal = new IntegrationsJournal,
        private string $createdCalendarId = IntegrationsFixtures::EXTERNAL_CALENDAR_ID,
    ) {}

    public function createWith(string $externalCalendarId): self
    {
        $this->createdCalendarId = $externalCalendarId;

        return $this;
    }

    public function adoptAs(string $externalCalendarId): self
    {
        $this->adoptedCalendarId = $externalCalendarId;

        return $this;
    }

    public function failWith(Throwable $failure): self
    {
        $this->provisioningFailure = $failure;

        return $this;
    }

    public function failDiscardingWith(Throwable $failure): self
    {
        $this->discardFailure = $failure;

        return $this;
    }

    public function failRevokingGrantWith(Throwable $failure): self
    {
        $this->grantRevocationFailure = $failure;

        return $this;
    }

    public function createCalendar(CalendarGrant $grant, BusinessCalendarProfile $business): string
    {
        $this->journal->record('provisioning.createCalendar');
        $this->refuseWhenFailing();

        $this->created[] = ['grant' => $grant, 'business' => $business];

        return $this->createdCalendarId;
    }

    public function adoptCalendar(CalendarGrant $grant, string $externalCalendarId, BusinessCalendarProfile $business): string
    {
        $this->journal->record('provisioning.adoptCalendar');
        $this->refuseWhenFailing();

        $this->adopted[] = ['grant' => $grant, 'externalCalendarId' => $externalCalendarId, 'business' => $business];

        return $this->adoptedCalendarId ?? $externalCalendarId;
    }

    public function deleteCalendar(CalendarConnection $connection): void
    {
        $this->journal->record('provisioning.deleteCalendar');
        $this->deletedCalendars[] = $connection->id;
    }

    public function discardCalendar(CalendarGrant $grant, string $externalCalendarId): void
    {
        $this->journal->record('provisioning.discardCalendar');
        $this->discarded[] = ['grant' => $grant, 'externalCalendarId' => $externalCalendarId];

        if ($this->discardFailure !== null) {
            throw $this->discardFailure;
        }
    }

    public function revokeAuthorization(CalendarConnection $connection): void
    {
        $this->journal->record('provisioning.revokeAuthorization');
        $this->revokedAuthorizations[] = $connection->id;
    }

    public function revokeGrant(CalendarGrant $grant): void
    {
        $this->journal->record('provisioning.revokeGrant');
        $this->revokedGrants[] = $grant;

        if ($this->grantRevocationFailure !== null) {
            throw $this->grantRevocationFailure;
        }
    }

    private function refuseWhenFailing(): void
    {
        if ($this->provisioningFailure !== null) {
            throw $this->provisioningFailure;
        }
    }
}

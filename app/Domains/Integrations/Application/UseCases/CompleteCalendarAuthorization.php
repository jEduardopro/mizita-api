<?php

declare(strict_types=1);

namespace App\Domains\Integrations\Application\UseCases;

use App\Domains\Integrations\Application\Dtos\CalendarConnectionData;
use App\Domains\Integrations\Application\Dtos\CompleteCalendarAuthorizationInput;
use App\Domains\Integrations\Contracts\BusinessProfiles;
use App\Domains\Integrations\Contracts\CalendarAuthorizationStates;
use App\Domains\Integrations\Contracts\CalendarAuthorizer;
use App\Domains\Integrations\Contracts\CalendarConnectionRepository;
use App\Domains\Integrations\Contracts\CalendarOwners;
use App\Domains\Integrations\Contracts\CalendarProvisioning;
use App\Domains\Integrations\Entities\CalendarConnection;
use App\Domains\Integrations\Events\CalendarConnected;
use App\Domains\Integrations\Exceptions\CalendarAuthorizationStateInvalid;
use App\Domains\Integrations\Exceptions\InvalidCalendarConnection;
use App\Domains\Integrations\ValueObjects\BusinessCalendarProfile;
use App\Domains\Integrations\ValueObjects\CalendarGrant;
use App\Domains\Integrations\ValueObjects\CalendarProvider;
use App\Domains\Integrations\ValueObjects\PendingAuthorization;
use App\Shared\Application\UseCaseResponse;
use App\Shared\Contracts\Clock;
use App\Shared\Contracts\DomainFailure;
use App\Shared\Contracts\IdGenerator;
use Illuminate\Contracts\Debug\ExceptionHandler;
use Illuminate\Contracts\Events\Dispatcher;
use Throwable;

final class CompleteCalendarAuthorization
{
    private const PROVIDER = CalendarProvider::Google;

    public function __construct(
        private readonly CalendarAuthorizationStates $states,
        private readonly CalendarOwners $owners,
        private readonly CalendarAuthorizer $authorizer,
        private readonly BusinessProfiles $businesses,
        private readonly CalendarProvisioning $provisioning,
        private readonly CalendarConnectionRepository $connections,
        private readonly IdGenerator $ids,
        private readonly Clock $clock,
        private readonly Dispatcher $events,
        private readonly ExceptionHandler $compensationFailures,
    ) {}

    /**
     * @return UseCaseResponse<CalendarConnectionData>
     */
    public function handle(CompleteCalendarAuthorizationInput $input): UseCaseResponse
    {
        try {
            $input->validate();

            $connection = $this->connect($input);
        } catch (DomainFailure $failure) {
            return UseCaseResponse::failure($failure);
        }

        $this->events->dispatch(new CalendarConnected($connection->id, $connection->businessId));

        return UseCaseResponse::success(CalendarConnectionData::fromEntity($connection));
    }

    private function connect(CompleteCalendarAuthorizationInput $input): CalendarConnection
    {
        $pending = $this->states->consume($input->state);
        $pending->assertIssuedTo($input->accountId);
        $this->assertStillStaffMember($pending);

        $existing = $this->connections->findForStaffMember($pending->businessId, $pending->staffMemberId, self::PROVIDER);
        $existing?->assertAwaitingReconnect();
        $business = $this->businesses->profileOf($pending->businessId);

        return $this->connectWith($this->authorizer->exchange($input->code), $pending, $existing, $business);
    }

    private function connectWith(
        CalendarGrant $grant,
        PendingAuthorization $pending,
        ?CalendarConnection $existing,
        BusinessCalendarProfile $business,
    ): CalendarConnection {
        try {
            $this->assertIdentifiesAccount($grant);

            return $existing === null
                ? $this->firstConnection($pending, $grant, $business)
                : $this->reconnection($existing, $grant, $business);
        } catch (Throwable $failure) {
            $this->revokeUnusedGrant($grant);

            throw $failure;
        }
    }

    /**
     * @throws CalendarAuthorizationStateInvalid
     */
    private function assertStillStaffMember(PendingAuthorization $pending): void
    {
        if ($this->owners->staffMemberIdOf($pending->businessId, $pending->accountId) !== $pending->staffMemberId) {
            throw CalendarAuthorizationStateInvalid::issuedToAnotherAccount();
        }
    }

    /**
     * @throws InvalidCalendarConnection
     */
    private function assertIdentifiesAccount(CalendarGrant $grant): void
    {
        if (trim($grant->accountEmail) === '') {
            throw InvalidCalendarConnection::missingAccountEmail();
        }
    }

    private function firstConnection(
        PendingAuthorization $pending,
        CalendarGrant $grant,
        BusinessCalendarProfile $business,
    ): CalendarConnection {
        $externalCalendarId = $this->provisioning->createCalendar($grant, $business);

        try {
            $connection = CalendarConnection::connect(
                id: $this->ids->next(),
                businessId: $pending->businessId,
                staffMemberId: $pending->staffMemberId,
                provider: self::PROVIDER,
                accountEmail: $grant->accountEmail,
                externalCalendarId: $externalCalendarId,
                now: $this->clock->now(),
            );

            $this->connections->saveWithTokens($connection, $grant->tokens);
        } catch (Throwable $failure) {
            $this->discardCalendar($grant, $externalCalendarId);

            throw $failure;
        }

        return $connection;
    }

    private function reconnection(
        CalendarConnection $existing,
        CalendarGrant $grant,
        BusinessCalendarProfile $business,
    ): CalendarConnection {
        $previousCalendarId = $existing->externalCalendarId();
        $externalCalendarId = $this->provisioning->adoptCalendar($grant, $previousCalendarId, $business);

        try {
            $existing->reconnect(
                accountEmail: $grant->accountEmail,
                externalCalendarId: $externalCalendarId,
                now: $this->clock->now(),
            );

            $this->connections->saveWithTokens($existing, $grant->tokens);
        } catch (Throwable $failure) {
            $this->discardReplacementCalendar($grant, $externalCalendarId, $previousCalendarId);

            throw $failure;
        }

        return $existing;
    }

    private function discardReplacementCalendar(CalendarGrant $grant, string $externalCalendarId, string $previousCalendarId): void
    {
        if ($externalCalendarId === $previousCalendarId) {
            return;
        }

        $this->discardCalendar($grant, $externalCalendarId);
    }

    private function discardCalendar(CalendarGrant $grant, string $externalCalendarId): void
    {
        try {
            $this->provisioning->discardCalendar($grant, $externalCalendarId);
        } catch (Throwable $compensationFailure) {
            $this->compensationFailures->report($compensationFailure);
        }
    }

    private function revokeUnusedGrant(CalendarGrant $grant): void
    {
        try {
            if ($this->connections->existsLiveForAccount(self::PROVIDER, $grant->accountEmail)) {
                return;
            }

            $this->provisioning->revokeGrant($grant);
        } catch (Throwable $compensationFailure) {
            $this->compensationFailures->report($compensationFailure);
        }
    }
}

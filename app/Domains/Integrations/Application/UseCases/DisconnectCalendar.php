<?php

declare(strict_types=1);

namespace App\Domains\Integrations\Application\UseCases;

use App\Domains\Integrations\Application\Dtos\DisconnectCalendarInput;
use App\Domains\Integrations\Contracts\CalendarConnectionRepository;
use App\Domains\Integrations\Contracts\CalendarEventLinkRepository;
use App\Domains\Integrations\Contracts\CalendarOwners;
use App\Domains\Integrations\Entities\CalendarConnection;
use App\Domains\Integrations\Events\CalendarDisconnected;
use App\Domains\Integrations\Exceptions\CalendarConnectionNotFound;
use App\Domains\Integrations\ValueObjects\CalendarProvider;
use App\Shared\Application\UseCaseResponse;
use App\Shared\Contracts\BusinessContext;
use App\Shared\Contracts\DomainFailure;
use App\Shared\Contracts\TransactionManager;
use Illuminate\Contracts\Events\Dispatcher;

final class DisconnectCalendar
{
    private const PROVIDER = CalendarProvider::Google;

    public function __construct(
        private readonly CalendarOwners $owners,
        private readonly CalendarConnectionRepository $connections,
        private readonly CalendarEventLinkRepository $links,
        private readonly TransactionManager $transactions,
        private readonly BusinessContext $business,
        private readonly Dispatcher $events,
    ) {}

    /**
     * @return UseCaseResponse<null>
     */
    public function handle(DisconnectCalendarInput $input): UseCaseResponse
    {
        try {
            $connection = $this->disconnect($input);
        } catch (DomainFailure $failure) {
            return UseCaseResponse::failure($failure);
        }

        $this->events->dispatch(new CalendarDisconnected($connection->id, $connection->businessId));

        return UseCaseResponse::success();
    }

    private function disconnect(DisconnectCalendarInput $input): CalendarConnection
    {
        $businessId = $this->business->currentBusinessId();
        $staffMemberId = $this->owners->staffMemberIdOf($businessId, $input->accountId);

        $connection = $this->connections->findForStaffMember($businessId, $staffMemberId, self::PROVIDER)
            ?? throw CalendarConnectionNotFound::forStaffMember($staffMemberId);

        $this->transactions->run(function () use ($connection): void {
            $this->links->deleteForConnection($connection->businessId, $connection->id);
            $this->connections->delete($connection->businessId, $connection->id);
        });

        return $connection;
    }
}

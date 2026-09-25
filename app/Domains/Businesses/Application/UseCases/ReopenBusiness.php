<?php

declare(strict_types=1);

namespace App\Domains\Businesses\Application\UseCases;

use App\Domains\Businesses\Application\Dtos\ReopenBusinessInput;
use App\Domains\Businesses\Contracts\BusinessRepository;
use App\Domains\Businesses\Contracts\OwnerRegistrar;
use App\Domains\Businesses\Contracts\PaymentMethodProvisioner;
use App\Domains\Businesses\Contracts\RoleProvisioner;
use App\Domains\Businesses\Contracts\ScheduleProvisioner;
use App\Domains\Businesses\Entities\Business;
use App\Domains\Businesses\Exceptions\BusinessNotClosed;
use App\Domains\Businesses\Exceptions\OwnerAlreadyHasBusiness;
use App\Shared\Application\UseCaseResponse;
use App\Shared\Contracts\DomainFailure;
use App\Shared\Contracts\TransactionManager;
use Illuminate\Contracts\Events\Dispatcher;

final class ReopenBusiness
{
    public function __construct(
        private readonly BusinessRepository $businesses,
        private readonly RoleProvisioner $roles,
        private readonly PaymentMethodProvisioner $paymentMethods,
        private readonly OwnerRegistrar $owners,
        private readonly ScheduleProvisioner $schedules,
        private readonly TransactionManager $transactions,
        private readonly Dispatcher $events,
    ) {}

    /**
     * @return UseCaseResponse<null>
     */
    public function handle(ReopenBusinessInput $input): UseCaseResponse
    {
        try {
            $events = $this->reopen($input);
        } catch (DomainFailure $failure) {
            return UseCaseResponse::failure($failure);
        }

        foreach ($events as $event) {
            $this->events->dispatch($event);
        }

        return UseCaseResponse::success();
    }

    /**
     * @return list<object>
     *
     * @throws BusinessNotClosed
     * @throws OwnerAlreadyHasBusiness
     */
    private function reopen(ReopenBusinessInput $input): array
    {
        $business = $this->closedBusiness($input->businessId);

        $reopening = $business->reopen($input->ownerAccountId);

        return $this->transactions->run(function () use ($business, $reopening, $input): array {
            $this->businesses->save($business);

            if (! $reopening->requiresReprovisioning()) {
                return [];
            }

            return $this->reprovision($business, $input->ownerAccountId);
        });
    }

    /**
     * @throws BusinessNotClosed
     */
    private function closedBusiness(string $businessId): Business
    {
        $business = $this->businesses->findClosedById($businessId);

        if ($business === null) {
            throw BusinessNotClosed::withId($businessId);
        }

        return $business;
    }

    /**
     * @return list<object>
     *
     * @throws OwnerAlreadyHasBusiness
     */
    private function reprovision(Business $business, string $ownerAccountId): array
    {
        $this->roles->provisionFor($business->id);

        $this->paymentMethods->provisionFor($business->id);

        $owner = $this->owners->registerOwner($business->id, $ownerAccountId);

        $this->schedules->provisionDefaultsFor($business->id, $owner->staffMemberId);

        return $owner->events;
    }
}

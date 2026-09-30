<?php

declare(strict_types=1);

namespace App\Domains\Customers\Application\UseCases;

use App\Domains\Customers\Application\Dtos\CustomerData;
use App\Domains\Customers\Application\Dtos\ListCustomersInput;
use App\Domains\Customers\Application\Presenters\CustomerPresenter;
use App\Domains\Customers\Contracts\BusinessTimezone;
use App\Domains\Customers\Contracts\CustomerPhoneBook;
use App\Domains\Customers\Contracts\CustomerRepository;
use App\Domains\Customers\ValueObjects\RegistrationPeriod;
use App\Domains\Customers\ValueObjects\RegistrationWindow;
use App\Shared\Application\UseCaseResponse;
use App\Shared\Contracts\BusinessContext;
use App\Shared\Contracts\DomainFailure;
use App\Shared\ValueObjects\Paginated;

final class ListCustomers
{
    public function __construct(
        private readonly CustomerRepository $customers,
        private readonly CustomerPhoneBook $phones,
        private readonly CustomerPresenter $presenter,
        private readonly BusinessContext $business,
        private readonly BusinessTimezone $timezones,
    ) {}

    /**
     * @return UseCaseResponse<Paginated<CustomerData>>
     */
    public function handle(ListCustomersInput $input): UseCaseResponse
    {
        try {
            $input->validate();

            $businessId = $this->business->currentBusinessId();

            $page = $this->customers->search(
                $businessId,
                $input->toQuery(
                    $this->customerIdsMatchingPhone($input->search),
                    $this->registrationWindowOf($businessId, $input->registrationPeriod()),
                ),
            );

            return UseCaseResponse::success($this->presenter->describePage($businessId, $page));
        } catch (DomainFailure $failure) {
            return UseCaseResponse::failure($failure);
        }
    }

    /**
     * @return list<string>
     */
    private function customerIdsMatchingPhone(?string $search): array
    {
        if ($search === null) {
            return [];
        }

        return $this->phones->customerIdsMatchingNumber($search);
    }

    private function registrationWindowOf(string $businessId, ?RegistrationPeriod $period): ?RegistrationWindow
    {
        if ($period === null) {
            return null;
        }

        return $period->windowIn($this->timezones->timezoneOf($businessId));
    }
}

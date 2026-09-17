<?php

declare(strict_types=1);

namespace App\Domains\Customers\Infrastructure\Gateways;

use App\Domains\Customers\Contracts\CustomerPhoneBook;
use App\Domains\Phones\Application\Dtos\AttachPhoneInput;
use App\Domains\Phones\Application\UseCases\AttachPhone;
use App\Domains\Phones\Contracts\PhoneRepository;
use App\Domains\Phones\Entities\Phone;
use App\Domains\Phones\ValueObjects\PhoneNumberFragment;
use App\Domains\Phones\ValueObjects\PhoneOwnerType;
use App\Shared\ValueObjects\PhoneNumber;

final class PhonesCustomerPhoneBook implements CustomerPhoneBook
{
    public function __construct(
        private readonly AttachPhone $attachPhone,
        private readonly PhoneRepository $phones,
    ) {}

    public function forCustomer(string $customerId): ?PhoneNumber
    {
        return $this->phones->findForOwner(PhoneOwnerType::Customer, $customerId)?->number();
    }

    /**
     * @param  list<string>  $customerIds
     * @return array<string, PhoneNumber>
     */
    public function forCustomers(array $customerIds): array
    {
        if ($customerIds === []) {
            return [];
        }

        return array_map(
            static fn (Phone $phone): PhoneNumber => $phone->number(),
            $this->phones->findForOwners(PhoneOwnerType::Customer, $customerIds),
        );
    }

    public function replaceForCustomer(string $customerId, ?PhoneNumber $phone): void
    {
        if ($phone === null) {
            $this->removeForCustomer($customerId);

            return;
        }

        $this->attachPhone->handle(new AttachPhoneInput(
            ownerType: PhoneOwnerType::Customer,
            ownerId: $customerId,
            number: $phone,
        ))->value();
    }

    public function removeForCustomer(string $customerId): void
    {
        $this->phones->deleteForOwner(PhoneOwnerType::Customer, $customerId);
    }

    /**
     * @return list<string>
     */
    public function customerIdsWithNumber(PhoneNumber $number): array
    {
        return $this->phones->ownerIdsWithNumber(PhoneOwnerType::Customer, $number);
    }

    /**
     * @return list<string>
     */
    public function customerIdsMatchingNumber(string $fragment): array
    {
        $digits = PhoneNumberFragment::of($fragment);

        if ($digits === null) {
            return [];
        }

        return $this->phones->ownerIdsMatchingNumber(PhoneOwnerType::Customer, $digits);
    }
}

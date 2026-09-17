<?php

declare(strict_types=1);

namespace App\Domains\Customers\Application\Services;

use App\Domains\Customers\Contracts\CustomerPhoneBook;
use App\Domains\Customers\Contracts\CustomerRepository;
use App\Domains\Customers\Exceptions\CustomerEmailAlreadyTaken;
use App\Domains\Customers\Exceptions\CustomerPhoneAlreadyTaken;
use App\Domains\Customers\ValueObjects\CustomerEmail;
use App\Shared\ValueObjects\PhoneNumber;

final class ContactUniqueness
{
    public function __construct(
        private readonly CustomerRepository $customers,
        private readonly CustomerPhoneBook $phones,
    ) {}

    /**
     * @throws CustomerEmailAlreadyTaken
     */
    public function ensureEmailIsFree(string $businessId, ?CustomerEmail $email, ?string $exceptId = null): void
    {
        if ($email === null) {
            return;
        }

        if ($this->customers->existsByEmail($businessId, $email, $exceptId)) {
            throw CustomerEmailAlreadyTaken::for($email->value);
        }
    }

    /**
     * @throws CustomerPhoneAlreadyTaken
     */
    public function ensurePhoneIsFree(string $businessId, ?PhoneNumber $phone, ?string $exceptId = null): void
    {
        if ($phone === null) {
            return;
        }

        $holders = $this->phones->customerIdsWithNumber($phone);

        if ($holders === []) {
            return;
        }

        if ($this->customers->existsAmong($businessId, $holders, $exceptId)) {
            throw CustomerPhoneAlreadyTaken::for($phone->e164());
        }
    }
}

<?php

declare(strict_types=1);

use App\Domains\Customers\Application\Services\ContactUniqueness;
use App\Domains\Customers\Exceptions\CustomerEmailAlreadyTaken;
use App\Domains\Customers\Exceptions\CustomerPhoneAlreadyTaken;
use App\Domains\Customers\ValueObjects\CustomerEmail;
use Tests\Support\Customers\CustomerFixtures;
use Tests\Support\Customers\FakeCustomerPhoneBook;
use Tests\Support\Customers\FakeCustomerRepository;
use Tests\Support\FakeBusinessContext;
use Tests\Support\PhoneNumbers;

beforeEach(function () {
    $this->customers = new FakeCustomerRepository;
    $this->phones = new FakeCustomerPhoneBook;

    $this->uniqueness = new ContactUniqueness($this->customers, $this->phones);

    $this->email = CustomerEmail::fromString(CustomerFixtures::EMAIL);
    $this->number = PhoneNumbers::mexican();
});

describe('the email a customer may carry', function () {
    it('accepts an email no other customer of the business carries', function () {
        $this->uniqueness->ensureEmailIsFree(FakeBusinessContext::BUSINESS_ID, $this->email);

        expect($this->customers->emailChecks)->toBe([[
            'businessId' => FakeBusinessContext::BUSINESS_ID,
            'email' => CustomerFixtures::EMAIL,
            'exceptId' => null,
        ]]);
    });

    it('asks nothing at all when no email was submitted', function () {
        $this->uniqueness->ensureEmailIsFree(FakeBusinessContext::BUSINESS_ID, null);

        expect($this->customers->emailChecks)->toBe([])
            ->and($this->customers->businessIdsSeen)->toBe([]);
    });

    it('refuses an email another customer of the same business already carries', function () {
        $this->customers->store(CustomerFixtures::customer());

        expect(fn () => $this->uniqueness->ensureEmailIsFree(FakeBusinessContext::BUSINESS_ID, $this->email))
            ->toThrow(CustomerEmailAlreadyTaken::class);
    });

    it('accepts an email another business carries, because the two never meet', function () {
        $this->customers->store(CustomerFixtures::customer(businessId: CustomerFixtures::OTHER_BUSINESS_ID));

        $this->uniqueness->ensureEmailIsFree(FakeBusinessContext::BUSINESS_ID, $this->email);

        expect($this->customers->emailChecks[0]['businessId'])->toBe(FakeBusinessContext::BUSINESS_ID);
    });

    it('lets a customer keep the email it already carries', function () {
        $this->customers->store(CustomerFixtures::customer());

        $this->uniqueness->ensureEmailIsFree(
            FakeBusinessContext::BUSINESS_ID,
            $this->email,
            CustomerFixtures::CUSTOMER_ID,
        );

        expect($this->customers->emailChecks[0]['exceptId'])->toBe(CustomerFixtures::CUSTOMER_ID);
    });

    it('still refuses another customer email when one customer is excluded', function () {
        $this->customers->store(
            CustomerFixtures::customer(),
            CustomerFixtures::customer(id: CustomerFixtures::SECOND_CUSTOMER_ID, email: 'grace@example.com'),
        );

        expect(fn () => $this->uniqueness->ensureEmailIsFree(
            FakeBusinessContext::BUSINESS_ID,
            $this->email,
            CustomerFixtures::SECOND_CUSTOMER_ID,
        ))->toThrow(CustomerEmailAlreadyTaken::class);
    });
});

describe('the phone a customer may carry', function () {
    it('accepts a number nobody holds', function () {
        $this->uniqueness->ensurePhoneIsFree(FakeBusinessContext::BUSINESS_ID, $this->number);

        expect($this->phones->numberLookups)->toBe([PhoneNumbers::MX_E164])
            ->and($this->customers->membershipChecks)->toBe([]);
    });

    it('asks nothing at all when no phone was submitted', function () {
        $this->uniqueness->ensurePhoneIsFree(FakeBusinessContext::BUSINESS_ID, null);

        expect($this->phones->numberLookups)->toBe([])
            ->and($this->customers->membershipChecks)->toBe([]);
    });

    it('refuses a number another customer of the same business holds', function () {
        $this->customers->store(CustomerFixtures::customer());
        $this->phones->store(CustomerFixtures::CUSTOMER_ID, $this->number);

        expect(fn () => $this->uniqueness->ensurePhoneIsFree(FakeBusinessContext::BUSINESS_ID, $this->number))
            ->toThrow(CustomerPhoneAlreadyTaken::class);
    });

    it('accepts a number held only by a customer of another business', function () {
        $this->customers->store(CustomerFixtures::customer(
            id: CustomerFixtures::FOREIGN_CUSTOMER_ID,
            businessId: CustomerFixtures::OTHER_BUSINESS_ID,
        ));
        $this->phones->store(CustomerFixtures::FOREIGN_CUSTOMER_ID, $this->number);

        $this->uniqueness->ensurePhoneIsFree(FakeBusinessContext::BUSINESS_ID, $this->number);

        expect($this->customers->membershipChecks)->toBe([[
            'businessId' => FakeBusinessContext::BUSINESS_ID,
            'customerIds' => [CustomerFixtures::FOREIGN_CUSTOMER_ID],
            'exceptId' => null,
        ]]);
    });

    it('hands the repository every holder the phone book named, tenant or not', function () {
        $this->phones->store(CustomerFixtures::CUSTOMER_ID, $this->number)
            ->store(CustomerFixtures::FOREIGN_CUSTOMER_ID, $this->number);

        $this->uniqueness->ensurePhoneIsFree(FakeBusinessContext::BUSINESS_ID, $this->number);

        expect($this->customers->membershipChecks[0]['customerIds'])->toBe([
            CustomerFixtures::CUSTOMER_ID,
            CustomerFixtures::FOREIGN_CUSTOMER_ID,
        ]);
    });

    it('lets a customer keep the number it already holds', function () {
        $this->customers->store(CustomerFixtures::customer());
        $this->phones->store(CustomerFixtures::CUSTOMER_ID, $this->number);

        $this->uniqueness->ensurePhoneIsFree(
            FakeBusinessContext::BUSINESS_ID,
            $this->number,
            CustomerFixtures::CUSTOMER_ID,
        );

        expect($this->customers->membershipChecks[0]['exceptId'])->toBe(CustomerFixtures::CUSTOMER_ID);
    });

    it('still refuses a number another customer holds when one customer is excluded', function () {
        $this->customers->store(
            CustomerFixtures::customer(),
            CustomerFixtures::customer(id: CustomerFixtures::SECOND_CUSTOMER_ID, email: 'grace@example.com'),
        );
        $this->phones->store(CustomerFixtures::CUSTOMER_ID, $this->number);

        expect(fn () => $this->uniqueness->ensurePhoneIsFree(
            FakeBusinessContext::BUSINESS_ID,
            $this->number,
            CustomerFixtures::SECOND_CUSTOMER_ID,
        ))->toThrow(CustomerPhoneAlreadyTaken::class);
    });
});

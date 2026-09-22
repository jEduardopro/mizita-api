<?php

declare(strict_types=1);

use App\Domains\Appointments\Application\Dtos\ListCustomerAppointmentsInput;
use App\Domains\Appointments\Exceptions\AppointmentCustomerNotFound;
use App\Domains\Appointments\ValueObjects\CustomerAppointmentQuery;
use App\Shared\ValueObjects\DomainFailureKind;
use App\Shared\ValueObjects\Pagination;
use Tests\Support\Appointments\AppointmentFixtures;

describe('the payload it reads', function () {
    it('reads the page a caller asked for', function () {
        $input = ListCustomerAppointmentsInput::fromRequest(
            ['page' => 3, 'per_page' => 25],
            AppointmentFixtures::CUSTOMER_ID,
        );

        expect($input->page)->toBe(3)
            ->and($input->perPage)->toBe(25);
    });

    it('survives a payload that never passed through the form request', function (array $payload) {
        $input = ListCustomerAppointmentsInput::fromRequest($payload, AppointmentFixtures::CUSTOMER_ID);

        expect($input->page)->toBeNull()
            ->and($input->perPage)->toBeNull()
            ->and($input->customerId)->toBe(AppointmentFixtures::CUSTOMER_ID);
    })->with([
        'nothing at all' => [[]],
        'a word where a number belongs' => [['page' => 'first', 'per_page' => 'all']],
        'an explicit null' => [['page' => null, 'per_page' => null]],
        'an array' => [['page' => [2], 'per_page' => ['x' => 1]]],
        'a boolean' => [['page' => true, 'per_page' => false]],
    ]);

    it('reads a numeric string as the number it spells', function () {
        $input = ListCustomerAppointmentsInput::fromRequest(
            ['page' => '2', 'per_page' => '15'],
            AppointmentFixtures::CUSTOMER_ID,
        );

        expect($input->page)->toBe(2)
            ->and($input->perPage)->toBe(15);
    });

    it('takes the customer from the route, never from a key the caller could set', function () {
        $input = ListCustomerAppointmentsInput::fromRequest(
            ['customer_id' => AppointmentFixtures::SECOND_CUSTOMER_ID],
            AppointmentFixtures::CUSTOMER_ID,
        );

        expect($input->customerId)->toBe(AppointmentFixtures::CUSTOMER_ID);
    });
});

describe('the customer it validates', function () {
    it('accepts a well formed customer uuid', function () {
        expect(fn () => AppointmentFixtures::listCustomerInput()->validate())->not->toThrow(Throwable::class);
    });

    it('refuses anything that is not a uuid', function (string $customerId) {
        expect(fn () => AppointmentFixtures::listCustomerInput(customerId: $customerId)->validate())
            ->toThrow(AppointmentCustomerNotFound::class);
    })->with([
        'empty' => '',
        'spaces' => '   ',
        'a word' => 'not-a-uuid',
        'an integer key' => '42',
        'a uuid with a trailing space' => AppointmentFixtures::CUSTOMER_ID.' ',
        'a uuid missing a group' => '01930000-0000-7000-0000000000c1',
    ]);

    it('refuses with a not found kind so the caller learns nothing about the business', function () {
        $failure = null;

        try {
            AppointmentFixtures::listCustomerInput(customerId: 'not-a-uuid')->validate();
        } catch (AppointmentCustomerNotFound $refused) {
            $failure = $refused;
        }

        expect($failure?->errorCode())->toBe('appointment_customer_not_found')
            ->and($failure?->kind())->toBe(DomainFailureKind::NotFound);
    });
});

describe('the query it builds', function () {
    it('carries the customer it was built for', function () {
        $query = AppointmentFixtures::listCustomerInput()->toQuery();

        expect($query)->toBeInstanceOf(CustomerAppointmentQuery::class)
            ->and($query->customerId)->toBe(AppointmentFixtures::CUSTOMER_ID);
    });

    it('asks for the first page of twenty when the caller asked for nothing', function () {
        $pagination = AppointmentFixtures::listCustomerInput()->toQuery()->pagination;

        expect($pagination->page)->toBe(1)
            ->and($pagination->perPage)->toBe(Pagination::DEFAULT_PER_PAGE);
    });

    it('resolves a page size it does not serve instead of refusing it', function (?int $page, ?int $perPage, int $expectedPage, int $expectedPerPage) {
        $pagination = AppointmentFixtures::listCustomerInput(page: $page, perPage: $perPage)->toQuery()->pagination;

        expect($pagination->page)->toBe($expectedPage)
            ->and($pagination->perPage)->toBe($expectedPerPage);
    })->with([
        'a page below the first' => [0, 10, 1, 10],
        'a negative page' => [-5, 10, 1, 10],
        'more rows than it serves' => [2, 9999, 2, Pagination::MAXIMUM_PER_PAGE],
        'exactly the maximum' => [1, Pagination::MAXIMUM_PER_PAGE, 1, Pagination::MAXIMUM_PER_PAGE],
        'fewer rows than one' => [1, 0, 1, 1],
    ]);
});

<?php

declare(strict_types=1);

use App\Domains\BookingPolicies\Entities\BookingPolicy;
use App\Domains\BookingPolicies\Infrastructure\Eloquent\Mappers\BookingPolicyMapper;
use App\Domains\BookingPolicies\Infrastructure\Eloquent\Models\BookingPolicyModel;
use App\Domains\BookingPolicies\ValueObjects\ContactFieldRequirement;
use Tests\Support\BookingPolicies\BookingPolicyFixtures;
use Tests\Support\FakeBusinessContext;

const BOOKING_POLICY_BUSINESS_KEY = 42;

/**
 * @param  array<string, mixed>  $overrides
 */
function bookingPolicyRow(array $overrides = []): BookingPolicyModel
{
    $model = new BookingPolicyModel;

    $model->setRawAttributes([
        'id' => 3,
        'uuid' => BookingPolicyFixtures::POLICY_ID,
        'business_id' => BOOKING_POLICY_BUSINESS_KEY,
        'lead_time_minutes' => 60,
        'booking_window_minutes' => 43200,
        'slot_granularity_minutes' => 30,
        'cancellation_window_minutes' => 240,
        'policy_message' => BookingPolicyFixtures::POLICY_MESSAGE,
        'display_on_booking_page' => true,
        'phone_field' => 'hidden',
        'email_field' => 'required',
        'address_field' => 'optional',
        'created_at' => BookingPolicyFixtures::now(),
        ...$overrides,
    ], true);

    return $model;
}

beforeEach(function () {
    $this->mapper = new BookingPolicyMapper;
});

describe('reading a row', function () {
    it('restores every rule the row carries', function () {
        $policy = $this->mapper->toEntity(bookingPolicyRow(), FakeBusinessContext::BUSINESS_ID);

        expect($policy)->toBeInstanceOf(BookingPolicy::class)
            ->and($policy->id)->toBe(BookingPolicyFixtures::POLICY_ID)
            ->and($policy->businessId)->toBe(FakeBusinessContext::BUSINESS_ID)
            ->and($policy->leadTime()->minutes)->toBe(60)
            ->and($policy->bookingWindow()->minutes())->toBe(43200)
            ->and($policy->slotGranularity()->minutes)->toBe(30)
            ->and($policy->cancellationWindow()->minutes)->toBe(240)
            ->and($policy->policyMessage()->toString())->toBe(BookingPolicyFixtures::POLICY_MESSAGE)
            ->and($policy->isDisplayedOnBookingPage())->toBeTrue()
            ->and($policy->contactFields()->phone)->toBe(ContactFieldRequirement::Hidden)
            ->and($policy->contactFields()->email)->toBe(ContactFieldRequirement::Required)
            ->and($policy->contactFields()->address)->toBe(ContactFieldRequirement::Optional)
            ->and($policy->createdAt)->toEqual(BookingPolicyFixtures::now());
    });

    it('reads the two nullable columns back as nothing at all, never as zero', function () {
        $policy = $this->mapper->toEntity(bookingPolicyRow([
            'booking_window_minutes' => null,
            'cancellation_window_minutes' => null,
            'policy_message' => null,
        ]), FakeBusinessContext::BUSINESS_ID);

        expect($policy->bookingWindow()->minutes())->toBeNull()
            ->and($policy->bookingWindow()->isUnlimited())->toBeTrue()
            ->and($policy->cancellationWindow()->minutes)->toBeNull()
            ->and($policy->cancellationWindow()->isAllowed())->toBeFalse()
            ->and($policy->policyMessage()->toString())->toBeNull();
    });

    it('takes the identity from the uuid column, not from the primary key', function () {
        expect($this->mapper->toEntity(bookingPolicyRow(), FakeBusinessContext::BUSINESS_ID)->id)
            ->toBe(BookingPolicyFixtures::POLICY_ID);
    });

    it('reads the business back as the uuid it was handed, never as the column', function () {
        $policy = $this->mapper->toEntity(bookingPolicyRow(), FakeBusinessContext::BUSINESS_ID);

        expect($policy->businessId)->toBe(FakeBusinessContext::BUSINESS_ID)
            ->and($policy->businessId)->not->toBe((string) BOOKING_POLICY_BUSINESS_KEY);
    });

    it('reads every stored requirement back as the case it names, on each of the three columns', function (string $stored, ContactFieldRequirement $expected) {
        $fields = $this->mapper->toEntity(bookingPolicyRow([
            'phone_field' => $stored,
            'email_field' => $stored,
            'address_field' => $stored,
        ]), FakeBusinessContext::BUSINESS_ID)->contactFields();

        expect($fields->phone)->toBe($expected)
            ->and($fields->email)->toBe($expected)
            ->and($fields->address)->toBe($expected);
    })->with([
        'hidden' => ['hidden', ContactFieldRequirement::Hidden],
        'optional' => ['optional', ContactFieldRequirement::Optional],
        'required' => ['required', ContactFieldRequirement::Required],
    ]);

    it('reads the toggle back as a boolean, whatever the driver handed over', function (mixed $stored, bool $expected) {
        expect($this->mapper->toEntity(
            bookingPolicyRow(['display_on_booking_page' => $stored]),
            FakeBusinessContext::BUSINESS_ID,
        )->isDisplayedOnBookingPage())->toBe($expected);
    })->with([
        'true' => [true, true],
        'false' => [false, false],
        'one' => [1, true],
        'zero' => [0, false],
    ]);
});

describe('writing a row', function () {
    it('spreads the policy across its eleven columns', function () {
        expect($this->mapper->toAttributes(BookingPolicyFixtures::policy(), BOOKING_POLICY_BUSINESS_KEY))->toBe([
            'uuid' => BookingPolicyFixtures::POLICY_ID,
            'business_id' => BOOKING_POLICY_BUSINESS_KEY,
            'lead_time_minutes' => 60,
            'booking_window_minutes' => 43200,
            'slot_granularity_minutes' => 30,
            'cancellation_window_minutes' => 240,
            'policy_message' => BookingPolicyFixtures::POLICY_MESSAGE,
            'display_on_booking_page' => true,
            'phone_field' => ContactFieldRequirement::Hidden,
            'email_field' => ContactFieldRequirement::Required,
            'address_field' => ContactFieldRequirement::Optional,
        ]);
    });

    it('stores each requirement as the string the column check constraint allows', function () {
        $stored = (new BookingPolicyModel)
            ->fill($this->mapper->toAttributes(BookingPolicyFixtures::policy(), BOOKING_POLICY_BUSINESS_KEY))
            ->getAttributes();

        expect($stored['phone_field'])->toBe('hidden')
            ->and($stored['email_field'])->toBe('required')
            ->and($stored['address_field'])->toBe('optional');
    });

    it('writes null for an unlimited horizon and for a cancellation nobody may use', function () {
        $attributes = $this->mapper->toAttributes(
            BookingPolicyFixtures::policy(
                bookingWindowMinutes: null,
                cancellationWindowMinutes: null,
                policyMessage: null,
            ),
            BOOKING_POLICY_BUSINESS_KEY,
        );

        expect($attributes['booking_window_minutes'])->toBeNull()
            ->and($attributes['cancellation_window_minutes'])->toBeNull()
            ->and($attributes['policy_message'])->toBeNull();
    });

    it('writes the business as the int key it was handed', function () {
        $attributes = $this->mapper->toAttributes(BookingPolicyFixtures::policy(), BOOKING_POLICY_BUSINESS_KEY);

        expect($attributes['business_id'])->toBeInt()
            ->and($attributes)->not->toContain(FakeBusinessContext::BUSINESS_ID);
    });

    it('never writes the internal primary key or the creation instant', function () {
        $attributes = $this->mapper->toAttributes(BookingPolicyFixtures::policy(), BOOKING_POLICY_BUSINESS_KEY);

        expect($attributes)->not->toHaveKey('id')
            ->and($attributes)->not->toHaveKey('created_at');
    });
});

it('survives a full round trip without losing a rule', function () {
    $policy = BookingPolicyFixtures::policy(
        leadTimeMinutes: 15,
        bookingWindowMinutes: 1440,
        slotGranularityMinutes: 5,
        cancellationWindowMinutes: 0,
        policyMessage: 'Avisa con antelación.',
        displayedOnBookingPage: false,
        contactFields: BookingPolicyFixtures::contactFields(
            phone: ContactFieldRequirement::Optional,
            email: ContactFieldRequirement::Hidden,
            address: ContactFieldRequirement::Required,
        ),
    );

    $attributes = $this->mapper->toAttributes($policy, BOOKING_POLICY_BUSINESS_KEY);

    $restored = $this->mapper->toEntity(
        bookingPolicyRow([...$attributes, 'created_at' => BookingPolicyFixtures::now()]),
        FakeBusinessContext::BUSINESS_ID,
    );

    expect($restored->id)->toBe($policy->id)
        ->and($restored->businessId)->toBe($policy->businessId)
        ->and($restored->leadTime()->minutes)->toBe(15)
        ->and($restored->bookingWindow()->minutes())->toBe(1440)
        ->and($restored->slotGranularity()->minutes)->toBe(5)
        ->and($restored->cancellationWindow()->minutes)->toBe(0)
        ->and($restored->policyMessage()->toString())->toBe('Avisa con antelación.')
        ->and($restored->isDisplayedOnBookingPage())->toBeFalse()
        ->and($restored->contactFields()->phone)->toBe(ContactFieldRequirement::Optional)
        ->and($restored->contactFields()->email)->toBe(ContactFieldRequirement::Hidden)
        ->and($restored->contactFields()->address)->toBe(ContactFieldRequirement::Required)
        ->and($restored->createdAt)->toEqual($policy->createdAt);
});

it('survives a round trip with both nullable columns empty', function () {
    $policy = BookingPolicyFixtures::policy(bookingWindowMinutes: null, cancellationWindowMinutes: null, policyMessage: null);

    $restored = $this->mapper->toEntity(
        bookingPolicyRow([
            ...$this->mapper->toAttributes($policy, BOOKING_POLICY_BUSINESS_KEY),
            'created_at' => BookingPolicyFixtures::now(),
        ]),
        FakeBusinessContext::BUSINESS_ID,
    );

    expect($restored->bookingWindow()->minutes())->toBeNull()
        ->and($restored->cancellationWindow()->minutes)->toBeNull()
        ->and($restored->policyMessage()->toString())->toBeNull();
});

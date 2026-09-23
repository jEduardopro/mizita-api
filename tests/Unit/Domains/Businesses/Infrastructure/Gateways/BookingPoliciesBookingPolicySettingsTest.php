<?php

declare(strict_types=1);

use App\Domains\BookingPolicies\Application\Presenters\BookingPolicyPresenter;
use App\Domains\BookingPolicies\Contracts\BookingPolicyRepository;
use App\Domains\BookingPolicies\Contracts\CurrentBookingPolicy;
use App\Domains\BookingPolicies\Exceptions\BookingPolicyNotFound;
use App\Domains\BookingPolicies\Infrastructure\ProvisionedCurrentBookingPolicy;
use App\Domains\BookingPolicies\ValueObjects\ContactFieldRequirement;
use App\Domains\Businesses\Contracts\BookingPolicySettings;
use App\Domains\Businesses\Infrastructure\Gateways\BookingPoliciesBookingPolicySettings;
use App\Domains\Businesses\ValueObjects\ContactFieldPreference;
use App\Domains\Businesses\ValueObjects\ContactFieldPreferences;
use Tests\Support\BookingPolicies\BookingPolicyFixtures;
use Tests\Support\BookingPolicies\FakeBookingPolicyRepository;
use Tests\Support\FakeBusinessContext;
use Tests\Support\FakeClock;
use Tests\Support\FixedIdGenerator;

function bookingPolicySettingsOver(
    FakeBookingPolicyRepository $policies,
    ?CurrentBookingPolicy $current = null,
    ?BookingPolicyRepository $repository = null,
): BookingPoliciesBookingPolicySettings {
    return new BookingPoliciesBookingPolicySettings(
        $current ?? new ProvisionedCurrentBookingPolicy(
            $policies,
            new FixedIdGenerator(BookingPolicyFixtures::GENERATED_POLICY_ID),
            new FakeClock(BookingPolicyFixtures::now()),
        ),
        new BookingPolicyPresenter,
        $repository ?? $policies,
    );
}

beforeEach(function () {
    $this->policies = new FakeBookingPolicyRepository;

    $this->settings = bookingPolicySettingsOver($this->policies);

    $this->apply = fn (
        ContactFieldPreference $phone = ContactFieldPreference::Optional,
        ContactFieldPreference $email = ContactFieldPreference::Hidden,
        ContactFieldPreference $address = ContactFieldPreference::Required,
        string $businessId = FakeBusinessContext::BUSINESS_ID,
    ): mixed => $this->settings->applyContactFieldsTo(
        $businessId,
        new ContactFieldPreferences($phone, $email, $address),
    );
});

describe('reading the contact fields', function () {
    it('translates the requirements the stored policy holds into the preferences the business domain reads', function () {
        $this->policies->store(BookingPolicyFixtures::policy());

        $preferences = $this->settings->contactFieldsFor(FakeBusinessContext::BUSINESS_ID);

        expect($preferences)->toBeInstanceOf(ContactFieldPreferences::class)
            ->and($preferences->phone)->toBe(ContactFieldPreference::Hidden)
            ->and($preferences->email)->toBe(ContactFieldPreference::Required)
            ->and($preferences->address)->toBe(ContactFieldPreference::Optional)
            ->and($this->policies->saved)->toBe([]);
    });

    it('provisions the policy of a business that has none, and answers with its defaults', function () {
        $preferences = $this->settings->contactFieldsFor(FakeBusinessContext::BUSINESS_ID);

        expect($preferences->phone)->toBe(ContactFieldPreference::Required)
            ->and($preferences->email)->toBe(ContactFieldPreference::Optional)
            ->and($preferences->address)->toBe(ContactFieldPreference::Hidden)
            ->and($this->policies->saved)->toHaveCount(1)
            ->and($this->policies->saved[0]->id)->toBe(BookingPolicyFixtures::GENERATED_POLICY_ID)
            ->and($this->policies->saved[0]->businessId)->toBe(FakeBusinessContext::BUSINESS_ID);
    });

    it('reads the policy of the business it was asked about, never a neighbour', function () {
        $this->policies->store(
            BookingPolicyFixtures::policy(),
            BookingPolicyFixtures::policy(
                id: BookingPolicyFixtures::OTHER_POLICY_ID,
                businessId: BookingPolicyFixtures::OTHER_BUSINESS_ID,
                contactFields: BookingPolicyFixtures::contactFields(
                    phone: ContactFieldRequirement::Required,
                    email: ContactFieldRequirement::Required,
                    address: ContactFieldRequirement::Required,
                ),
            ),
        );

        $preferences = $this->settings->contactFieldsFor(FakeBusinessContext::BUSINESS_ID);

        expect($preferences->phone)->toBe(ContactFieldPreference::Hidden)
            ->and($this->policies->businessIdsSeen)->toBe([FakeBusinessContext::BUSINESS_ID]);
    });
});

describe('revising the contact fields', function () {
    beforeEach(function () {
        $this->policies->store(BookingPolicyFixtures::policy());
    });

    it('applies the preferences the business submitted and saves the policy', function () {
        ($this->apply)();

        expect($this->policies->saved)->toHaveCount(1)
            ->and($this->policies->saved[0]->id)->toBe(BookingPolicyFixtures::POLICY_ID)
            ->and($this->policies->saved[0]->contactFields()->phone)->toBe(ContactFieldRequirement::Optional)
            ->and($this->policies->saved[0]->contactFields()->email)->toBe(ContactFieldRequirement::Hidden)
            ->and($this->policies->saved[0]->contactFields()->address)->toBe(ContactFieldRequirement::Required);
    });

    it('translates every preference the business domain names into the requirement of the same name', function (ContactFieldPreference $preference) {
        ($this->apply)($preference, $preference, $preference);

        $saved = $this->policies->saved[0]->contactFields();

        expect($saved->phone->value)->toBe($preference->value)
            ->and($saved->email->value)->toBe($preference->value)
            ->and($saved->address->value)->toBe($preference->value);
    })->with(ContactFieldPreference::cases());

    it('touches none of the booking rules and leaves the toggle where it was', function () {
        ($this->apply)();

        $saved = $this->policies->saved[0];

        expect($saved->leadTime()->minutes)->toBe(60)
            ->and($saved->bookingWindow()->minutes())->toBe(43200)
            ->and($saved->slotGranularity()->minutes)->toBe(30)
            ->and($saved->cancellationWindow()->minutes)->toBe(240)
            ->and($saved->policyMessage()->toString())->toBe(BookingPolicyFixtures::POLICY_MESSAGE)
            ->and($saved->isDisplayedOnBookingPage())->toBeTrue();
    });

    it('answers with what it just wrote on the next read', function () {
        ($this->apply)();

        $preferences = $this->settings->contactFieldsFor(FakeBusinessContext::BUSINESS_ID);

        expect($preferences->phone)->toBe(ContactFieldPreference::Optional)
            ->and($preferences->email)->toBe(ContactFieldPreference::Hidden)
            ->and($preferences->address)->toBe(ContactFieldPreference::Required);
    });

    it('revises the policy of the business it was handed and leaves every other one alone', function () {
        $this->policies->store(BookingPolicyFixtures::policy(
            id: BookingPolicyFixtures::OTHER_POLICY_ID,
            businessId: BookingPolicyFixtures::OTHER_BUSINESS_ID,
        ));

        ($this->apply)(businessId: BookingPolicyFixtures::OTHER_BUSINESS_ID);

        expect($this->policies->saved)->toHaveCount(1)
            ->and($this->policies->saved[0]->id)->toBe(BookingPolicyFixtures::OTHER_POLICY_ID)
            ->and($this->policies->saved[0]->businessId)->toBe(BookingPolicyFixtures::OTHER_BUSINESS_ID)
            ->and($this->policies->findForBusiness(FakeBusinessContext::BUSINESS_ID)?->contactFields())
            ->toEqual(BookingPolicyFixtures::contactFields());
    });

    it('provisions a policy for the business it was handed when that business had none', function () {
        ($this->apply)(businessId: BookingPolicyFixtures::OTHER_BUSINESS_ID);

        expect($this->policies->saved)->toHaveCount(2)
            ->and($this->policies->saved[0]->id)->toBe(BookingPolicyFixtures::GENERATED_POLICY_ID)
            ->and($this->policies->saved[1]->businessId)->toBe(BookingPolicyFixtures::OTHER_BUSINESS_ID)
            ->and($this->policies->saved[1]->contactFields()->address)->toBe(ContactFieldRequirement::Required);
    });
});

describe('the rollback contract', function () {
    it('hands nothing back, so no use case response can cross the port', function () {
        $this->policies->store(BookingPolicyFixtures::policy());

        expect(($this->apply)())->toBeNull()
            ->and((string) (new ReflectionMethod(BookingPolicySettings::class, 'applyContactFieldsTo'))->getReturnType())
            ->toBe('void');
    });

    it('lets a neighbour refusal out by throwing, having saved nothing', function () {
        $failure = BookingPolicyNotFound::withId(BookingPolicyFixtures::POLICY_ID);

        $current = Mockery::mock(CurrentBookingPolicy::class);
        $current->shouldReceive('forBusiness')->once()->andThrow($failure);

        $settings = bookingPolicySettingsOver($this->policies, current: $current);

        expect(fn () => $settings->applyContactFieldsTo(
            FakeBusinessContext::BUSINESS_ID,
            new ContactFieldPreferences(
                ContactFieldPreference::Required,
                ContactFieldPreference::Optional,
                ContactFieldPreference::Hidden,
            ),
        ))->toThrow($failure)
            ->and($this->policies->saved)->toBe([]);
    });

    it('lets an infrastructure error from the write out untouched', function () {
        $bug = new RuntimeException('the booking policies table is gone');

        $this->policies->store(BookingPolicyFixtures::policy());

        $repository = Mockery::mock(BookingPolicyRepository::class);
        $repository->shouldReceive('save')->once()->andThrow($bug);

        $settings = bookingPolicySettingsOver($this->policies, repository: $repository);

        expect(fn () => $settings->applyContactFieldsTo(
            FakeBusinessContext::BUSINESS_ID,
            new ContactFieldPreferences(
                ContactFieldPreference::Required,
                ContactFieldPreference::Optional,
                ContactFieldPreference::Hidden,
            ),
        ))->toThrow($bug);
    });
});

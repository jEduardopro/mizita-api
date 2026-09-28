<?php

declare(strict_types=1);

use App\Domains\Staff\Infrastructure\Gateways\ServicesServiceAssignments;
use Tests\Support\FakeBusinessContext;
use Tests\Support\Services\FakeServiceRepository;
use Tests\Support\Services\ServiceFixtures;
use Tests\Support\Staff\StaffFixtures;

beforeEach(function () {
    $this->services = new FakeServiceRepository;
    $this->gateway = new ServicesServiceAssignments($this->services);
});

it('answers with the members assigned to at least one active service, in the order asked', function () {
    $this->services->store(
        ServiceFixtures::service(staffIds: [StaffFixtures::THIRD_MEMBER_ID]),
        ServiceFixtures::service(id: ServiceFixtures::SECOND_SERVICE_ID, slug: 'barba', staffIds: [StaffFixtures::MEMBER_ID, StaffFixtures::THIRD_MEMBER_ID]),
    );

    expect($this->gateway->staffOfferingServices(FakeBusinessContext::BUSINESS_ID, [
        StaffFixtures::MEMBER_ID,
        StaffFixtures::SECOND_MEMBER_ID,
        StaffFixtures::THIRD_MEMBER_ID,
    ]))->toBe([StaffFixtures::MEMBER_ID, StaffFixtures::THIRD_MEMBER_ID]);
});

it('does not count a service that is inactive', function () {
    $this->services->store(ServiceFixtures::service(active: false, staffIds: [StaffFixtures::MEMBER_ID]));

    expect($this->gateway->staffOfferingServices(FakeBusinessContext::BUSINESS_ID, [StaffFixtures::MEMBER_ID]))->toBe([]);
});

it('does not count a service of another business', function () {
    $this->services->store(ServiceFixtures::service(businessId: StaffFixtures::OTHER_BUSINESS_ID, staffIds: [StaffFixtures::MEMBER_ID]));

    expect($this->gateway->staffOfferingServices(FakeBusinessContext::BUSINESS_ID, [StaffFixtures::MEMBER_ID]))->toBe([])
        ->and($this->services->businessIdsSeen)->toBe([FakeBusinessContext::BUSINESS_ID]);
});

it('reads the services of the business once for the whole batch', function () {
    $this->gateway->staffOfferingServices(FakeBusinessContext::BUSINESS_ID, [StaffFixtures::MEMBER_ID, StaffFixtures::SECOND_MEMBER_ID]);

    expect($this->services->businessIdsSeen)->toBe([FakeBusinessContext::BUSINESS_ID]);
});

it('asks nothing for an empty team', function () {
    expect($this->gateway->staffOfferingServices(FakeBusinessContext::BUSINESS_ID, []))->toBe([])
        ->and($this->services->businessIdsSeen)->toBe([]);
});

<?php

declare(strict_types=1);

use App\Domains\PublicCatalog\Infrastructure\Http\Resources\PublicBookingResource;
use Tests\Support\PublicCatalog\PublicCatalogFixtures;
use Tests\TestCase;

uses(TestCase::class);

beforeEach(function () {
    $this->serialized = PublicBookingResource::make(PublicCatalogFixtures::guestBooking())
        ->response()
        ->getData(true);
});

it('wraps the booking in the data envelope', function () {
    expect($this->serialized)->toHaveKey('data');
});

it('serializes exactly the keys a visitor may see, in order', function () {
    expect(array_keys($this->serialized['data']))->toBe([
        'reference_code',
        'service_name',
        'staff_member_name',
        'starts_at',
        'ends_at',
        'duration_minutes',
        'status',
        'cancelled_at',
        'cancellation_window_minutes',
        'changeable',
    ]);
});

it('discloses no customer name', function () {
    expect($this->serialized['data'])->not->toHaveKey('customer_name');
});

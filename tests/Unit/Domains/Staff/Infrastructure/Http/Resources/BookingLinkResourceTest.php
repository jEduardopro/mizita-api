<?php

declare(strict_types=1);

use App\Domains\Staff\Application\Dtos\BookingLinkData;
use App\Domains\Staff\Infrastructure\Http\Resources\BookingLinkResource;
use Tests\Support\Staff\StaffFixtures;
use Tests\TestCase;

uses(TestCase::class);

it('serializes exactly the slug and the absolute url, wrapped in data', function () {
    $body = BookingLinkResource::make(new BookingLinkData(StaffFixtures::BOOKING_SLUG, StaffFixtures::BOOKING_URL))
        ->response()
        ->getData(true);

    expect($body)->toBe([
        'data' => [
            'booking_slug' => StaffFixtures::BOOKING_SLUG,
            'booking_url' => StaffFixtures::BOOKING_URL,
        ],
    ]);
});

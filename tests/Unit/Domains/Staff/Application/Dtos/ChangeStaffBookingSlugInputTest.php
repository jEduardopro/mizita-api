<?php

declare(strict_types=1);

use App\Domains\Staff\Application\Dtos\ChangeStaffBookingSlugInput;
use App\Domains\Staff\Exceptions\InvalidBookingSlug;
use App\Domains\Staff\Exceptions\StaffMemberNotFound;
use App\Shared\Contracts\DomainFailure;
use Tests\Support\Staff\StaffFixtures;

it('builds itself from the payload and the member named in the url', function () {
    $input = ChangeStaffBookingSlugInput::fromRequest(['slug' => 'ada-la-barbera'], StaffFixtures::MEMBER_ID);

    expect($input->staffMemberId)->toBe(StaffFixtures::MEMBER_ID)
        ->and($input->slug)->toBe('ada-la-barbera');
});

it('accepts a well formed payload', function () {
    $input = ChangeStaffBookingSlugInput::fromRequest(['slug' => 'ada-la-barbera'], StaffFixtures::MEMBER_ID);

    expect(fn () => $input->validate())->not->toThrow(Throwable::class)
        ->and($input->toBookingSlug()->value)->toBe('ada-la-barbera');
});

it('trims the slug before it judges and before it hands it on', function () {
    $input = ChangeStaffBookingSlugInput::fromRequest(['slug' => "  ada-la-barbera\t"], StaffFixtures::MEMBER_ID);

    expect(fn () => $input->validate())->not->toThrow(Throwable::class)
        ->and($input->toBookingSlug()->value)->toBe('ada-la-barbera');
});

it('reads a missing or non string slug as an empty one rather than failing in php', function (array $payload) {
    $input = ChangeStaffBookingSlugInput::fromRequest($payload, StaffFixtures::MEMBER_ID);

    expect($input->slug)->toBe('')
        ->and(fn () => $input->validate())->toThrow(InvalidBookingSlug::class);
})->with([
    'no slug key' => [[]],
    'null' => [['slug' => null]],
    'an int' => [['slug' => 42]],
    'an array' => [['slug' => ['ada']]],
]);

it('refuses a slug the form request would have refused', function (string $slug) {
    $input = ChangeStaffBookingSlugInput::fromRequest(['slug' => $slug], StaffFixtures::MEMBER_ID);

    try {
        $input->validate();
        $thrown = null;
    } catch (Throwable $failure) {
        $thrown = $failure;
    }

    expect($thrown)->toBeInstanceOf(InvalidBookingSlug::class)
        ->and($thrown)->toBeInstanceOf(DomainFailure::class);
})->with([
    'empty' => '',
    'whitespace' => '   ',
    'uppercase' => 'Ada',
    'accented' => 'josé-pablo',
    'spaces inside' => 'ada la barbera',
    'underscore' => 'ada_la_barbera',
    'leading hyphen' => '-ada',
    'double hyphen' => 'ada--la',
    'too long' => str_repeat('a', 61),
]);

it('refuses a member that is not a uuid as a member that is not there, before judging the slug', function (string $staffMemberId) {
    expect(fn () => ChangeStaffBookingSlugInput::fromRequest(['slug' => 'Not A Slug'], $staffMemberId)->validate())
        ->toThrow(StaffMemberNotFound::class);
})->with([
    'empty' => '',
    'an int id' => '42',
    'garbage' => 'not-a-uuid',
]);

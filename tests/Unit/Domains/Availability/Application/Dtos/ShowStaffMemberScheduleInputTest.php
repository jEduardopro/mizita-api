<?php

declare(strict_types=1);

use App\Domains\Availability\Application\Dtos\ShowStaffMemberScheduleInput;
use App\Domains\Availability\Exceptions\StaffMemberNotFound;
use App\Shared\Contracts\DomainFailure;
use App\Shared\ValueObjects\DomainFailureKind;
use Tests\Support\Availability\ScheduleFixtures;

it('accepts a staff member named by a uuid', function (string $staffMemberId) {
    expect(fn () => (new ShowStaffMemberScheduleInput($staffMemberId))->validate())->not->toThrow(Throwable::class);
})->with([
    'a time ordered uuid' => ScheduleFixtures::STAFF_ID,
    'uppercase' => 'F47AC10B-58CC-4372-A567-0E02B2C3D479',
]);

it('reports a staff member it cannot even look up as not found', function (string $staffMemberId) {
    expect(fn () => (new ShowStaffMemberScheduleInput($staffMemberId))->validate())->toThrow(StaffMemberNotFound::class);
})->with('malformed staff member ids for showing');

it('refuses with a not found failure the transport can classify', function (string $staffMemberId) {
    try {
        (new ShowStaffMemberScheduleInput($staffMemberId))->validate();
    } catch (StaffMemberNotFound $failure) {
        expect($failure)->toBeInstanceOf(DomainFailure::class)
            ->and($failure->errorCode())->toBe('staff_member_not_found')
            ->and($failure->kind())->toBe(DomainFailureKind::NotFound);

        return;
    }

    $this->fail('validate() accepted a staff member id that is not a uuid.');
})->with('malformed staff member ids for showing');

dataset('malformed staff member ids for showing', [
    'empty' => '',
    'whitespace only' => '   ',
    'a row number' => '1',
    'a word' => 'ada',
    'surrounding whitespace' => ' '.ScheduleFixtures::STAFF_ID.' ',
    'a trailing newline' => ScheduleFixtures::STAFF_ID."\n",
    'one character short' => '01930000-0000-7000-8000-0000000000d',
    'accented characters' => 'ñ1930000-0000-7000-8000-0000000000d1',
]);

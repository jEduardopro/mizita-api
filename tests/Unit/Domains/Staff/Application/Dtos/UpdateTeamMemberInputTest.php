<?php

declare(strict_types=1);

use App\Domains\Staff\Application\Dtos\PhoneChange;
use App\Domains\Staff\Application\Dtos\TextChange;
use App\Domains\Staff\Application\Dtos\UpdateTeamMemberInput;
use App\Domains\Staff\Exceptions\InvalidProfileAbout;
use App\Domains\Staff\Exceptions\InvalidProfileJobTitle;
use App\Domains\Staff\Exceptions\InvalidProfileName;
use App\Domains\Staff\Exceptions\InvalidProfilePhone;
use App\Domains\Staff\Exceptions\InvalidTeamLevel;
use App\Domains\Staff\Exceptions\StaffMemberNotFound;
use App\Domains\Staff\ValueObjects\StaffRole;
use App\Shared\Contracts\DomainFailure;
use Tests\Support\PhoneNumbers;
use Tests\Support\Staff\StaffFixtures;

/**
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function teamMemberChanges(array $overrides = []): array
{
    return [
        'name' => 'Grace Hopper',
        'job_title' => 'Colorista',
        'about' => 'Especialista en rubios.',
        'phone' => ['country_code' => 'MX', 'national_number' => PhoneNumbers::MX_NATIONAL_NUMBER],
        'level' => 'no_access',
        ...$overrides,
    ];
}

function teamMemberChangeRefusal(UpdateTeamMemberInput $input): ?Throwable
{
    try {
        $input->validate();
    } catch (Throwable $refusal) {
        return $refusal;
    }

    return null;
}

describe('building itself from a request payload', function () {
    it('assembles every field from a well formed payload', function () {
        $input = UpdateTeamMemberInput::fromRequest(teamMemberChanges(), StaffFixtures::MEMBER_ID);

        expect($input->staffMemberId)->toBe(StaffFixtures::MEMBER_ID)
            ->and($input->name)->toBe('Grace Hopper')
            ->and($input->jobTitle)->toEqual(new TextChange('Colorista'))
            ->and($input->about)->toEqual(new TextChange('Especialista en rubios.'))
            ->and($input->phone)->toBeInstanceOf(PhoneChange::class)
            ->and($input->phone?->phone?->countryCode)->toBe('MX')
            ->and($input->phone?->phone?->nationalNumber)->toBe(PhoneNumbers::MX_NATIONAL_NUMBER)
            ->and($input->level)->toBe('no_access');
    });

    it('takes the member from the route, never from the body', function () {
        $input = UpdateTeamMemberInput::fromRequest(
            teamMemberChanges(['staff_member_id' => StaffFixtures::SECOND_MEMBER_ID, 'id' => StaffFixtures::SECOND_MEMBER_ID]),
            StaffFixtures::MEMBER_ID,
        );

        expect($input->staffMemberId)->toBe(StaffFixtures::MEMBER_ID);
    });

    it('reads every missing key as a field left untouched', function () {
        $input = UpdateTeamMemberInput::fromRequest([], StaffFixtures::MEMBER_ID);

        expect($input->name)->toBeNull()
            ->and($input->jobTitle)->toBeNull()
            ->and($input->about)->toBeNull()
            ->and($input->phone)->toBeNull()
            ->and($input->level)->toBeNull()
            ->and(teamMemberChangeRefusal($input))->toBeNull();
    });

    it('reads a text field sent as null, blank or not text as a request to clear it', function (mixed $value) {
        $input = UpdateTeamMemberInput::fromRequest(['job_title' => $value, 'about' => $value], StaffFixtures::MEMBER_ID);

        expect($input->jobTitle)->toEqual(new TextChange(null))
            ->and($input->about)->toEqual(new TextChange(null));
    })->with([
        'null' => [null],
        'empty' => [''],
        'whitespace only' => ["  \n"],
        'a number' => [42],
    ]);

    it('reads a phone sent as null or empty as a request to remove it', function (mixed $phone) {
        $input = UpdateTeamMemberInput::fromRequest(['phone' => $phone], StaffFixtures::MEMBER_ID);

        expect($input->phone)->toEqual(new PhoneChange(null))
            ->and(teamMemberChangeRefusal($input))->toBeNull();
    })->with([
        'null' => [null],
        'an empty section' => [[]],
    ]);

    it('reads a name or a level that is not text as left untouched', function () {
        $input = UpdateTeamMemberInput::fromRequest(['name' => ['Grace'], 'level' => 3], StaffFixtures::MEMBER_ID);

        expect($input->name)->toBeNull()
            ->and($input->level)->toBeNull();
    });
});

describe('validating the changes', function () {
    it('returns silently for a well formed payload', function () {
        expect(teamMemberChangeRefusal(UpdateTeamMemberInput::fromRequest(teamMemberChanges(), StaffFixtures::MEMBER_ID)))->toBeNull();
    });

    it('takes a name of exactly the maximum length', function () {
        $input = UpdateTeamMemberInput::fromRequest(['name' => str_repeat('ñ', UpdateTeamMemberInput::MAXIMUM_NAME_LENGTH)], StaffFixtures::MEMBER_ID);

        expect(teamMemberChangeRefusal($input))->toBeNull();
    });

    it('takes either level an owner may hand out', function (string $level) {
        expect(teamMemberChangeRefusal(UpdateTeamMemberInput::fromRequest(['level' => $level], StaffFixtures::MEMBER_ID)))->toBeNull();
    })->with(['staff', 'no_access']);

    it('refuses the payload with the failure its first broken rule names', function (array $payload, string $staffMemberId, string $exception) {
        $refusal = teamMemberChangeRefusal(UpdateTeamMemberInput::fromRequest($payload, $staffMemberId));

        expect($refusal)->toBeInstanceOf($exception)
            ->and($refusal)->toBeInstanceOf(DomainFailure::class);
    })->with([
        'a member that is not a uuid' => [teamMemberChanges(), 'not-a-uuid', StaffMemberNotFound::class],
        'a blank name' => [['name' => ''], StaffFixtures::MEMBER_ID, InvalidProfileName::class],
        'a whitespace-only name' => [['name' => " \t"], StaffFixtures::MEMBER_ID, InvalidProfileName::class],
        'a name past the limit' => [['name' => str_repeat('a', 256)], StaffFixtures::MEMBER_ID, InvalidProfileName::class],
        'a job title past the limit' => [['job_title' => str_repeat('a', 121)], StaffFixtures::MEMBER_ID, InvalidProfileJobTitle::class],
        'a description past the limit' => [['about' => str_repeat('a', 1001)], StaffFixtures::MEMBER_ID, InvalidProfileAbout::class],
        'a three letter country' => [['phone' => ['country_code' => 'MEX', 'national_number' => '5512345678']], StaffFixtures::MEMBER_ID, InvalidProfilePhone::class],
        'a phone with no number' => [['phone' => ['country_code' => 'MX']], StaffFixtures::MEMBER_ID, InvalidProfilePhone::class],
        'the owner level' => [['level' => 'owner'], StaffFixtures::MEMBER_ID, InvalidTeamLevel::class],
        'an unknown level' => [['level' => 'admin'], StaffFixtures::MEMBER_ID, InvalidTeamLevel::class],
        'a bad member before a bad name' => [['name' => ''], 'not-a-uuid', StaffMemberNotFound::class],
    ]);
});

describe('what it hands the use case', function () {
    it('trims a job title and a description, and clears them when blank', function () {
        $input = UpdateTeamMemberInput::fromRequest([], StaffFixtures::MEMBER_ID);

        expect($input->toJobTitle(new TextChange('  Colorista '))?->value)->toBe('Colorista')
            ->and($input->toAbout(new TextChange("\nEspecialista en rubios.\n"))?->value)->toBe('Especialista en rubios.')
            ->and($input->toJobTitle(new TextChange(null)))->toBeNull()
            ->and($input->toAbout(new TextChange(null)))->toBeNull();
    });

    it('resolves the level to a role', function () {
        expect(UpdateTeamMemberInput::fromRequest([], StaffFixtures::MEMBER_ID)->toLevel('no_access'))->toBe(StaffRole::NoAccess);
    });
});

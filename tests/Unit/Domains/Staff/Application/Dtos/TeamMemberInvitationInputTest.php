<?php

declare(strict_types=1);

use App\Domains\Staff\Application\Dtos\TeamMemberInvitationInput;
use App\Domains\Staff\Exceptions\InvalidProfileName;
use App\Domains\Staff\Exceptions\InvalidTeamLevel;
use App\Domains\Staff\Exceptions\InvalidTeamMemberEmail;
use App\Domains\Staff\ValueObjects\StaffRole;
use App\Shared\Contracts\DomainFailure;

function teamInvitationRowRefusal(TeamMemberInvitationInput $row): ?Throwable
{
    try {
        $row->validate();
    } catch (Throwable $refusal) {
        return $refusal;
    }

    return null;
}

describe('building itself from one row of the payload', function () {
    it('reads the name, the email and the level of a well formed row', function () {
        $row = TeamMemberInvitationInput::fromPayload(['name' => 'Grace Hopper', 'email' => 'grace@example.com', 'level' => 'staff']);

        expect($row->name)->toBe('Grace Hopper')
            ->and($row->email)->toBe('grace@example.com')
            ->and($row->level)->toBe('staff');
    });

    it('reads every missing or non-text field as empty, never as a PHP error', function (mixed $payload) {
        $row = TeamMemberInvitationInput::fromPayload($payload);

        expect($row->name)->toBe('')
            ->and($row->email)->toBe('')
            ->and($row->level)->toBe('');
    })->with([
        'an empty row' => [[]],
        'a row that is not an array' => ['grace@example.com'],
        'null' => [null],
        'non-text values' => [['name' => ['Grace'], 'email' => 42, 'level' => true]],
    ]);

    it('turns a row with every key missing into a domain failure', function () {
        expect(teamInvitationRowRefusal(TeamMemberInvitationInput::fromPayload([])))
            ->toBeInstanceOf(InvalidProfileName::class);
    });
});

describe('validating a row', function () {
    it('returns silently for a well formed row at either assignable level', function (string $level) {
        expect(teamInvitationRowRefusal(new TeamMemberInvitationInput('Grace Hopper', 'grace@example.com', $level)))->toBeNull();
    })->with(['staff', 'no_access']);

    it('takes a name of exactly the maximum length, accents included', function () {
        $name = str_repeat('ñ', TeamMemberInvitationInput::MAXIMUM_NAME_LENGTH);

        expect(teamInvitationRowRefusal(new TeamMemberInvitationInput($name, 'grace@example.com', 'staff')))->toBeNull();
    });

    it('refuses the row with the failure its first broken rule names', function (array $row, string $exception) {
        $refusal = teamInvitationRowRefusal(TeamMemberInvitationInput::fromPayload($row));

        expect($refusal)->toBeInstanceOf($exception)
            ->and($refusal)->toBeInstanceOf(DomainFailure::class);
    })->with([
        'a missing name' => [['email' => 'grace@example.com', 'level' => 'staff'], InvalidProfileName::class],
        'a whitespace-only name' => [['name' => " \t ", 'email' => 'grace@example.com', 'level' => 'staff'], InvalidProfileName::class],
        'a name past the limit' => [['name' => str_repeat('a', 256), 'email' => 'grace@example.com', 'level' => 'staff'], InvalidProfileName::class],
        'a missing email' => [['name' => 'Grace', 'level' => 'staff'], InvalidTeamMemberEmail::class],
        'a malformed email' => [['name' => 'Grace', 'email' => 'grace.example.com', 'level' => 'staff'], InvalidTeamMemberEmail::class],
        'a missing level' => [['name' => 'Grace', 'email' => 'grace@example.com'], InvalidTeamLevel::class],
        'the owner level' => [['name' => 'Grace', 'email' => 'grace@example.com', 'level' => 'owner'], InvalidTeamLevel::class],
        'an unknown level' => [['name' => 'Grace', 'email' => 'grace@example.com', 'level' => 'admin'], InvalidTeamLevel::class],
        'a broken name before a broken email' => [['name' => '', 'email' => 'nope', 'level' => 'admin'], InvalidProfileName::class],
        'a broken email before a broken level' => [['name' => 'Grace', 'email' => 'nope', 'level' => 'admin'], InvalidTeamMemberEmail::class],
    ]);
});

describe('what the row hands the use case', function () {
    it('trims the name', function () {
        expect((new TeamMemberInvitationInput('  Grace Hopper ', 'grace@example.com', 'staff'))->trimmedName())->toBe('Grace Hopper');
    });

    it('normalizes the email', function () {
        expect((new TeamMemberInvitationInput('Grace', ' Grace@Example.COM ', 'staff'))->toEmail()->value)->toBe('grace@example.com');
    });

    it('resolves the level to a role', function (string $level, StaffRole $role) {
        expect((new TeamMemberInvitationInput('Grace', 'grace@example.com', $level))->toLevel())->toBe($role);
    })->with([
        'staff' => ['staff', StaffRole::Member],
        'no access' => ['no_access', StaffRole::NoAccess],
    ]);
});

<?php

declare(strict_types=1);

use App\Domains\Staff\Application\Dtos\InviteTeamMembersInput;
use App\Domains\Staff\Application\Dtos\TeamMemberInvitationInput;
use App\Domains\Staff\Exceptions\DuplicateTeamInvitationEmail;
use App\Domains\Staff\Exceptions\InvalidProfileName;
use App\Domains\Staff\Exceptions\InvalidTeamInvitation;
use App\Domains\Staff\Exceptions\InvalidTeamLevel;
use App\Domains\Staff\Exceptions\InvalidTeamMemberEmail;
use App\Shared\Contracts\DomainFailure;

/**
 * @return array{name: string, email: string, level: string}
 */
function invitedRow(string $email, string $level = 'staff', string $name = 'Grace Hopper'): array
{
    return ['name' => $name, 'email' => $email, 'level' => $level];
}

/**
 * @return list<array{name: string, email: string, level: string}>
 */
function invitedRows(int $count): array
{
    return array_map(static fn (int $index): array => invitedRow("member{$index}@example.com"), range(1, $count));
}

function teamInvitationRefusal(InviteTeamMembersInput $input): ?Throwable
{
    try {
        $input->validate();
    } catch (Throwable $refusal) {
        return $refusal;
    }

    return null;
}

describe('building itself from a request payload', function () {
    it('builds one row per member, in the order they were sent', function () {
        $input = InviteTeamMembersInput::fromRequest(['members' => [
            invitedRow('grace@example.com'),
            invitedRow('ada@example.com', 'no_access', 'Ada Lovelace'),
        ]]);

        expect($input->members)->toHaveCount(2)
            ->and($input->members[0])->toBeInstanceOf(TeamMemberInvitationInput::class)
            ->and($input->members[0]->email)->toBe('grace@example.com')
            ->and($input->members[1]->name)->toBe('Ada Lovelace')
            ->and($input->members[1]->level)->toBe('no_access');
    });

    it('reindexes the members, whatever keys they were sent under', function () {
        $input = InviteTeamMembersInput::fromRequest(['members' => ['first' => invitedRow('grace@example.com'), 7 => invitedRow('ada@example.com')]]);

        expect(array_keys($input->members))->toBe([0, 1]);
    });

    it('reads a missing or malformed members list as an empty invitation', function (array $payload) {
        $input = InviteTeamMembersInput::fromRequest($payload);

        expect($input->members)->toBe([])
            ->and(teamInvitationRefusal($input))->toBeInstanceOf(InvalidTeamInvitation::class);
    })->with([
        'no members key' => [[]],
        'members as text' => [['members' => 'grace@example.com']],
        'members as null' => [['members' => null]],
    ]);

    it('turns a row that is not an array into a domain failure rather than a PHP error', function () {
        expect(teamInvitationRefusal(InviteTeamMembersInput::fromRequest(['members' => ['grace@example.com']])))
            ->toBeInstanceOf(InvalidProfileName::class);
    });
});

describe('validating the batch', function () {
    it('returns silently for a single member', function () {
        expect(teamInvitationRefusal(InviteTeamMembersInput::fromRequest(['members' => invitedRows(1)])))->toBeNull();
    });

    it('returns silently for exactly the maximum number of members', function () {
        expect(teamInvitationRefusal(InviteTeamMembersInput::fromRequest(['members' => invitedRows(InviteTeamMembersInput::MAXIMUM_MEMBERS)])))->toBeNull();
    });

    it('refuses an invitation with nobody in it', function () {
        expect(teamInvitationRefusal(new InviteTeamMembersInput([]))?->getMessage())
            ->toBe('An invitation needs at least one team member.');
    });

    it('refuses an invitation one member past the maximum', function () {
        $refusal = teamInvitationRefusal(InviteTeamMembersInput::fromRequest(['members' => invitedRows(21)]));

        expect($refusal)->toBeInstanceOf(InvalidTeamInvitation::class)
            ->and($refusal?->getMessage())->toBe('An invitation takes up to [20] team members at once.');
    });

    it('refuses the whole batch when any one row is broken', function (array $brokenRow, string $exception) {
        $refusal = teamInvitationRefusal(InviteTeamMembersInput::fromRequest(['members' => [
            invitedRow('grace@example.com'),
            $brokenRow,
        ]]));

        expect($refusal)->toBeInstanceOf($exception)
            ->and($refusal)->toBeInstanceOf(DomainFailure::class);
    })->with([
        'a blank name' => [invitedRow('ada@example.com', name: '  '), InvalidProfileName::class],
        'a malformed email' => [invitedRow('ada.example.com'), InvalidTeamMemberEmail::class],
        'the owner level' => [invitedRow('ada@example.com', 'owner'), InvalidTeamLevel::class],
    ]);

    it('refuses the same person twice in one batch, however the address was cased or padded', function (string $again) {
        $refusal = teamInvitationRefusal(InviteTeamMembersInput::fromRequest(['members' => [
            invitedRow('grace@example.com'),
            invitedRow('ada@example.com'),
            invitedRow($again, 'no_access'),
        ]]));

        expect($refusal)->toBeInstanceOf(DuplicateTeamInvitationEmail::class)
            ->and($refusal?->getMessage())->toBe('[grace@example.com] appears more than once in the same invitation.');
    })->with([
        'identical' => 'grace@example.com',
        'uppercased' => 'GRACE@EXAMPLE.COM',
        'padded' => '  grace@example.com ',
    ]);

    it('checks the size of the batch before any row', function () {
        $rows = array_fill(0, 21, invitedRow('not-an-email'));

        expect(teamInvitationRefusal(InviteTeamMembersInput::fromRequest(['members' => $rows])))
            ->toBeInstanceOf(InvalidTeamInvitation::class);
    });

    it('reports a broken row before a duplicate', function () {
        $refusal = teamInvitationRefusal(InviteTeamMembersInput::fromRequest(['members' => [
            invitedRow('grace@example.com'),
            invitedRow('grace@example.com'),
            invitedRow('ada@example.com', 'owner'),
        ]]));

        expect($refusal)->toBeInstanceOf(InvalidTeamLevel::class);
    });
});

it('hands over every email normalized, in batch order', function () {
    $input = InviteTeamMembersInput::fromRequest(['members' => [
        invitedRow(' Grace@Example.com'),
        invitedRow('ADA@example.com '),
    ]]);

    expect($input->emails())->toBe(['grace@example.com', 'ada@example.com']);
});

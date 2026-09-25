<?php

declare(strict_types=1);

use App\Domains\Staff\Exceptions\InvalidTeamMemberEmail;
use App\Domains\Staff\ValueObjects\InviteeEmail;
use App\Shared\Contracts\DomainFailure;

function inviteeEmailOfLength(int $length): string
{
    $localPart = str_repeat('a', 64);
    $labels = str_repeat('b', 63).'.'.str_repeat('c', 63).'.';
    $lastLabel = str_repeat('d', $length - mb_strlen($localPart.'@'.$labels.'.com'));

    return $localPart.'@'.$labels.$lastLabel.'.com';
}

function inviteeEmailRefusal(string $email): ?Throwable
{
    try {
        InviteeEmail::fromString($email);
    } catch (Throwable $thrown) {
        return $thrown;
    }

    return null;
}

it('keeps a well formed address', function () {
    expect(InviteeEmail::fromString('grace@example.com')->value)->toBe('grace@example.com');
});

it('lowercases and trims the address, so the same person is never invited twice', function () {
    expect(InviteeEmail::fromString("  Grace.Hopper@Example.COM \n")->value)->toBe('grace.hopper@example.com');
});

it('takes the longest address the email standard allows', function () {
    $email = inviteeEmailOfLength(254);

    expect(mb_strlen($email))->toBe(254)
        ->and(InviteeEmail::fromString($email)->value)->toBe($email);
});

it('refuses an address with no characters but whitespace as empty', function (string $email) {
    $thrown = inviteeEmailRefusal($email);

    expect($thrown)->toBeInstanceOf(InvalidTeamMemberEmail::class)
        ->and($thrown)->toBeInstanceOf(DomainFailure::class)
        ->and($thrown?->getMessage())->toBe('A team member needs an email address.');
})->with([
    'empty' => '',
    'spaces' => '   ',
    'tab and newline' => "\t\n",
]);

it('refuses an address one character past the maximum length', function () {
    $thrown = inviteeEmailRefusal(inviteeEmailOfLength(255));

    expect($thrown)->toBeInstanceOf(InvalidTeamMemberEmail::class)
        ->and($thrown)->toBeInstanceOf(DomainFailure::class)
        ->and($thrown?->getMessage())->toBe('A team member email takes up to [254] characters.');
});

it('refuses an address that is not one', function (string $email) {
    $thrown = inviteeEmailRefusal($email);

    expect($thrown)->toBeInstanceOf(InvalidTeamMemberEmail::class)
        ->and($thrown?->getMessage())->toEndWith('is not an email address.');
})->with([
    'no at sign' => 'grace.example.com',
    'no domain' => 'grace@',
    'no local part' => '@example.com',
    'two at signs' => 'grace@@example.com',
    'a space inside' => 'grace hopper@example.com',
    'an accented local part' => 'josé@example.com',
]);

it('names the lowercased address it turned down', function () {
    expect(inviteeEmailRefusal(' NOT-AN-EMAIL ')?->getMessage())->toBe('[not-an-email] is not an email address.');
});

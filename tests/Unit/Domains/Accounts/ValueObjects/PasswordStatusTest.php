<?php

declare(strict_types=1);

use App\Domains\Accounts\ValueObjects\PasswordStatus;

it('knows exactly three states a password can be in', function () {
    expect(PasswordStatus::cases())->toBe([
        PasswordStatus::Absent,
        PasswordStatus::Temporary,
        PasswordStatus::Chosen,
    ]);
});

it('decides whether a temporary password may be issued over it', function (PasswordStatus $status, bool $accepts) {
    expect($status->acceptsTemporaryPassword())->toBe($accepts);
})->with([
    'no password yet' => [PasswordStatus::Absent, true],
    'a temporary password still unchanged' => [PasswordStatus::Temporary, true],
    'a password the person chose' => [PasswordStatus::Chosen, false],
]);

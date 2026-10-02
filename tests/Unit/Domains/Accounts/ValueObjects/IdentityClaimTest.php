<?php

declare(strict_types=1);

use App\Domains\Accounts\ValueObjects\IdentityClaim;

it('says whether the claim revoked the access held before it', function (IdentityClaim $claim, bool $revoked) {
    expect($claim->revokedPriorAccess())->toBe($revoked);
})->with([
    'the proven owner' => [IdentityClaim::ProvenOwnerKeptAccess, false],
    'an unproven holder' => [IdentityClaim::UnprovenAccessRevoked, true],
]);

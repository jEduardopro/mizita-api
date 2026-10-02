<?php

declare(strict_types=1);

use App\Domains\Platform\Contracts\ImpersonationSession;
use App\Domains\Platform\Exceptions\ImpersonationConfinedToBusiness;
use App\Shared\Contracts\CurrentBusinessResolver;
use Tests\Support\Platform\ImpersonationFixtures;
use Tests\Support\Platform\RecordingImpersonationSession;
use Tests\TestCase;

uses(TestCase::class);

it('confines the resolver the container hands out to the impersonated business', function () {
    app()->instance(ImpersonationSession::class, (new RecordingImpersonationSession)->holding(ImpersonationFixtures::begun()));

    expect(fn () => app(CurrentBusinessResolver::class)->resolveFor(ImpersonationFixtures::ACCOUNT_ID, ImpersonationFixtures::OTHER_BUSINESS_ID))
        ->toThrow(ImpersonationConfinedToBusiness::class);
});

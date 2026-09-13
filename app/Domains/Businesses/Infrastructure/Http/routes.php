<?php

declare(strict_types=1);

/*
| Routes owned by the Businesses domain, for a caller who already operates one.
|
| Loaded by BusinessesServiceProvider under ['api', 'auth:sanctum', 'business'],
| so everything declared here can assume a bound BusinessContext: reading and
| editing the current business, its profile and its settings.
|
| Empty for now. Onboarding lives in onboarding.php, under a different stack,
| because the routes that create a business cannot demand one - and splitting
| the two files is what makes that visible from the filename instead of from a
| middleware array halfway down a route list.
*/

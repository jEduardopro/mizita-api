<?php

use App\Domains\Accounts\AccountsServiceProvider;
use App\Domains\Businesses\BusinessesServiceProvider;
use App\Domains\Industries\IndustriesServiceProvider;
use App\Domains\Phones\PhonesServiceProvider;
use App\Domains\Services\ServicesServiceProvider;
use App\Domains\Staff\StaffServiceProvider;
use App\Providers\AppServiceProvider;
use App\Providers\FortifyServiceProvider;

// Maintained by hand: make:domain's "already registered?" check misses once Pint has rewritten
// an entry into a short class name, and a duplicated provider registers its route group twice.
return [
    AppServiceProvider::class,
    FortifyServiceProvider::class,
    AccountsServiceProvider::class,
    BusinessesServiceProvider::class,
    IndustriesServiceProvider::class,
    PhonesServiceProvider::class,
    StaffServiceProvider::class,
    ServicesServiceProvider::class,
];

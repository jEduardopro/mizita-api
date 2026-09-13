<?php

use App\Domains\Accounts\AccountsServiceProvider;
use App\Domains\Businesses\BusinessesServiceProvider;
use App\Domains\Customers\CustomersServiceProvider;
use App\Domains\Industries\IndustriesServiceProvider;
use App\Domains\Phones\PhonesServiceProvider;
use App\Domains\Staff\StaffServiceProvider;
use App\Providers\AppServiceProvider;
use App\Providers\FortifyServiceProvider;

// Written and maintained by hand. make:domain appends to this file and its
// check for an already registered provider misses once Pint has rewritten the
// entry into a short class name, so a second run adds a duplicate - which
// registers that domain's route group twice. Every entry below appears once.
return [
    AppServiceProvider::class,
    FortifyServiceProvider::class,
    AccountsServiceProvider::class,
    BusinessesServiceProvider::class,
    CustomersServiceProvider::class,
    IndustriesServiceProvider::class,
    PhonesServiceProvider::class,
    StaffServiceProvider::class,
];

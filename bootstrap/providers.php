<?php

use App\Domains\Accounts\AccountsServiceProvider;
use App\Domains\Businesses\BusinessesServiceProvider;
use App\Domains\Customers\CustomersServiceProvider;
use App\Providers\AppServiceProvider;
use App\Providers\FortifyServiceProvider;

return [
    AppServiceProvider::class,
    FortifyServiceProvider::class,
    AccountsServiceProvider::class,
    BusinessesServiceProvider::class,
    CustomersServiceProvider::class,
];

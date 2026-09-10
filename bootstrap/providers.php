<?php

use App\Domains\Businesses\BusinessesServiceProvider;
use App\Domains\Customers\CustomersServiceProvider;
use App\Providers\AppServiceProvider;

return [
    AppServiceProvider::class,
    CustomersServiceProvider::class,
    BusinessesServiceProvider::class,
    BusinessesServiceProvider::class,
    CustomersServiceProvider::class,
];

<?php

declare(strict_types=1);

namespace App\Domains\BookingPolicies;

use App\Domains\BookingPolicies\Contracts\BookingPolicyRepository;
use App\Domains\BookingPolicies\Contracts\CurrentBookingPolicy;
use App\Domains\BookingPolicies\Infrastructure\Eloquent\EloquentBookingPolicyRepository;
use App\Domains\BookingPolicies\Infrastructure\ProvisionedCurrentBookingPolicy;
use Illuminate\Support\ServiceProvider;

final class BookingPoliciesServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(BookingPolicyRepository::class, EloquentBookingPolicyRepository::class);
        $this->app->bind(CurrentBookingPolicy::class, ProvisionedCurrentBookingPolicy::class);
    }
}

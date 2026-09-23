<?php

declare(strict_types=1);

namespace App\Domains\Businesses\Contracts;

use App\Domains\Businesses\ValueObjects\BookingPolicyPreferences;
use App\Domains\Businesses\ValueObjects\BookingPolicySnapshot;
use App\Domains\Businesses\ValueObjects\ContactFieldPreferences;

interface BookingPolicySettings
{
    public function forBusiness(string $businessId): BookingPolicySnapshot;

    public function applyTo(string $businessId, BookingPolicyPreferences $preferences): void;

    public function contactFieldsFor(string $businessId): ContactFieldPreferences;

    public function applyContactFieldsTo(string $businessId, ContactFieldPreferences $preferences): void;
}

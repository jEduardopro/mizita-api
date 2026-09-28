<?php

declare(strict_types=1);

namespace App\Domains\PublicCatalog\Infrastructure\Gateways;

use App\Domains\BookingPolicies\Contracts\BookingPolicyRepository;
use App\Domains\BookingPolicies\ValueObjects\ContactFieldRequirement;
use App\Domains\BookingPolicies\ValueObjects\ContactFields;
use App\Domains\PublicCatalog\Contracts\BookingRulesAllowance;
use App\Domains\PublicCatalog\Contracts\GuestContactFields;
use App\Domains\PublicCatalog\ValueObjects\GuestFieldRequirement;
use App\Domains\PublicCatalog\ValueObjects\GuestFormFields;

final class BookingPoliciesGuestContactFields implements GuestContactFields
{
    public function __construct(
        private readonly BookingPolicyRepository $policies,
        private readonly BookingRulesAllowance $allowance,
    ) {}

    public function forBusiness(string $businessId): GuestFormFields
    {
        $fields = $this->contactFieldsOf($businessId);

        return new GuestFormFields(
            phone: self::requirementFrom($fields->phone),
            email: self::requirementFrom($fields->email),
            address: self::requirementFrom($fields->address),
        );
    }

    private function contactFieldsOf(string $businessId): ContactFields
    {
        if (! $this->allowance->includesBookingRules($businessId)) {
            return ContactFields::defaults();
        }

        return $this->policies->findForBusiness($businessId)?->contactFields() ?? ContactFields::defaults();
    }

    private static function requirementFrom(ContactFieldRequirement $requirement): GuestFieldRequirement
    {
        return match ($requirement) {
            ContactFieldRequirement::Hidden => GuestFieldRequirement::Hidden,
            ContactFieldRequirement::Optional => GuestFieldRequirement::Optional,
            ContactFieldRequirement::Required => GuestFieldRequirement::Required,
        };
    }
}

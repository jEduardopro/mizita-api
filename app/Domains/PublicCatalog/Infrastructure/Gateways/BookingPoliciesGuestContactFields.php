<?php

declare(strict_types=1);

namespace App\Domains\PublicCatalog\Infrastructure\Gateways;

use App\Domains\BookingPolicies\Contracts\BookingPolicyRepository;
use App\Domains\BookingPolicies\ValueObjects\ContactFieldRequirement;
use App\Domains\BookingPolicies\ValueObjects\ContactFields;
use App\Domains\PublicCatalog\Contracts\GuestContactFields;
use App\Domains\PublicCatalog\ValueObjects\GuestFieldRequirement;
use App\Domains\PublicCatalog\ValueObjects\GuestFormFields;

final class BookingPoliciesGuestContactFields implements GuestContactFields
{
    public function __construct(
        private readonly BookingPolicyRepository $policies,
    ) {}

    public function forBusiness(string $businessId): GuestFormFields
    {
        $fields = $this->policies->findForBusiness($businessId)?->contactFields() ?? ContactFields::defaults();

        return new GuestFormFields(
            phone: self::requirementFrom($fields->phone),
            email: self::requirementFrom($fields->email),
            address: self::requirementFrom($fields->address),
        );
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

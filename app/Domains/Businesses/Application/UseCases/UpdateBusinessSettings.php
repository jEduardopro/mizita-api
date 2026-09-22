<?php

declare(strict_types=1);

namespace App\Domains\Businesses\Application\UseCases;

use App\Domains\Businesses\Application\Dtos\AppearanceInput;
use App\Domains\Businesses\Application\Dtos\BookingPolicyInput;
use App\Domains\Businesses\Application\Dtos\BrandDetailsInput;
use App\Domains\Businesses\Application\Dtos\BusinessSettingsData;
use App\Domains\Businesses\Application\Dtos\ContactInput;
use App\Domains\Businesses\Application\Dtos\LinksInput;
use App\Domains\Businesses\Application\Dtos\LocationInput;
use App\Domains\Businesses\Application\Dtos\PhoneNumberInput;
use App\Domains\Businesses\Application\Dtos\ScheduleInput;
use App\Domains\Businesses\Application\Dtos\UpdateBusinessSettingsInput;
use App\Domains\Businesses\Application\Presenters\BusinessSettingsPresenter;
use App\Domains\Businesses\Contracts\BookingPageSettings;
use App\Domains\Businesses\Contracts\BookingPolicySettings;
use App\Domains\Businesses\Contracts\BusinessAddressBook;
use App\Domains\Businesses\Contracts\BusinessLinkList;
use App\Domains\Businesses\Contracts\BusinessRepository;
use App\Domains\Businesses\Contracts\BusinessSchedule;
use App\Domains\Businesses\Contracts\IndustryCatalog;
use App\Domains\Businesses\Contracts\PhoneBook;
use App\Domains\Businesses\Entities\Business;
use App\Domains\Businesses\Exceptions\BusinessNameAlreadyTaken;
use App\Domains\Businesses\Exceptions\BusinessNotFound;
use App\Domains\Businesses\Exceptions\BusinessSlugAlreadyTaken;
use App\Domains\Businesses\Exceptions\UnknownIndustry;
use App\Domains\Businesses\Exceptions\UnsupportedPhoneNumber;
use App\Domains\Businesses\ValueObjects\About;
use App\Domains\Businesses\ValueObjects\BookingPageStyle;
use App\Domains\Businesses\ValueObjects\BookingPolicyPreferences;
use App\Domains\Businesses\ValueObjects\BusinessAddressSnapshot;
use App\Domains\Businesses\ValueObjects\ContactEmail;
use App\Domains\Businesses\ValueObjects\Slug;
use App\Domains\Businesses\ValueObjects\Timezone;
use App\Shared\Application\UseCaseResponse;
use App\Shared\Contracts\BusinessContext;
use App\Shared\Contracts\DomainFailure;
use App\Shared\Contracts\PhoneNumberParser;
use App\Shared\Contracts\TransactionManager;
use App\Shared\ValueObjects\CountryCode;
use App\Shared\ValueObjects\CurrencyCode;
use App\Shared\ValueObjects\PhoneNumber;

final class UpdateBusinessSettings
{
    public function __construct(
        private readonly BusinessRepository $businesses,
        private readonly IndustryCatalog $industries,
        private readonly BusinessAddressBook $addresses,
        private readonly BusinessLinkList $links,
        private readonly BusinessSchedule $schedule,
        private readonly BookingPageSettings $bookingPages,
        private readonly BookingPolicySettings $bookingPolicies,
        private readonly PhoneBook $phones,
        private readonly BusinessSettingsPresenter $presenter,
        private readonly PhoneNumberParser $phoneNumberParser,
        private readonly BusinessContext $business,
        private readonly TransactionManager $transactions,
    ) {}

    /**
     * @return UseCaseResponse<BusinessSettingsData>
     */
    public function handle(UpdateBusinessSettingsInput $input): UseCaseResponse
    {
        try {
            $input->validate();

            $businessId = $this->business->currentBusinessId();

            $this->transactions->run(function () use ($input, $businessId): void {
                $this->applyTo($businessId, $input);
            });

            return UseCaseResponse::success($this->presenter->describe($businessId));
        } catch (DomainFailure $failure) {
            return UseCaseResponse::failure($failure);
        }
    }

    /**
     * @throws BusinessNotFound
     * @throws BusinessNameAlreadyTaken
     * @throws BusinessSlugAlreadyTaken
     * @throws UnknownIndustry
     * @throws UnsupportedPhoneNumber
     */
    private function applyTo(string $businessId, UpdateBusinessSettingsInput $input): void
    {
        $business = $this->businesses->findById($businessId);

        $this->applyBrand($input->brand, $business);
        $this->applyContact($input->contact, $business, $businessId);
        $this->applyLocation($input->location, $business, $businessId);

        if ($input->changesBusinessRecord()) {
            $this->businesses->save($business);
        }

        $this->applyAppearance($input->appearance, $businessId);
        $this->applyBookingPolicy($input->bookingPolicy, $businessId);
        $this->applySchedule($input->schedule, $businessId);
        $this->applyLinks($input->links, $businessId);
    }

    /**
     * @throws BusinessNameAlreadyTaken
     * @throws BusinessSlugAlreadyTaken
     * @throws UnknownIndustry
     */
    private function applyBrand(?BrandDetailsInput $brand, Business $business): void
    {
        if ($brand === null) {
            return;
        }

        $this->renameIfChanged($brand->name, $business);
        $this->reslugIfChanged($brand->slug, $business);
        $this->reclassifyIfChanged($brand->industryId, $business);

        $business->describeAs(About::fromNullable($brand->about));
    }

    /**
     * @throws BusinessNameAlreadyTaken
     */
    private function renameIfChanged(string $submitted, Business $business): void
    {
        $name = trim($submitted);

        if (mb_strtolower($name) === mb_strtolower($business->name())) {
            return;
        }

        if ($this->businesses->existsByName($name)) {
            throw BusinessNameAlreadyTaken::for($name);
        }

        $business->rename($name);
    }

    /**
     * @throws BusinessSlugAlreadyTaken
     */
    private function reslugIfChanged(string $submitted, Business $business): void
    {
        $slug = Slug::fromString(trim($submitted));

        if ($slug->value === $business->slug()) {
            return;
        }

        if (in_array($slug->value, $this->businesses->slugsMatching($slug->value), true)) {
            throw BusinessSlugAlreadyTaken::for($slug->value);
        }

        $business->changeSlug($slug);
    }

    /**
     * @throws UnknownIndustry
     */
    private function reclassifyIfChanged(string $industryId, Business $business): void
    {
        if ($industryId === $business->industryId()) {
            return;
        }

        if (! $this->industries->exists($industryId)) {
            throw UnknownIndustry::withId($industryId);
        }

        $business->reclassify($industryId);
    }

    /**
     * @throws UnsupportedPhoneNumber
     */
    private function applyContact(?ContactInput $contact, Business $business, string $businessId): void
    {
        if ($contact === null) {
            return;
        }

        $business->changeContactEmail(
            $contact->contactEmail === null ? null : ContactEmail::fromString($contact->contactEmail),
        );

        $this->phones->replaceForBusiness($businessId, $this->parsedPhoneNumber($contact->phone));
    }

    private function applyLocation(?LocationInput $location, Business $business, string $businessId): void
    {
        if ($location === null) {
            return;
        }

        $business->changeTimezone(Timezone::fromString($location->timezone));
        $business->changeCurrency(CurrencyCode::fromString($location->currencyCode));

        $this->applyAddress($location, $businessId);
    }

    private function applyAddress(LocationInput $location, string $businessId): void
    {
        $this->addresses->replaceForBusiness($businessId, new BusinessAddressSnapshot(
            street: trim($location->street),
            city: self::trimmed($location->city),
            stateId: $location->stateId,
            postalCode: self::trimmed($location->postalCode),
            countryCode: trim($location->countryCode),
            latitude: $location->latitude,
            longitude: $location->longitude,
        ));
    }

    private function applyAppearance(?AppearanceInput $appearance, string $businessId): void
    {
        if ($appearance === null) {
            return;
        }

        $this->bookingPages->applyTo($businessId, new BookingPageStyle(
            accentColor: $appearance->accentColor,
            buttonShape: $appearance->buttonShape,
            theme: $appearance->theme,
        ));
    }

    private function applyBookingPolicy(?BookingPolicyInput $bookingPolicy, string $businessId): void
    {
        if ($bookingPolicy === null) {
            return;
        }

        $this->bookingPolicies->applyTo($businessId, new BookingPolicyPreferences(
            leadTimeMinutes: $bookingPolicy->leadTimeMinutes,
            bookingWindowMinutes: $bookingPolicy->bookingWindowMinutes,
            slotGranularityMinutes: $bookingPolicy->slotGranularityMinutes,
            cancellationWindowMinutes: $bookingPolicy->cancellationWindowMinutes,
            policyMessage: $bookingPolicy->policyMessage,
            displayOnBookingPage: $bookingPolicy->displayOnBookingPage,
        ));
    }

    private function applySchedule(?ScheduleInput $schedule, string $businessId): void
    {
        if ($schedule === null) {
            return;
        }

        $this->schedule->replaceForBusiness($businessId, $schedule->entries);
    }

    private function applyLinks(?LinksInput $links, string $businessId): void
    {
        if ($links === null) {
            return;
        }

        $this->links->replaceForBusiness($businessId, $links->links);
    }

    /**
     * @throws UnsupportedPhoneNumber
     */
    private function parsedPhoneNumber(?PhoneNumberInput $submitted): ?PhoneNumber
    {
        if ($submitted === null) {
            return null;
        }

        $country = CountryCode::tryFrom($submitted->countryCode);

        if ($country === null) {
            throw UnsupportedPhoneNumber::inCountry($submitted->countryCode);
        }

        $number = $this->phoneNumberParser->parse($country, $submitted->nationalNumber);

        if ($number === null) {
            throw UnsupportedPhoneNumber::forCountry($country);
        }

        return $number;
    }

    private static function trimmed(?string $value): ?string
    {
        return $value === null ? null : trim($value);
    }
}

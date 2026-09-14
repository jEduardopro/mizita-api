<?php

declare(strict_types=1);

namespace App\Domains\Businesses\Application\UseCases;

use App\Domains\Businesses\Application\Dtos\BusinessData;
use App\Domains\Businesses\Application\Dtos\OnboardBusinessInput;
use App\Domains\Businesses\Application\Dtos\OnboardingOutcome;
use App\Domains\Businesses\Application\Dtos\PhoneNumberInput;
use App\Domains\Businesses\Contracts\BusinessRepository;
use App\Domains\Businesses\Contracts\IndustryCatalog;
use App\Domains\Businesses\Contracts\OwnerRegistrar;
use App\Domains\Businesses\Contracts\PhoneBook;
use App\Domains\Businesses\Contracts\RoleProvisioner;
use App\Domains\Businesses\Entities\Business;
use App\Domains\Businesses\Events\BusinessCreated;
use App\Domains\Businesses\Exceptions\BusinessNameAlreadyTaken;
use App\Domains\Businesses\Exceptions\BusinessNameNotSluggable;
use App\Domains\Businesses\Exceptions\BusinessSlugAlreadyTaken;
use App\Domains\Businesses\Exceptions\InvalidBusinessName;
use App\Domains\Businesses\Exceptions\InvalidBusinessOwner;
use App\Domains\Businesses\Exceptions\InvalidBusinessTimezone;
use App\Domains\Businesses\Exceptions\OwnerAlreadyHasBusiness;
use App\Domains\Businesses\Exceptions\UnknownIndustry;
use App\Domains\Businesses\Exceptions\UnsupportedPhoneNumber;
use App\Domains\Businesses\Services\SlugAllocator;
use App\Domains\Businesses\ValueObjects\Slug;
use App\Domains\Businesses\ValueObjects\Timezone;
use App\Shared\Contracts\Clock;
use App\Shared\Contracts\IdGenerator;
use App\Shared\Contracts\PhoneNumberParser;
use App\Shared\Contracts\TransactionManager;
use App\Shared\ValueObjects\CountryCode;
use App\Shared\ValueObjects\PhoneNumber;
use Illuminate\Contracts\Events\Dispatcher;

final class OnboardBusiness
{
    public function __construct(
        private readonly BusinessRepository $businesses,
        private readonly IndustryCatalog $industries,
        private readonly RoleProvisioner $roles,
        private readonly OwnerRegistrar $owners,
        private readonly PhoneBook $phones,
        private readonly SlugAllocator $slugs,
        private readonly PhoneNumberParser $phoneNumberParser,
        private readonly IdGenerator $ids,
        private readonly Clock $clock,
        private readonly TransactionManager $transactions,
        private readonly Dispatcher $events,
    ) {}

    /**
     * @throws InvalidBusinessOwner
     * @throws InvalidBusinessName
     * @throws UnsupportedPhoneNumber
     * @throws UnknownIndustry
     * @throws BusinessNameNotSluggable
     * @throws InvalidBusinessTimezone
     * @throws BusinessNameAlreadyTaken
     * @throws BusinessSlugAlreadyTaken
     * @throws OwnerAlreadyHasBusiness
     */
    public function handle(OnboardBusinessInput $input): BusinessData
    {
        $input->validate();

        $phone = $this->parsedPhoneNumber($input->phone);

        if (! $this->industries->exists($input->industryId)) {
            throw UnknownIndustry::withId($input->industryId);
        }

        $name = trim($input->name);

        if ($this->businesses->existsByName($name)) {
            throw BusinessNameAlreadyTaken::for($name);
        }

        $base = Slug::fromName($name);
        $slug = $this->slugs->allocate($base, $this->businesses->slugsMatching($base->value));
        $timezone = Timezone::fromString($input->timezone);

        $outcome = $this->transactions->run(
            fn (): OnboardingOutcome => $this->register($input, $name, $slug, $timezone, $phone),
        );

        foreach ($outcome->events as $event) {
            $this->events->dispatch($event);
        }

        return $outcome->business;
    }

    private function register(
        OnboardBusinessInput $input,
        string $name,
        Slug $slug,
        Timezone $timezone,
        ?PhoneNumber $phone,
    ): OnboardingOutcome {
        $business = Business::create(
            id: $this->ids->next(),
            name: $name,
            slug: $slug,
            industryId: $input->industryId,
            timezone: $timezone,
            now: $this->clock->now(),
        );

        $this->businesses->save($business);

        $this->roles->provisionFor($business->id);

        $ownerEvents = $this->owners->registerOwner($business->id, $input->ownerAccountId);

        if ($phone !== null) {
            $this->phones->attachToBusiness($business->id, $phone);
        }

        return new OnboardingOutcome(
            BusinessData::fromEntity($business),
            [new BusinessCreated($business->id), ...$ownerEvents],
        );
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
}

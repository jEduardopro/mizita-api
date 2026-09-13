<?php

declare(strict_types=1);

namespace App\Domains\Businesses\Application\UseCases;

use App\Domains\Businesses\Application\Dtos\BusinessData;
use App\Domains\Businesses\Application\Dtos\OnboardBusinessInput;
use App\Domains\Businesses\Application\Dtos\OnboardingOutcome;
use App\Domains\Businesses\Contracts\BusinessRepository;
use App\Domains\Businesses\Contracts\IndustryCatalog;
use App\Domains\Businesses\Contracts\OwnerRegistrar;
use App\Domains\Businesses\Contracts\PhoneBook;
use App\Domains\Businesses\Entities\Business;
use App\Domains\Businesses\Events\BusinessCreated;
use App\Domains\Businesses\Exceptions\BusinessNameAlreadyTaken;
use App\Domains\Businesses\Exceptions\BusinessNameNotSluggable;
use App\Domains\Businesses\Exceptions\BusinessSlugAlreadyTaken;
use App\Domains\Businesses\Exceptions\InvalidBusinessTimezone;
use App\Domains\Businesses\Exceptions\OwnerAlreadyHasBusiness;
use App\Domains\Businesses\Exceptions\UnknownIndustry;
use App\Domains\Businesses\Services\SlugAllocator;
use App\Domains\Businesses\ValueObjects\Slug;
use App\Domains\Businesses\ValueObjects\Timezone;
use App\Shared\Contracts\Clock;
use App\Shared\Contracts\IdGenerator;
use App\Shared\Contracts\TransactionManager;
use Illuminate\Contracts\Events\Dispatcher;

/**
 * Turns an account into a business owner: the tenant, its address, its owner
 * membership and its contact number, or none of them.
 *
 * No BusinessContext. This is the use case that brings a tenant into existence,
 * so there is no current business to read from - the one it creates is the
 * answer, not the input.
 *
 * Depends only on interfaces, so it can be built with mocks and exercised
 * without a database.
 */
final class OnboardBusiness
{
    public function __construct(
        private readonly BusinessRepository $businesses,
        private readonly IndustryCatalog $industries,
        private readonly OwnerRegistrar $owners,
        private readonly PhoneBook $phones,
        private readonly SlugAllocator $slugs,
        private readonly IdGenerator $ids,
        private readonly Clock $clock,
        private readonly TransactionManager $transactions,
        private readonly Dispatcher $events,
    ) {}

    /**
     * @throws UnknownIndustry when the chosen industry is not in the catalog
     * @throws BusinessNameNotSluggable when the name yields no usable address
     * @throws InvalidBusinessTimezone when the time zone is not an IANA identifier
     * @throws BusinessNameAlreadyTaken when the name is, or has just been, taken
     * @throws BusinessSlugAlreadyTaken when a concurrent signup won the address
     * @throws OwnerAlreadyHasBusiness when the caller already owns a business
     */
    public function handle(OnboardBusinessInput $input): BusinessData
    {
        // Everything down to the transaction is a read or a pure computation.
        // They stay outside it deliberately: holding a transaction open across
        // work that writes nothing buys no atomicity and costs a connection.
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
            fn (): OnboardingOutcome => $this->register($input, $name, $slug, $timezone),
        );

        // Announced only now that the work has committed. Dispatching inside
        // the closure would deliver BusinessCreated for a row a later rollback
        // took away, and a listener cannot un-send a welcome email.
        foreach ($outcome->events as $event) {
            $this->events->dispatch($event);
        }

        return $outcome->business;
    }

    /**
     * The unit of work: a business, its owner, and optionally a phone number.
     *
     * There is no recovery when a concurrent signup wins the name or the slug,
     * by design. Onboarding is a deliberate one-shot action by a person who
     * typed the name they wanted, so adopting the winner's business would hand
     * them somebody else's company. The loser is told to pick another name.
     */
    private function register(
        OnboardBusinessInput $input,
        string $name,
        Slug $slug,
        Timezone $timezone,
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

        $ownerEvents = $this->owners->registerOwner($business->id, $input->ownerAccountId);

        if ($input->phone !== null) {
            $this->phones->attachToBusiness($business->id, $input->phone);
        }

        return new OnboardingOutcome(
            BusinessData::fromEntity($business),
            // Order is the contract: a business is announced before anything
            // that refers to it.
            [new BusinessCreated($business->id), ...$ownerEvents],
        );
    }
}

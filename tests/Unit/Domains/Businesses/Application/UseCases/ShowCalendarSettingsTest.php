<?php

declare(strict_types=1);

use App\Domains\Businesses\Application\Dtos\CalendarSettingsData;
use App\Domains\Businesses\Application\UseCases\ShowCalendarSettings;
use App\Domains\Businesses\Exceptions\BusinessNotFound;
use App\Domains\Businesses\ValueObjects\BusinessScheduleEntry;
use App\Shared\Contracts\BusinessContext;
use App\Shared\ValueObjects\CurrencyCode;
use App\Shared\ValueObjects\DomainFailureKind;
use Tests\Support\Businesses\FakeBusinessRepository;
use Tests\Support\Businesses\FakeBusinessSchedule;
use Tests\Support\Businesses\OnboardingFixtures;
use Tests\Support\Businesses\SettingsFixtures;
use Tests\Support\FakeBusinessContext;

beforeEach(function () {
    $this->businesses = new FakeBusinessRepository;
    $this->schedule = new FakeBusinessSchedule;

    $this->useCaseFor = fn (BusinessContext $context) => new ShowCalendarSettings(
        $this->businesses,
        $this->schedule,
        $context,
    );

    $this->show = fn () => ($this->useCaseFor)(new FakeBusinessContext)->handle();

    $this->mondayMorning = new BusinessScheduleEntry(weekday: 1, startsAt: '09:00', endsAt: '14:00');
    $this->mondayAfternoon = new BusinessScheduleEntry(weekday: 1, startsAt: '16:00', endsAt: '20:00');
    $this->saturday = new BusinessScheduleEntry(weekday: 6, startsAt: '10:00', endsAt: '13:30');
});

describe('the settings of the business in context', function () {
    it('answers with its timezone, currency and weekly schedule', function () {
        $this->businesses->store(OnboardingFixtures::business(
            id: FakeBusinessContext::BUSINESS_ID,
            timezone: 'America/Mexico_City',
            currency: CurrencyCode::restore('USD'),
        ));
        $this->schedule->store(FakeBusinessContext::BUSINESS_ID, $this->mondayMorning, $this->saturday);

        $response = ($this->show)();
        $data = $response->value();

        expect($response->succeeded())->toBeTrue()
            ->and($data)->toBeInstanceOf(CalendarSettingsData::class)
            ->and($data->timezone)->toBe('America/Mexico_City')
            ->and($data->currencyCode)->toBe('USD')
            ->and($data->schedule)->toBe([$this->mondayMorning, $this->saturday]);
    });

    it('answers with the default currency of a business that never chose one', function () {
        $this->businesses->store(OnboardingFixtures::business(id: FakeBusinessContext::BUSINESS_ID));

        expect(($this->show)()->value()->currencyCode)->toBe(CurrencyCode::default()->value);
    });

    it('keeps two shifts on the same weekday in the order the schedule returned them', function () {
        $this->businesses->store(OnboardingFixtures::business(id: FakeBusinessContext::BUSINESS_ID));
        $this->schedule->store(FakeBusinessContext::BUSINESS_ID, $this->mondayMorning, $this->mondayAfternoon);

        expect(($this->show)()->value()->schedule)->toBe([$this->mondayMorning, $this->mondayAfternoon]);
    });

    it('answers with an empty schedule for a business that filed no hours', function () {
        $this->businesses->store(OnboardingFixtures::business(id: FakeBusinessContext::BUSINESS_ID));

        expect(($this->show)()->value()->schedule)->toBe([]);
    });

    it('writes nothing while it reads', function () {
        $this->businesses->store(OnboardingFixtures::business(id: FakeBusinessContext::BUSINESS_ID));

        ($this->show)();

        expect($this->businesses->saved)->toBe([])
            ->and($this->businesses->deleted)->toBe([])
            ->and($this->schedule->replacements)->toBe([]);
    });
});

describe('tenant isolation', function () {
    it('takes the business from the context, because a caller names no business of their own', function () {
        expect((new ReflectionMethod(ShowCalendarSettings::class, 'handle'))->getNumberOfParameters())->toBe(0);
    });

    it('reads the business and the schedule of the business in context only', function () {
        $this->businesses->store(
            OnboardingFixtures::business(id: FakeBusinessContext::BUSINESS_ID),
            OnboardingFixtures::business(
                id: SettingsFixtures::OTHER_BUSINESS_ID,
                name: 'Peluquería Ámbar',
                slug: 'peluqueria-ambar',
                timezone: 'America/Bogota',
            ),
        );
        $this->schedule
            ->store(FakeBusinessContext::BUSINESS_ID, $this->mondayMorning)
            ->store(SettingsFixtures::OTHER_BUSINESS_ID, $this->saturday);

        $data = ($this->show)()->value();

        expect($data->timezone)->toBe(OnboardingFixtures::TIMEZONE)
            ->and($data->schedule)->toBe([$this->mondayMorning])
            ->and($this->businesses->idsRead)->toBe([FakeBusinessContext::BUSINESS_ID])
            ->and($this->schedule->reads)->toBe([FakeBusinessContext::BUSINESS_ID]);
    });

    it('answers about whichever business the context names', function () {
        $this->businesses->store(OnboardingFixtures::business(
            id: SettingsFixtures::OTHER_BUSINESS_ID,
            name: 'Peluquería Ámbar',
            slug: 'peluqueria-ambar',
            timezone: 'America/Bogota',
        ));
        $this->schedule->store(SettingsFixtures::OTHER_BUSINESS_ID, $this->saturday);

        $data = ($this->useCaseFor)(new FakeBusinessContext(SettingsFixtures::OTHER_BUSINESS_ID))->handle()->value();

        expect($data->timezone)->toBe('America/Bogota')
            ->and($data->schedule)->toBe([$this->saturday])
            ->and($this->schedule->reads)->toBe([SettingsFixtures::OTHER_BUSINESS_ID]);
    });
});

describe('a business that is not on record', function () {
    it('answers with a not found failure', function () {
        $response = ($this->show)();

        expect($response->failed())->toBeTrue()
            ->and($response->error()->code)->toBe('business_not_found')
            ->and($response->error()->kind)->toBe(DomainFailureKind::NotFound);
    });

    it('keeps the original failure on the error', function () {
        expect(($this->show)()->error()->cause())->toBeInstanceOf(BusinessNotFound::class);
    });

    it('never reads a schedule for a business it could not find', function () {
        ($this->show)();

        expect($this->schedule->reads)->toBe([]);
    });
});

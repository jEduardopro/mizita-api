<?php

declare(strict_types=1);

namespace App\Domains\Integrations;

use App\Domains\Appointments\Events\AppointmentBooked;
use App\Domains\Appointments\Events\AppointmentCancelled;
use App\Domains\Appointments\Events\AppointmentCreated;
use App\Domains\Appointments\Events\AppointmentDeleted;
use App\Domains\Appointments\Events\AppointmentRescheduled;
use App\Domains\Appointments\Events\AppointmentUpdated;
use App\Domains\Integrations\Application\UseCases\ReconcileAppointmentCalendarEvents;
use App\Domains\Integrations\Contracts\AppointmentFeed;
use App\Domains\Integrations\Contracts\AppointmentSyncQueue;
use App\Domains\Integrations\Contracts\BusinessProfiles;
use App\Domains\Integrations\Contracts\CalendarAuthorizationStates;
use App\Domains\Integrations\Contracts\CalendarAuthorizer;
use App\Domains\Integrations\Contracts\CalendarConnectionRepository;
use App\Domains\Integrations\Contracts\CalendarEventFeed;
use App\Domains\Integrations\Contracts\CalendarEventLinkRepository;
use App\Domains\Integrations\Contracts\CalendarEventPublisher;
use App\Domains\Integrations\Contracts\CalendarOwners;
use App\Domains\Integrations\Contracts\CalendarProvisioning;
use App\Domains\Integrations\Contracts\UpcomingAppointments;
use App\Domains\Integrations\Events\CalendarConnected;
use App\Domains\Integrations\Events\CalendarDisconnected;
use App\Domains\Integrations\Infrastructure\Authorization\CacheCalendarAuthorizationStates;
use App\Domains\Integrations\Infrastructure\Eloquent\EloquentCalendarConnectionRepository;
use App\Domains\Integrations\Infrastructure\Eloquent\EloquentCalendarEventLinkRepository;
use App\Domains\Integrations\Infrastructure\Gateways\AppointmentsAppointmentFeed;
use App\Domains\Integrations\Infrastructure\Gateways\AppointmentsUpcomingAppointments;
use App\Domains\Integrations\Infrastructure\Gateways\BusinessesBusinessProfiles;
use App\Domains\Integrations\Infrastructure\Gateways\StaffCalendarOwners;
use App\Domains\Integrations\Infrastructure\Google\CachedGoogleEventSource;
use App\Domains\Integrations\Infrastructure\Google\GoogleAccessTokens;
use App\Domains\Integrations\Infrastructure\Google\GoogleCalendarApi;
use App\Domains\Integrations\Infrastructure\Google\GoogleCalendarAuthorizer;
use App\Domains\Integrations\Infrastructure\Google\GoogleCalendarEventFeed;
use App\Domains\Integrations\Infrastructure\Google\GoogleCalendarEventPublisher;
use App\Domains\Integrations\Infrastructure\Google\GoogleCalendarProvisioning;
use App\Domains\Integrations\Infrastructure\Google\GoogleEventSource;
use App\Domains\Integrations\Infrastructure\Google\GoogleOAuthClient;
use App\Domains\Integrations\Infrastructure\Google\HttpGoogleEventSource;
use App\Domains\Integrations\Infrastructure\Listeners\QueueAppointmentCalendarSync;
use App\Domains\Integrations\Infrastructure\Listeners\QueueCalendarBackfill;
use App\Domains\Integrations\Infrastructure\Listeners\QueueCalendarCleanUp;
use App\Domains\Integrations\Infrastructure\Queue\QueuedAppointmentSync;
use Illuminate\Contracts\Cache\Repository as Cache;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Laravel\Socialite\SocialiteManager;

final class IntegrationsServiceProvider extends ServiceProvider
{
    private const API_MIDDLEWARE = ['api', 'auth:sanctum', 'business'];

    private const CALLBACK_MIDDLEWARE = ['web', 'auth', 'throttle:10,1'];

    private const CALENDAR_PAGE_PATH = '/calendar';

    private const WRITE_TIMEOUT_SECONDS = 10;

    private const BUSY_READ_TIMEOUT_SECONDS = 3;

    private const BUSY_CACHE_SECONDS = 120;

    private const APPOINTMENT_EVENTS = [
        AppointmentCreated::class,
        AppointmentBooked::class,
        AppointmentUpdated::class,
        AppointmentRescheduled::class,
        AppointmentCancelled::class,
        AppointmentDeleted::class,
    ];

    public function register(): void
    {
        $this->app->bind(CalendarConnectionRepository::class, EloquentCalendarConnectionRepository::class);
        $this->app->bind(CalendarEventLinkRepository::class, EloquentCalendarEventLinkRepository::class);
        $this->app->bind(CalendarAuthorizationStates::class, CacheCalendarAuthorizationStates::class);
        $this->app->bind(CalendarAuthorizer::class, GoogleCalendarAuthorizer::class);
        $this->app->bind(CalendarProvisioning::class, GoogleCalendarProvisioning::class);
        $this->app->bind(CalendarEventPublisher::class, GoogleCalendarEventPublisher::class);
        $this->app->bind(CalendarEventFeed::class, GoogleCalendarEventFeed::class);
        $this->app->bind(AppointmentFeed::class, AppointmentsAppointmentFeed::class);
        $this->app->bind(UpcomingAppointments::class, AppointmentsUpcomingAppointments::class);
        $this->app->bind(AppointmentSyncQueue::class, QueuedAppointmentSync::class);
        $this->app->bind(CalendarOwners::class, StaffCalendarOwners::class);
        $this->app->bind(BusinessProfiles::class, BusinessesBusinessProfiles::class);

        $this->registerGoogleClients();

        $this->app->when(ReconcileAppointmentCalendarEvents::class)
            ->needs('$mizitaLink')
            ->give(static fn (): string => rtrim((string) config('app.url'), '/').self::CALENDAR_PAGE_PATH);
    }

    public function boot(): void
    {
        Event::listen(self::APPOINTMENT_EVENTS, QueueAppointmentCalendarSync::class);
        Event::listen(CalendarConnected::class, QueueCalendarBackfill::class);
        Event::listen(CalendarDisconnected::class, QueueCalendarCleanUp::class);

        Route::middleware(self::CALLBACK_MIDDLEWARE)
            ->group(__DIR__.'/Infrastructure/Http/web.php');

        Route::prefix('api')
            ->middleware(self::API_MIDDLEWARE)
            ->group(__DIR__.'/Infrastructure/Http/routes.php');
    }

    private function registerGoogleClients(): void
    {
        $this->app->bind(GoogleOAuthClient::class, static fn (Application $app): GoogleOAuthClient => new GoogleOAuthClient(
            socialite: $app->make(SocialiteManager::class),
            clientId: (string) config('services.google_calendar.client_id'),
            clientSecret: (string) config('services.google_calendar.client_secret'),
            redirectUri: (string) config('services.google_calendar.redirect'),
            timeoutSeconds: self::WRITE_TIMEOUT_SECONDS,
        ));

        $this->app->bind(GoogleCalendarApi::class, static fn (Application $app): GoogleCalendarApi => new GoogleCalendarApi(
            $app->make(GoogleAccessTokens::class),
            self::WRITE_TIMEOUT_SECONDS,
        ));

        $this->app->when(GoogleCalendarProvisioning::class)
            ->needs('$timeoutSeconds')
            ->give(self::WRITE_TIMEOUT_SECONDS);

        $this->app->when(GoogleCalendarEventFeed::class)
            ->needs(GoogleEventSource::class)
            ->give(static fn (Application $app): CachedGoogleEventSource => new CachedGoogleEventSource(
                new HttpGoogleEventSource(new GoogleCalendarApi(
                    $app->make(GoogleAccessTokens::class),
                    self::BUSY_READ_TIMEOUT_SECONDS,
                )),
                $app->make(Cache::class),
                self::BUSY_CACHE_SECONDS,
            ));
    }
}

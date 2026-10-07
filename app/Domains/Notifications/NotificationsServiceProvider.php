<?php

declare(strict_types=1);

namespace App\Domains\Notifications;

use App\Domains\Appointments\Events\AppointmentBooked;
use App\Domains\Availability\Events\StaffScheduleChanged;
use App\Domains\Notifications\Contracts\BookedAppointments;
use App\Domains\Notifications\Contracts\BusinessOwners;
use App\Domains\Notifications\Contracts\NotificationFeed;
use App\Domains\Notifications\Contracts\NotificationReaders;
use App\Domains\Notifications\Contracts\NotifiedStaffMembers;
use App\Domains\Notifications\Contracts\StaffNotificationRepository;
use App\Domains\Notifications\Infrastructure\Eloquent\EloquentNotificationFeed;
use App\Domains\Notifications\Infrastructure\Eloquent\EloquentStaffNotificationRepository;
use App\Domains\Notifications\Infrastructure\Gateways\AppointmentsBookedAppointments;
use App\Domains\Notifications\Infrastructure\Gateways\StaffBusinessOwners;
use App\Domains\Notifications\Infrastructure\Gateways\StaffNotificationReaders;
use App\Domains\Notifications\Infrastructure\Gateways\StaffNotifiedStaffMembers;
use App\Domains\Notifications\Infrastructure\Listeners\NotifyOwnerOfScheduleChange;
use App\Domains\Notifications\Infrastructure\Listeners\NotifyStaffOfPublicBooking;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

final class NotificationsServiceProvider extends ServiceProvider
{
    private const API_MIDDLEWARE = ['api', 'auth:sanctum', 'business'];

    public function register(): void
    {
        $this->app->bind(StaffNotificationRepository::class, EloquentStaffNotificationRepository::class);
        $this->app->bind(NotificationFeed::class, EloquentNotificationFeed::class);
        $this->app->bind(BookedAppointments::class, AppointmentsBookedAppointments::class);
        $this->app->bind(NotificationReaders::class, StaffNotificationReaders::class);
        $this->app->bind(BusinessOwners::class, StaffBusinessOwners::class);
        $this->app->bind(NotifiedStaffMembers::class, StaffNotifiedStaffMembers::class);
    }

    public function boot(): void
    {
        Event::listen(AppointmentBooked::class, NotifyStaffOfPublicBooking::class);
        Event::listen(StaffScheduleChanged::class, NotifyOwnerOfScheduleChange::class);

        Route::prefix('api')
            ->middleware(self::API_MIDDLEWARE)
            ->group(__DIR__.'/Infrastructure/Http/routes.php');
    }
}

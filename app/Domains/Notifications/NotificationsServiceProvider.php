<?php

declare(strict_types=1);

namespace App\Domains\Notifications;

use App\Domains\Appointments\Events\AppointmentBooked;
use App\Domains\Notifications\Contracts\BookedAppointments;
use App\Domains\Notifications\Contracts\NotificationFeed;
use App\Domains\Notifications\Contracts\NotificationReaders;
use App\Domains\Notifications\Contracts\StaffNotificationRepository;
use App\Domains\Notifications\Infrastructure\Eloquent\EloquentNotificationFeed;
use App\Domains\Notifications\Infrastructure\Eloquent\EloquentStaffNotificationRepository;
use App\Domains\Notifications\Infrastructure\Gateways\AppointmentsBookedAppointments;
use App\Domains\Notifications\Infrastructure\Gateways\StaffNotificationReaders;
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
    }

    public function boot(): void
    {
        Event::listen(AppointmentBooked::class, NotifyStaffOfPublicBooking::class);

        Route::prefix('api')
            ->middleware(self::API_MIDDLEWARE)
            ->group(__DIR__.'/Infrastructure/Http/routes.php');
    }
}

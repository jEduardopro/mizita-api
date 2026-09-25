<?php

declare(strict_types=1);

namespace App\Domains\Staff;

use App\Domains\Staff\Application\Listeners\NotifyInvitedTeamMember;
use App\Domains\Staff\Contracts\AccountDirectory;
use App\Domains\Staff\Contracts\BusinessDirectory;
use App\Domains\Staff\Contracts\StaffMemberRepository;
use App\Domains\Staff\Contracts\StaffPhoneBook;
use App\Domains\Staff\Contracts\StaffProfilePhotos;
use App\Domains\Staff\Contracts\StaffProfileRepository;
use App\Domains\Staff\Contracts\TeamAccountProvisioner;
use App\Domains\Staff\Contracts\TeamInvitationMailer;
use App\Domains\Staff\Contracts\TeamRoster;
use App\Domains\Staff\Contracts\TeamTemporaryPasswords;
use App\Domains\Staff\Contracts\UpcomingAppointments;
use App\Domains\Staff\Events\TeamMemberInvited;
use App\Domains\Staff\Infrastructure\Eloquent\EloquentStaffMemberRepository;
use App\Domains\Staff\Infrastructure\Eloquent\EloquentStaffProfileRepository;
use App\Domains\Staff\Infrastructure\Eloquent\Models\StaffMemberModel;
use App\Domains\Staff\Infrastructure\Eloquent\Models\StaffProfileModel;
use App\Domains\Staff\Infrastructure\Gateways\AccountsAccountDirectory;
use App\Domains\Staff\Infrastructure\Gateways\AccountsTeamAccountProvisioner;
use App\Domains\Staff\Infrastructure\Gateways\AccountsTeamTemporaryPasswords;
use App\Domains\Staff\Infrastructure\Gateways\AppointmentsUpcomingAppointments;
use App\Domains\Staff\Infrastructure\Gateways\BusinessesBusinessDirectory;
use App\Domains\Staff\Infrastructure\Gateways\EloquentBusinessMembership;
use App\Domains\Staff\Infrastructure\Gateways\PhonesStaffPhoneBook;
use App\Domains\Staff\Infrastructure\Media\SpatieStaffProfilePhotos;
use App\Domains\Staff\Infrastructure\Notifications\NotificationTeamInvitationMailer;
use App\Domains\Staff\Infrastructure\Permissions\StaffBusinessAuthorization;
use App\Shared\Contracts\BusinessAuthorization;
use App\Shared\Contracts\BusinessMembership;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

final class StaffServiceProvider extends ServiceProvider
{
    public const INVITATION_LIMITER = 'staff-team-invitations';

    public const TEMPORARY_PASSWORD_LIMITER = 'staff-temporary-password-reveals';

    private const PHOTO_UPLOAD_LIMITER = 'staff-profile-photo-uploads';

    private const UPLOADS_PER_MINUTE = 10;

    private const UPLOADS_PER_HOUR = 60;

    private const INVITATIONS_PER_MINUTE = 10;

    private const INVITATIONS_PER_HOUR = 60;

    private const TEMPORARY_PASSWORD_REVEALS_PER_MINUTE = 10;

    private const TEMPORARY_PASSWORD_REVEALS_PER_HOUR = 60;

    public function register(): void
    {
        $this->app->bind(StaffMemberRepository::class, EloquentStaffMemberRepository::class);
        $this->app->bind(TeamRoster::class, EloquentStaffMemberRepository::class);
        $this->app->bind(StaffProfileRepository::class, EloquentStaffProfileRepository::class);
        $this->app->bind(StaffProfilePhotos::class, SpatieStaffProfilePhotos::class);
        $this->app->bind(StaffPhoneBook::class, PhonesStaffPhoneBook::class);
        $this->app->bind(BusinessMembership::class, EloquentBusinessMembership::class);
        $this->app->bind(AccountDirectory::class, AccountsAccountDirectory::class);
        $this->app->bind(BusinessAuthorization::class, StaffBusinessAuthorization::class);
        $this->app->bind(TeamAccountProvisioner::class, AccountsTeamAccountProvisioner::class);
        $this->app->bind(UpcomingAppointments::class, AppointmentsUpcomingAppointments::class);
        $this->app->bind(BusinessDirectory::class, BusinessesBusinessDirectory::class);
        $this->app->bind(TeamInvitationMailer::class, NotificationTeamInvitationMailer::class);
        $this->app->bind(TeamTemporaryPasswords::class, AccountsTeamTemporaryPasswords::class);
    }

    public function boot(): void
    {
        Relation::enforceMorphMap([
            'staff_member' => StaffMemberModel::class,
            'staff_profile' => StaffProfileModel::class,
        ]);

        Event::listen(TeamMemberInvited::class, NotifyInvitedTeamMember::class);

        self::registerPerAccountLimiter(self::PHOTO_UPLOAD_LIMITER, self::UPLOADS_PER_MINUTE, self::UPLOADS_PER_HOUR);
        self::registerPerAccountLimiter(self::INVITATION_LIMITER, self::INVITATIONS_PER_MINUTE, self::INVITATIONS_PER_HOUR);
        self::registerPerAccountLimiter(
            self::TEMPORARY_PASSWORD_LIMITER,
            self::TEMPORARY_PASSWORD_REVEALS_PER_MINUTE,
            self::TEMPORARY_PASSWORD_REVEALS_PER_HOUR,
        );

        Route::prefix('api')
            ->middleware(['api', 'auth:sanctum', 'business'])
            ->group(__DIR__.'/Infrastructure/Http/routes.php');

        Route::prefix('api')
            ->middleware(['api', 'auth:sanctum', 'business', 'throttle:'.self::PHOTO_UPLOAD_LIMITER])
            ->group(__DIR__.'/Infrastructure/Http/media.php');
    }

    private static function registerPerAccountLimiter(string $name, int $perMinute, int $perHour): void
    {
        RateLimiter::for($name, static fn (Request $request): array => [
            Limit::perMinute($perMinute)->by('minute:'.self::limiterKeyFor($request)),
            Limit::perHour($perHour)->by('hour:'.self::limiterKeyFor($request)),
        ]);
    }

    private static function limiterKeyFor(Request $request): string
    {
        $account = $request->user()?->getAuthIdentifier();

        return $account === null ? 'ip:'.(string) $request->ip() : 'account:'.(string) $account;
    }
}

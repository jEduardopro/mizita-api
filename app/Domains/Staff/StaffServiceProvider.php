<?php

declare(strict_types=1);

namespace App\Domains\Staff;

use App\Domains\Staff\Contracts\AccountDirectory;
use App\Domains\Staff\Contracts\StaffMemberRepository;
use App\Domains\Staff\Contracts\StaffPhoneBook;
use App\Domains\Staff\Contracts\StaffProfilePhotos;
use App\Domains\Staff\Contracts\StaffProfileRepository;
use App\Domains\Staff\Infrastructure\Eloquent\EloquentStaffMemberRepository;
use App\Domains\Staff\Infrastructure\Eloquent\EloquentStaffProfileRepository;
use App\Domains\Staff\Infrastructure\Eloquent\Models\StaffMemberModel;
use App\Domains\Staff\Infrastructure\Eloquent\Models\StaffProfileModel;
use App\Domains\Staff\Infrastructure\Gateways\AccountsAccountDirectory;
use App\Domains\Staff\Infrastructure\Gateways\EloquentBusinessMembership;
use App\Domains\Staff\Infrastructure\Gateways\PhonesStaffPhoneBook;
use App\Domains\Staff\Infrastructure\Media\SpatieStaffProfilePhotos;
use App\Domains\Staff\Infrastructure\Permissions\StaffBusinessAuthorization;
use App\Shared\Contracts\BusinessAuthorization;
use App\Shared\Contracts\BusinessMembership;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

final class StaffServiceProvider extends ServiceProvider
{
    private const PHOTO_UPLOAD_LIMITER = 'staff-profile-photo-uploads';

    private const UPLOADS_PER_MINUTE = 10;

    private const UPLOADS_PER_HOUR = 60;

    public function register(): void
    {
        $this->app->bind(StaffMemberRepository::class, EloquentStaffMemberRepository::class);
        $this->app->bind(StaffProfileRepository::class, EloquentStaffProfileRepository::class);
        $this->app->bind(StaffProfilePhotos::class, SpatieStaffProfilePhotos::class);
        $this->app->bind(StaffPhoneBook::class, PhonesStaffPhoneBook::class);
        $this->app->bind(BusinessMembership::class, EloquentBusinessMembership::class);
        $this->app->bind(AccountDirectory::class, AccountsAccountDirectory::class);
        $this->app->bind(BusinessAuthorization::class, StaffBusinessAuthorization::class);
    }

    public function boot(): void
    {
        Relation::enforceMorphMap([
            'staff_member' => StaffMemberModel::class,
            'staff_profile' => StaffProfileModel::class,
        ]);

        $this->registerPhotoUploadLimiter();

        Route::prefix('api')
            ->middleware(['api', 'auth:sanctum', 'business'])
            ->group(__DIR__.'/Infrastructure/Http/routes.php');

        Route::prefix('api')
            ->middleware(['api', 'auth:sanctum', 'business', 'throttle:'.self::PHOTO_UPLOAD_LIMITER])
            ->group(__DIR__.'/Infrastructure/Http/media.php');
    }

    private function registerPhotoUploadLimiter(): void
    {
        RateLimiter::for(self::PHOTO_UPLOAD_LIMITER, static fn (Request $request): array => [
            Limit::perMinute(self::UPLOADS_PER_MINUTE)->by('minute:'.self::limiterKeyFor($request)),
            Limit::perHour(self::UPLOADS_PER_HOUR)->by('hour:'.self::limiterKeyFor($request)),
        ]);
    }

    private static function limiterKeyFor(Request $request): string
    {
        $account = $request->user()?->getAuthIdentifier();

        return $account === null ? 'ip:'.(string) $request->ip() : 'account:'.(string) $account;
    }
}

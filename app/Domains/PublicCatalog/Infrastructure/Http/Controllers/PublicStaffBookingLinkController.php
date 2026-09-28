<?php

declare(strict_types=1);

namespace App\Domains\PublicCatalog\Infrastructure\Http\Controllers;

use App\Domains\PublicCatalog\Application\Dtos\ResolveStaffBookingLinkInput;
use App\Domains\PublicCatalog\Application\Dtos\StaffBookingLinkTarget;
use App\Domains\PublicCatalog\Application\UseCases\ResolveStaffBookingLink;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Symfony\Component\HttpFoundation\Response;

final class PublicStaffBookingLinkController extends Controller
{
    public const STAFF_LINK_ROUTE = 'booking-page.staff';

    public const STAFF_SERVICE_LINK_ROUTE = 'booking-page.staff-service';

    private const SERVICE_STEP_ROUTE = 'booking-flow.service';

    private const TIME_STEP_ROUTE = 'booking-flow.time';

    private const STAFF_QUERY_KEY = 'with';

    private const SERVICE_QUERY_KEY = 'service';

    public function staff(
        string $slug,
        string $staffSlug,
        ResolveStaffBookingLink $resolveStaffBookingLink,
    ): RedirectResponse {
        $target = self::targetOf($resolveStaffBookingLink, new ResolveStaffBookingLinkInput(
            businessSlug: $slug,
            staffSlug: $staffSlug,
            serviceSlug: null,
        ));

        return redirect()->route(self::SERVICE_STEP_ROUTE, [
            'slug' => $target->businessSlug,
            self::STAFF_QUERY_KEY => $target->staffMemberId,
        ]);
    }

    public function staffService(
        string $slug,
        string $staffSlug,
        string $serviceSlug,
        ResolveStaffBookingLink $resolveStaffBookingLink,
    ): RedirectResponse {
        $target = self::targetOf($resolveStaffBookingLink, new ResolveStaffBookingLinkInput(
            businessSlug: $slug,
            staffSlug: $staffSlug,
            serviceSlug: $serviceSlug,
        ));

        if ($target->serviceId === null) {
            return redirect()->route(self::STAFF_LINK_ROUTE, [
                'slug' => $target->businessSlug,
                'staffSlug' => $staffSlug,
            ]);
        }

        return redirect()->route(self::TIME_STEP_ROUTE, [
            'slug' => $target->businessSlug,
            self::STAFF_QUERY_KEY => $target->staffMemberId,
            self::SERVICE_QUERY_KEY => $target->serviceId,
        ]);
    }

    private static function targetOf(
        ResolveStaffBookingLink $resolveStaffBookingLink,
        ResolveStaffBookingLinkInput $input,
    ): StaffBookingLinkTarget {
        $response = $resolveStaffBookingLink->handle($input);

        abort_if($response->failed(), Response::HTTP_NOT_FOUND);

        return $response->value();
    }
}

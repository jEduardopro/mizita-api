<?php

declare(strict_types=1);

namespace App\Domains\PublicCatalog\Infrastructure\Http\Controllers;

use App\Domains\PublicCatalog\Application\Dtos\ResolveServiceBookingLinkInput;
use App\Domains\PublicCatalog\Application\UseCases\ResolveServiceBookingLink;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Symfony\Component\HttpFoundation\Response;

final class PublicServiceBookingLinkController extends Controller
{
    public const SERVICE_LINK_ROUTE = 'booking-page.service';

    private const BUSINESS_PAGE_ROUTE = 'booking-page';

    private const STAFF_STEP_ROUTE = 'booking-flow.staff';

    private const SERVICE_QUERY_KEY = 'service';

    public function service(
        string $slug,
        string $serviceSlug,
        ResolveServiceBookingLink $resolveServiceBookingLink,
    ): RedirectResponse {
        $response = $resolveServiceBookingLink->handle(new ResolveServiceBookingLinkInput(
            businessSlug: $slug,
            serviceSlug: $serviceSlug,
        ));

        abort_if($response->failed(), Response::HTTP_NOT_FOUND);

        $target = $response->value();

        if ($target->serviceId === null) {
            return redirect()->route(self::BUSINESS_PAGE_ROUTE, ['slug' => $target->businessSlug]);
        }

        return redirect()->route(self::STAFF_STEP_ROUTE, [
            'slug' => $target->businessSlug,
            self::SERVICE_QUERY_KEY => $target->serviceId,
        ]);
    }
}

<?php

declare(strict_types=1);

namespace App\Domains\PublicCatalog\Infrastructure\Http\Controllers;

use App\Domains\PublicCatalog\Application\Dtos\BookPublicAppointmentInput;
use App\Domains\PublicCatalog\Application\Dtos\CancelPublicBookingInput;
use App\Domains\PublicCatalog\Application\Dtos\ReschedulePublicBookingInput;
use App\Domains\PublicCatalog\Application\Dtos\ShowPublicBookingInput;
use App\Domains\PublicCatalog\Application\UseCases\BookPublicAppointment;
use App\Domains\PublicCatalog\Application\UseCases\CancelPublicBooking;
use App\Domains\PublicCatalog\Application\UseCases\ReschedulePublicBooking;
use App\Domains\PublicCatalog\Application\UseCases\ShowPublicBooking;
use App\Domains\PublicCatalog\Infrastructure\Http\Requests\BookPublicAppointmentRequest;
use App\Domains\PublicCatalog\Infrastructure\Http\Requests\ReschedulePublicBookingRequest;
use App\Domains\PublicCatalog\Infrastructure\Http\Resources\PublicBookingConfirmationResource;
use App\Domains\PublicCatalog\Infrastructure\Http\Resources\PublicBookingResource;
use App\Domains\PublicCatalog\ValueObjects\PublicBookingCredentials;
use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponder;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

final class PublicBookingController extends Controller
{
    private const MANAGE_TOKEN_HEADER = 'X-Manage-Token';

    public function store(
        BookPublicAppointmentRequest $request,
        string $slug,
        BookPublicAppointment $bookPublicAppointment,
        ApiResponder $responder,
    ): Response {
        try {
            $response = $bookPublicAppointment->handle(
                BookPublicAppointmentInput::fromRequest($request->validated(), $slug),
            );

            if ($response->failed()) {
                return $responder->failure($response->error(), $response->warnings());
            }

            return $responder->success(
                $response,
                PublicBookingConfirmationResource::make($response->value()),
                Response::HTTP_CREATED,
            );
        } catch (Throwable $unexpected) {
            return $responder->unexpected($request, $unexpected);
        }
    }

    public function show(
        Request $request,
        string $slug,
        string $reference,
        ShowPublicBooking $showPublicBooking,
        ApiResponder $responder,
    ): Response {
        try {
            $response = $showPublicBooking->handle(new ShowPublicBookingInput(
                slug: $slug,
                credentials: self::credentialsFrom($request, $reference),
            ));

            if ($response->failed()) {
                return $responder->failure($response->error(), $response->warnings());
            }

            return $responder->success(
                $response,
                PublicBookingResource::make($response->value()),
                Response::HTTP_OK,
            );
        } catch (Throwable $unexpected) {
            return $responder->unexpected($request, $unexpected);
        }
    }

    public function update(
        ReschedulePublicBookingRequest $request,
        string $slug,
        string $reference,
        ReschedulePublicBooking $reschedulePublicBooking,
        ApiResponder $responder,
    ): Response {
        try {
            $response = $reschedulePublicBooking->handle(ReschedulePublicBookingInput::fromRequest(
                $request->validated(),
                $slug,
                self::credentialsFrom($request, $reference),
            ));

            if ($response->failed()) {
                return $responder->failure($response->error(), $response->warnings());
            }

            return $responder->success(
                $response,
                PublicBookingResource::make($response->value()),
                Response::HTTP_OK,
            );
        } catch (Throwable $unexpected) {
            return $responder->unexpected($request, $unexpected);
        }
    }

    public function destroy(
        Request $request,
        string $slug,
        string $reference,
        CancelPublicBooking $cancelPublicBooking,
        ApiResponder $responder,
    ): Response {
        try {
            $response = $cancelPublicBooking->handle(new CancelPublicBookingInput(
                slug: $slug,
                credentials: self::credentialsFrom($request, $reference),
            ));

            if ($response->failed()) {
                return $responder->failure($response->error(), $response->warnings());
            }

            return $responder->success(
                $response,
                PublicBookingResource::make($response->value()),
                Response::HTTP_OK,
            );
        } catch (Throwable $unexpected) {
            return $responder->unexpected($request, $unexpected);
        }
    }

    private static function credentialsFrom(Request $request, string $reference): PublicBookingCredentials
    {
        return new PublicBookingCredentials(
            referenceCode: $reference,
            manageToken: (string) $request->header(self::MANAGE_TOKEN_HEADER),
        );
    }
}

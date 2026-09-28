<?php

declare(strict_types=1);

namespace App\Domains\Staff\Infrastructure\Http\Controllers;

use App\Domains\Staff\Application\Dtos\ChangeStaffBookingSlugInput;
use App\Domains\Staff\Application\Dtos\GenerateStaffBookingLinkInput;
use App\Domains\Staff\Application\UseCases\ChangeStaffBookingSlug;
use App\Domains\Staff\Application\UseCases\GenerateStaffBookingLink;
use App\Domains\Staff\Infrastructure\Http\Requests\ChangeStaffBookingSlugRequest;
use App\Domains\Staff\Infrastructure\Http\Resources\BookingLinkResource;
use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponder;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

final class StaffBookingLinkController extends Controller
{
    public function store(
        Request $request,
        string $staffMember,
        GenerateStaffBookingLink $generateStaffBookingLink,
        ApiResponder $responder,
    ): Response {
        try {
            $response = $generateStaffBookingLink->handle(new GenerateStaffBookingLinkInput($staffMember));

            if ($response->failed()) {
                return $responder->failure($response->error(), $response->warnings());
            }

            return $responder->success(
                $response,
                BookingLinkResource::make($response->value()),
                Response::HTTP_CREATED,
            );
        } catch (Throwable $unexpected) {
            return $responder->unexpected($request, $unexpected);
        }
    }

    public function update(
        ChangeStaffBookingSlugRequest $request,
        string $staffMember,
        ChangeStaffBookingSlug $changeStaffBookingSlug,
        ApiResponder $responder,
    ): Response {
        try {
            $response = $changeStaffBookingSlug->handle(
                ChangeStaffBookingSlugInput::fromRequest($request->validated(), $staffMember),
            );

            if ($response->failed()) {
                return $responder->failure($response->error(), $response->warnings());
            }

            return $responder->success(
                $response,
                BookingLinkResource::make($response->value()),
                Response::HTTP_OK,
            );
        } catch (Throwable $unexpected) {
            return $responder->unexpected($request, $unexpected);
        }
    }
}

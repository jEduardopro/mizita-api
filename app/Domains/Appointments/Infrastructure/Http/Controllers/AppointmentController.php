<?php

declare(strict_types=1);

namespace App\Domains\Appointments\Infrastructure\Http\Controllers;

use App\Domains\Appointments\Application\Dtos\CancelAppointmentInput;
use App\Domains\Appointments\Application\Dtos\CreateAppointmentInput;
use App\Domains\Appointments\Application\Dtos\DeleteAppointmentInput;
use App\Domains\Appointments\Application\Dtos\ListAppointmentsInput;
use App\Domains\Appointments\Application\Dtos\ShowAppointmentInput;
use App\Domains\Appointments\Application\Dtos\UpdateAppointmentInput;
use App\Domains\Appointments\Application\UseCases\CancelAppointment;
use App\Domains\Appointments\Application\UseCases\CreateAppointment;
use App\Domains\Appointments\Application\UseCases\DeleteAppointment;
use App\Domains\Appointments\Application\UseCases\ListAppointments;
use App\Domains\Appointments\Application\UseCases\ShowAppointment;
use App\Domains\Appointments\Application\UseCases\UpdateAppointment;
use App\Domains\Appointments\Infrastructure\Http\Requests\CreateAppointmentRequest;
use App\Domains\Appointments\Infrastructure\Http\Requests\ListAppointmentsRequest;
use App\Domains\Appointments\Infrastructure\Http\Requests\UpdateAppointmentRequest;
use App\Domains\Appointments\Infrastructure\Http\Resources\AppointmentResource;
use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponder;
use App\Models\User;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

final class AppointmentController extends Controller
{
    public function index(
        ListAppointmentsRequest $request,
        ListAppointments $listAppointments,
        ApiResponder $responder,
    ): Response {
        /** @var User $account */
        $account = $request->user();

        try {
            $response = $listAppointments->handle(
                ListAppointmentsInput::fromRequest($request->validated(), $account->uuid),
            );

            if ($response->failed()) {
                return $responder->failure($response->error(), $response->warnings());
            }

            return $responder->success(
                $response,
                AppointmentResource::collection($response->value()),
                Response::HTTP_OK,
            );
        } catch (Throwable $unexpected) {
            return $responder->unexpected($request, $unexpected);
        }
    }

    public function store(
        CreateAppointmentRequest $request,
        CreateAppointment $createAppointment,
        ApiResponder $responder,
    ): Response {
        /** @var User $account */
        $account = $request->user();

        try {
            $response = $createAppointment->handle(
                CreateAppointmentInput::fromRequest($request->validated(), $account->uuid),
            );

            if ($response->failed()) {
                return $responder->failure($response->error(), $response->warnings());
            }

            return $responder->success(
                $response,
                AppointmentResource::make($response->value()),
                Response::HTTP_CREATED,
            );
        } catch (Throwable $unexpected) {
            return $responder->unexpected($request, $unexpected);
        }
    }

    public function show(
        Request $request,
        string $appointment,
        ShowAppointment $showAppointment,
        ApiResponder $responder,
    ): Response {
        /** @var User $account */
        $account = $request->user();

        try {
            $response = $showAppointment->handle(new ShowAppointmentInput($appointment, $account->uuid));

            if ($response->failed()) {
                return $responder->failure($response->error(), $response->warnings());
            }

            return $responder->success(
                $response,
                AppointmentResource::make($response->value()),
                Response::HTTP_OK,
            );
        } catch (Throwable $unexpected) {
            return $responder->unexpected($request, $unexpected);
        }
    }

    public function update(
        UpdateAppointmentRequest $request,
        string $appointment,
        UpdateAppointment $updateAppointment,
        ApiResponder $responder,
    ): Response {
        /** @var User $account */
        $account = $request->user();

        try {
            $response = $updateAppointment->handle(
                UpdateAppointmentInput::fromRequest($request->validated(), $appointment, $account->uuid),
            );

            if ($response->failed()) {
                return $responder->failure($response->error(), $response->warnings());
            }

            return $responder->success(
                $response,
                AppointmentResource::make($response->value()),
                Response::HTTP_OK,
            );
        } catch (Throwable $unexpected) {
            return $responder->unexpected($request, $unexpected);
        }
    }

    public function cancel(
        Request $request,
        string $appointment,
        CancelAppointment $cancelAppointment,
        ApiResponder $responder,
    ): Response {
        /** @var User $account */
        $account = $request->user();

        try {
            $response = $cancelAppointment->handle(new CancelAppointmentInput($appointment, $account->uuid));

            if ($response->failed()) {
                return $responder->failure($response->error(), $response->warnings());
            }

            return $responder->success(
                $response,
                AppointmentResource::make($response->value()),
                Response::HTTP_OK,
            );
        } catch (Throwable $unexpected) {
            return $responder->unexpected($request, $unexpected);
        }
    }

    public function destroy(
        Request $request,
        string $appointment,
        DeleteAppointment $deleteAppointment,
        ApiResponder $responder,
    ): Response {
        /** @var User $account */
        $account = $request->user();

        try {
            $response = $deleteAppointment->handle(new DeleteAppointmentInput($appointment, $account->uuid));

            if ($response->failed()) {
                return $responder->failure($response->error(), $response->warnings());
            }

            return response()->noContent();
        } catch (Throwable $unexpected) {
            return $responder->unexpected($request, $unexpected);
        }
    }
}

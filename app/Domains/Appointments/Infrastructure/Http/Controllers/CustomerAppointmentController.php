<?php

declare(strict_types=1);

namespace App\Domains\Appointments\Infrastructure\Http\Controllers;

use App\Domains\Appointments\Application\Dtos\ListCustomerAppointmentsInput;
use App\Domains\Appointments\Application\Dtos\ShowCustomerLastAppointmentInput;
use App\Domains\Appointments\Application\UseCases\ListCustomerAppointments;
use App\Domains\Appointments\Application\UseCases\ShowCustomerLastAppointment;
use App\Domains\Appointments\Infrastructure\Http\Requests\ListCustomerAppointmentsRequest;
use App\Domains\Appointments\Infrastructure\Http\Resources\AppointmentResource;
use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponder;
use App\Http\Responses\NullResource;
use App\Http\Responses\PaginatedCollection;
use App\Models\User;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

final class CustomerAppointmentController extends Controller
{
    public function index(
        ListCustomerAppointmentsRequest $request,
        string $customer,
        ListCustomerAppointments $listCustomerAppointments,
        ApiResponder $responder,
    ): Response {
        /** @var User $account */
        $account = $request->user();

        try {
            $response = $listCustomerAppointments->handle(
                ListCustomerAppointmentsInput::fromRequest($request->validated(), $customer, $account->uuid),
            );

            if ($response->failed()) {
                return $responder->failure($response->error(), $response->warnings());
            }

            return $responder->success(
                $response,
                PaginatedCollection::of($response->value(), AppointmentResource::class),
                Response::HTTP_OK,
            );
        } catch (Throwable $unexpected) {
            return $responder->unexpected($request, $unexpected);
        }
    }

    public function last(
        Request $request,
        string $customer,
        ShowCustomerLastAppointment $showCustomerLastAppointment,
        ApiResponder $responder,
    ): Response {
        /** @var User $account */
        $account = $request->user();

        try {
            $response = $showCustomerLastAppointment->handle(
                new ShowCustomerLastAppointmentInput($customer, $account->uuid),
            );

            if ($response->failed()) {
                return $responder->failure($response->error(), $response->warnings());
            }

            $appointment = $response->value();

            return $responder->success(
                $response,
                $appointment === null ? NullResource::instance() : AppointmentResource::make($appointment),
                Response::HTTP_OK,
            );
        } catch (Throwable $unexpected) {
            return $responder->unexpected($request, $unexpected);
        }
    }
}

<?php

declare(strict_types=1);

namespace App\Domains\Services\Infrastructure\Http\Controllers;

use App\Domains\Services\Application\Dtos\AssignStaffToServiceInput;
use App\Domains\Services\Application\Dtos\ListServicesForStaffInput;
use App\Domains\Services\Application\Dtos\UnassignStaffFromServiceInput;
use App\Domains\Services\Application\UseCases\AssignStaffToService;
use App\Domains\Services\Application\UseCases\ListServicesForStaff;
use App\Domains\Services\Application\UseCases\UnassignStaffFromService;
use App\Domains\Services\Infrastructure\Http\Resources\ServiceResource;
use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponder;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

final class ServiceStaffController extends Controller
{
    public function index(
        Request $request,
        string $staffMember,
        ListServicesForStaff $listServicesForStaff,
        ApiResponder $responder,
    ): Response {
        try {
            $response = $listServicesForStaff->handle(new ListServicesForStaffInput($staffMember));

            if ($response->failed()) {
                return $responder->failure($response->error(), $response->warnings());
            }

            return $responder->success(
                $response,
                ServiceResource::collection($response->value()),
                Response::HTTP_OK,
            );
        } catch (Throwable $unexpected) {
            return $responder->unexpected($request, $unexpected);
        }
    }

    public function update(
        Request $request,
        string $service,
        string $staffMember,
        AssignStaffToService $assignStaffToService,
        ApiResponder $responder,
    ): Response {
        try {
            $response = $assignStaffToService->handle(new AssignStaffToServiceInput($service, $staffMember));

            if ($response->failed()) {
                return $responder->failure($response->error(), $response->warnings());
            }

            return $responder->success(
                $response,
                ServiceResource::make($response->value()),
                Response::HTTP_OK,
            );
        } catch (Throwable $unexpected) {
            return $responder->unexpected($request, $unexpected);
        }
    }

    public function destroy(
        Request $request,
        string $service,
        string $staffMember,
        UnassignStaffFromService $unassignStaffFromService,
        ApiResponder $responder,
    ): Response {
        try {
            $response = $unassignStaffFromService->handle(new UnassignStaffFromServiceInput($service, $staffMember));

            if ($response->failed()) {
                return $responder->failure($response->error(), $response->warnings());
            }

            return $responder->success(
                $response,
                ServiceResource::make($response->value()),
                Response::HTTP_OK,
            );
        } catch (Throwable $unexpected) {
            return $responder->unexpected($request, $unexpected);
        }
    }
}

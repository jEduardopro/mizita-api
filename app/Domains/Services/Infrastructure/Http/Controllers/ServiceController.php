<?php

declare(strict_types=1);

namespace App\Domains\Services\Infrastructure\Http\Controllers;

use App\Domains\Services\Application\Dtos\CreateServiceInput;
use App\Domains\Services\Application\Dtos\DeleteServiceInput;
use App\Domains\Services\Application\Dtos\DuplicateServiceInput;
use App\Domains\Services\Application\Dtos\ListServicesInput;
use App\Domains\Services\Application\Dtos\ShowServiceInput;
use App\Domains\Services\Application\Dtos\UpdateServiceInput;
use App\Domains\Services\Application\UseCases\CreateService;
use App\Domains\Services\Application\UseCases\DeleteService;
use App\Domains\Services\Application\UseCases\DuplicateService;
use App\Domains\Services\Application\UseCases\ListServices;
use App\Domains\Services\Application\UseCases\ShowService;
use App\Domains\Services\Application\UseCases\UpdateService;
use App\Domains\Services\Infrastructure\Http\Requests\CreateServiceRequest;
use App\Domains\Services\Infrastructure\Http\Requests\DuplicateServiceRequest;
use App\Domains\Services\Infrastructure\Http\Requests\ListServicesRequest;
use App\Domains\Services\Infrastructure\Http\Requests\UpdateServiceRequest;
use App\Domains\Services\Infrastructure\Http\Resources\ServiceResource;
use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponder;
use App\Http\Responses\PaginatedCollection;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

final class ServiceController extends Controller
{
    public function index(
        ListServicesRequest $request,
        ListServices $listServices,
        ApiResponder $responder,
    ): Response {
        try {
            $response = $listServices->handle(ListServicesInput::fromRequest($request->validated()));

            if ($response->failed()) {
                return $responder->failure($response->error(), $response->warnings());
            }

            return $responder->success(
                $response,
                PaginatedCollection::of($response->value(), ServiceResource::class),
                Response::HTTP_OK,
            );
        } catch (Throwable $unexpected) {
            return $responder->unexpected($request, $unexpected);
        }
    }

    public function store(
        CreateServiceRequest $request,
        CreateService $createService,
        ApiResponder $responder,
    ): Response {
        try {
            $response = $createService->handle(CreateServiceInput::fromRequest($request->validated()));

            if ($response->failed()) {
                return $responder->failure($response->error(), $response->warnings());
            }

            return $responder->success(
                $response,
                ServiceResource::make($response->value()),
                Response::HTTP_CREATED,
            );
        } catch (Throwable $unexpected) {
            return $responder->unexpected($request, $unexpected);
        }
    }

    public function show(
        Request $request,
        string $service,
        ShowService $showService,
        ApiResponder $responder,
    ): Response {
        try {
            $response = $showService->handle(new ShowServiceInput($service));

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

    public function update(
        UpdateServiceRequest $request,
        string $service,
        UpdateService $updateService,
        ApiResponder $responder,
    ): Response {
        try {
            $response = $updateService->handle(
                UpdateServiceInput::fromRequest($request->validated(), $service),
            );

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
        DeleteService $deleteService,
        ApiResponder $responder,
    ): Response {
        try {
            $response = $deleteService->handle(new DeleteServiceInput($service));

            if ($response->failed()) {
                return $responder->failure($response->error(), $response->warnings());
            }

            return response()->noContent();
        } catch (Throwable $unexpected) {
            return $responder->unexpected($request, $unexpected);
        }
    }

    public function duplicate(
        DuplicateServiceRequest $request,
        string $service,
        DuplicateService $duplicateService,
        ApiResponder $responder,
    ): Response {
        try {
            $response = $duplicateService->handle(
                DuplicateServiceInput::fromRequest($request->validated(), $service),
            );

            if ($response->failed()) {
                return $responder->failure($response->error(), $response->warnings());
            }

            return $responder->success(
                $response,
                ServiceResource::make($response->value()),
                Response::HTTP_CREATED,
            );
        } catch (Throwable $unexpected) {
            return $responder->unexpected($request, $unexpected);
        }
    }
}

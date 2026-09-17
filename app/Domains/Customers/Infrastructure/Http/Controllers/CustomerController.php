<?php

declare(strict_types=1);

namespace App\Domains\Customers\Infrastructure\Http\Controllers;

use App\Domains\Customers\Application\Dtos\CreateCustomerInput;
use App\Domains\Customers\Application\Dtos\DeleteCustomerInput;
use App\Domains\Customers\Application\Dtos\ListCustomersInput;
use App\Domains\Customers\Application\Dtos\ShowCustomerInput;
use App\Domains\Customers\Application\Dtos\UpdateCustomerInput;
use App\Domains\Customers\Application\UseCases\CreateCustomer;
use App\Domains\Customers\Application\UseCases\DeleteCustomer;
use App\Domains\Customers\Application\UseCases\ListCustomers;
use App\Domains\Customers\Application\UseCases\ShowCustomer;
use App\Domains\Customers\Application\UseCases\UpdateCustomer;
use App\Domains\Customers\Infrastructure\Http\Requests\CreateCustomerRequest;
use App\Domains\Customers\Infrastructure\Http\Requests\ListCustomersRequest;
use App\Domains\Customers\Infrastructure\Http\Requests\UpdateCustomerRequest;
use App\Domains\Customers\Infrastructure\Http\Resources\CustomerResource;
use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponder;
use App\Http\Responses\PaginatedCollection;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

final class CustomerController extends Controller
{
    public function index(
        ListCustomersRequest $request,
        ListCustomers $listCustomers,
        ApiResponder $responder,
    ): Response {
        try {
            $response = $listCustomers->handle(ListCustomersInput::fromRequest($request->validated()));

            if ($response->failed()) {
                return $responder->failure($response->error(), $response->warnings());
            }

            return $responder->success(
                $response,
                PaginatedCollection::of($response->value(), CustomerResource::class),
                Response::HTTP_OK,
            );
        } catch (Throwable $unexpected) {
            return $responder->unexpected($request, $unexpected);
        }
    }

    public function store(
        CreateCustomerRequest $request,
        CreateCustomer $createCustomer,
        ApiResponder $responder,
    ): Response {
        try {
            $response = $createCustomer->handle(CreateCustomerInput::fromRequest($request->validated()));

            if ($response->failed()) {
                return $responder->failure($response->error(), $response->warnings());
            }

            return $responder->success(
                $response,
                CustomerResource::make($response->value()),
                Response::HTTP_CREATED,
            );
        } catch (Throwable $unexpected) {
            return $responder->unexpected($request, $unexpected);
        }
    }

    public function show(
        Request $request,
        string $customer,
        ShowCustomer $showCustomer,
        ApiResponder $responder,
    ): Response {
        try {
            $response = $showCustomer->handle(new ShowCustomerInput($customer));

            if ($response->failed()) {
                return $responder->failure($response->error(), $response->warnings());
            }

            return $responder->success(
                $response,
                CustomerResource::make($response->value()),
                Response::HTTP_OK,
            );
        } catch (Throwable $unexpected) {
            return $responder->unexpected($request, $unexpected);
        }
    }

    public function update(
        UpdateCustomerRequest $request,
        string $customer,
        UpdateCustomer $updateCustomer,
        ApiResponder $responder,
    ): Response {
        try {
            $response = $updateCustomer->handle(
                UpdateCustomerInput::fromRequest($request->validated(), $customer),
            );

            if ($response->failed()) {
                return $responder->failure($response->error(), $response->warnings());
            }

            return $responder->success(
                $response,
                CustomerResource::make($response->value()),
                Response::HTTP_OK,
            );
        } catch (Throwable $unexpected) {
            return $responder->unexpected($request, $unexpected);
        }
    }

    public function destroy(
        Request $request,
        string $customer,
        DeleteCustomer $deleteCustomer,
        ApiResponder $responder,
    ): Response {
        try {
            $response = $deleteCustomer->handle(new DeleteCustomerInput($customer));

            if ($response->failed()) {
                return $responder->failure($response->error(), $response->warnings());
            }

            return response()->noContent();
        } catch (Throwable $unexpected) {
            return $responder->unexpected($request, $unexpected);
        }
    }
}

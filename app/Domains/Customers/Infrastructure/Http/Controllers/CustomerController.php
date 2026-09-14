<?php

declare(strict_types=1);

namespace App\Domains\Customers\Infrastructure\Http\Controllers;

use App\Domains\Customers\Application\Dtos\CreateCustomerInput;
use App\Domains\Customers\Application\UseCases\CreateCustomer;
use App\Domains\Customers\Infrastructure\Http\Requests\CreateCustomerRequest;
use App\Domains\Customers\Infrastructure\Http\Resources\CustomerResource;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

final class CustomerController extends Controller
{
    public function store(CreateCustomerRequest $request, CreateCustomer $createCustomer): JsonResponse
    {
        $customer = $createCustomer->handle(CreateCustomerInput::fromRequest($request->validated()));

        return CustomerResource::make($customer)
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }
}

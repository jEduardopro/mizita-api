<?php

declare(strict_types=1);

namespace App\Domains\Payments\Infrastructure\Http\Controllers;

use App\Domains\Payments\Application\Dtos\ListSalesInput;
use App\Domains\Payments\Application\Dtos\ListTransactionsInput;
use App\Domains\Payments\Application\UseCases\ListSales;
use App\Domains\Payments\Application\UseCases\ListTransactions;
use App\Domains\Payments\Infrastructure\Http\Requests\ListSalesRequest;
use App\Domains\Payments\Infrastructure\Http\Requests\ListTransactionsRequest;
use App\Domains\Payments\Infrastructure\Http\Resources\PaymentTransactionReportResource;
use App\Domains\Payments\Infrastructure\Http\Resources\SaleResource;
use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponder;
use App\Http\Responses\PaginatedCollection;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

final class PaymentReportController extends Controller
{
    public function sales(
        ListSalesRequest $request,
        ListSales $listSales,
        ApiResponder $responder,
    ): Response {
        try {
            $response = $listSales->handle(ListSalesInput::fromRequest($request->validated()));

            if ($response->failed()) {
                return $responder->failure($response->error(), $response->warnings());
            }

            return $responder->success(
                $response,
                PaginatedCollection::of($response->value(), SaleResource::class),
                Response::HTTP_OK,
            );
        } catch (Throwable $unexpected) {
            return $responder->unexpected($request, $unexpected);
        }
    }

    public function transactions(
        ListTransactionsRequest $request,
        ListTransactions $listTransactions,
        ApiResponder $responder,
    ): Response {
        try {
            $response = $listTransactions->handle(ListTransactionsInput::fromRequest($request->validated()));

            if ($response->failed()) {
                return $responder->failure($response->error(), $response->warnings());
            }

            return $responder->success(
                $response,
                PaginatedCollection::of($response->value(), PaymentTransactionReportResource::class),
                Response::HTTP_OK,
            );
        } catch (Throwable $unexpected) {
            return $responder->unexpected($request, $unexpected);
        }
    }
}

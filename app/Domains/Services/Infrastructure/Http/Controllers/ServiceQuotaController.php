<?php

declare(strict_types=1);

namespace App\Domains\Services\Infrastructure\Http\Controllers;

use App\Domains\Services\Application\UseCases\ShowActiveServiceQuota;
use App\Domains\Services\Infrastructure\Http\Resources\ActiveServiceQuotaResource;
use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponder;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

final class ServiceQuotaController extends Controller
{
    public function show(
        Request $request,
        ShowActiveServiceQuota $showQuota,
        ApiResponder $responder,
    ): Response {
        try {
            $response = $showQuota->handle();

            if ($response->failed()) {
                return $responder->failure($response->error(), $response->warnings());
            }

            return $responder->success(
                $response,
                ActiveServiceQuotaResource::make($response->value()),
                Response::HTTP_OK,
            );
        } catch (Throwable $unexpected) {
            return $responder->unexpected($request, $unexpected);
        }
    }
}

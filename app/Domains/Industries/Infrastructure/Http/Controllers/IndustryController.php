<?php

declare(strict_types=1);

namespace App\Domains\Industries\Infrastructure\Http\Controllers;

use App\Domains\Industries\Application\UseCases\ListIndustries;
use App\Domains\Industries\Infrastructure\Http\Resources\IndustryResource;
use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

final class IndustryController extends Controller
{
    public function index(
        Request $request,
        ListIndustries $listIndustries,
        ApiResponder $responder,
    ): JsonResponse {
        try {
            $response = $listIndustries->handle();

            if ($response->failed()) {
                return $responder->failure($response->error(), $response->warnings());
            }

            return $responder->success(
                $response,
                IndustryResource::collection($response->value()),
                Response::HTTP_OK,
            );
        } catch (Throwable $unexpected) {
            return $responder->unexpected($request, $unexpected);
        }
    }
}

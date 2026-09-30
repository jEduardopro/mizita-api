<?php

declare(strict_types=1);

namespace App\Domains\Statistics\Infrastructure\Http\Controllers;

use App\Domains\Statistics\Application\Dtos\ShowStatisticsInput;
use App\Domains\Statistics\Application\UseCases\ShowBusinessStatistics;
use App\Domains\Statistics\Infrastructure\Http\Requests\ShowStatisticsRequest;
use App\Domains\Statistics\Infrastructure\Http\Resources\BusinessStatisticsResource;
use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponder;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

final class StatisticsController extends Controller
{
    public function show(
        ShowStatisticsRequest $request,
        ShowBusinessStatistics $showBusinessStatistics,
        ApiResponder $responder,
    ): Response {
        try {
            $response = $showBusinessStatistics->handle(ShowStatisticsInput::fromRequest($request->validated()));

            if ($response->failed()) {
                return $responder->failure($response->error(), $response->warnings());
            }

            return $responder->success(
                $response,
                BusinessStatisticsResource::make($response->value()),
                Response::HTTP_OK,
            );
        } catch (Throwable $unexpected) {
            return $responder->unexpected($request, $unexpected);
        }
    }
}

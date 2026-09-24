<?php

declare(strict_types=1);

namespace App\Domains\Availability\Infrastructure\Http\Controllers;

use App\Domains\Availability\Application\Dtos\ReplaceMyScheduleInput;
use App\Domains\Availability\Application\Dtos\ShowMyScheduleInput;
use App\Domains\Availability\Application\UseCases\ReplaceMySchedule;
use App\Domains\Availability\Application\UseCases\ShowMySchedule;
use App\Domains\Availability\Infrastructure\Http\Requests\ReplaceMyScheduleRequest;
use App\Domains\Availability\Infrastructure\Http\Resources\MyScheduleResource;
use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponder;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

final class MyScheduleController extends Controller
{
    public function show(
        Request $request,
        ShowMySchedule $showMySchedule,
        ApiResponder $responder,
    ): JsonResponse {
        /** @var User $account */
        $account = $request->user();

        try {
            $response = $showMySchedule->handle(new ShowMyScheduleInput($account->uuid));

            if ($response->failed()) {
                return $responder->failure($response->error(), $response->warnings());
            }

            return $responder->success(
                $response,
                MyScheduleResource::make($response->value()),
                Response::HTTP_OK,
            );
        } catch (Throwable $unexpected) {
            return $responder->unexpected($request, $unexpected);
        }
    }

    public function update(
        ReplaceMyScheduleRequest $request,
        ReplaceMySchedule $replaceMySchedule,
        ApiResponder $responder,
    ): JsonResponse {
        /** @var User $account */
        $account = $request->user();

        try {
            $response = $replaceMySchedule->handle(
                ReplaceMyScheduleInput::fromRequest($request->validated(), $account->uuid),
            );

            if ($response->failed()) {
                return $responder->failure($response->error(), $response->warnings());
            }

            return $responder->success(
                $response,
                MyScheduleResource::make($response->value()),
                Response::HTTP_OK,
            );
        } catch (Throwable $unexpected) {
            return $responder->unexpected($request, $unexpected);
        }
    }
}

<?php

declare(strict_types=1);

namespace App\Domains\Availability\Infrastructure\Http\Controllers;

use App\Domains\Availability\Application\Dtos\ReplaceStaffMemberScheduleInput;
use App\Domains\Availability\Application\Dtos\ShowStaffMemberScheduleInput;
use App\Domains\Availability\Application\UseCases\ReplaceStaffMemberSchedule;
use App\Domains\Availability\Application\UseCases\ShowStaffMemberSchedule;
use App\Domains\Availability\Infrastructure\Http\Requests\ReplaceMyScheduleRequest;
use App\Domains\Availability\Infrastructure\Http\Resources\MyScheduleResource;
use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

final class StaffMemberScheduleController extends Controller
{
    public function show(
        Request $request,
        string $staffMember,
        ShowStaffMemberSchedule $showStaffMemberSchedule,
        ApiResponder $responder,
    ): JsonResponse {
        try {
            $response = $showStaffMemberSchedule->handle(new ShowStaffMemberScheduleInput($staffMember));

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
        string $staffMember,
        ReplaceStaffMemberSchedule $replaceStaffMemberSchedule,
        ApiResponder $responder,
    ): JsonResponse {
        try {
            $response = $replaceStaffMemberSchedule->handle(
                ReplaceStaffMemberScheduleInput::fromRequest($request->validated(), $staffMember),
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

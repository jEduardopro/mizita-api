<?php

declare(strict_types=1);

namespace App\Domains\Staff\Infrastructure\Http\Controllers;

use App\Domains\Staff\Application\UseCases\ListStaffMembers;
use App\Domains\Staff\Infrastructure\Http\Resources\StaffMemberResource;
use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

final class StaffMemberController extends Controller
{
    public function index(
        Request $request,
        ListStaffMembers $listStaffMembers,
        ApiResponder $responder,
    ): JsonResponse {
        try {
            $response = $listStaffMembers->handle();

            if ($response->failed()) {
                return $responder->failure($response->error(), $response->warnings());
            }

            return $responder->success(
                $response,
                StaffMemberResource::collection($response->value()),
                Response::HTTP_OK,
            );
        } catch (Throwable $unexpected) {
            return $responder->unexpected($request, $unexpected);
        }
    }
}

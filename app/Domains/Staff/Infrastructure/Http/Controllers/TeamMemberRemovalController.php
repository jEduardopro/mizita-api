<?php

declare(strict_types=1);

namespace App\Domains\Staff\Infrastructure\Http\Controllers;

use App\Domains\Staff\Application\Dtos\CheckTeamMemberRemovalInput;
use App\Domains\Staff\Application\UseCases\CheckTeamMemberRemoval;
use App\Domains\Staff\Infrastructure\Http\Resources\TeamMemberRemovalResource;
use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponder;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

final class TeamMemberRemovalController extends Controller
{
    public function show(
        Request $request,
        string $staffMember,
        CheckTeamMemberRemoval $checkTeamMemberRemoval,
        ApiResponder $responder,
    ): Response {
        try {
            $response = $checkTeamMemberRemoval->handle(new CheckTeamMemberRemovalInput($staffMember));

            if ($response->failed()) {
                return $responder->failure($response->error(), $response->warnings());
            }

            return $responder->success(
                $response,
                TeamMemberRemovalResource::make($response->value()),
                Response::HTTP_OK,
            );
        } catch (Throwable $unexpected) {
            return $responder->unexpected($request, $unexpected);
        }
    }
}

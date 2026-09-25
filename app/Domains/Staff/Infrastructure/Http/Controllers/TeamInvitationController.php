<?php

declare(strict_types=1);

namespace App\Domains\Staff\Infrastructure\Http\Controllers;

use App\Domains\Staff\Application\Dtos\ResendTeamInvitationInput;
use App\Domains\Staff\Application\UseCases\ResendTeamInvitation;
use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponder;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

final class TeamInvitationController extends Controller
{
    public function store(
        Request $request,
        string $staffMember,
        ResendTeamInvitation $resendTeamInvitation,
        ApiResponder $responder,
    ): Response {
        try {
            $response = $resendTeamInvitation->handle(new ResendTeamInvitationInput($staffMember));

            if ($response->failed()) {
                return $responder->failure($response->error(), $response->warnings());
            }

            return response()->noContent(Response::HTTP_ACCEPTED);
        } catch (Throwable $unexpected) {
            return $responder->unexpected($request, $unexpected);
        }
    }
}

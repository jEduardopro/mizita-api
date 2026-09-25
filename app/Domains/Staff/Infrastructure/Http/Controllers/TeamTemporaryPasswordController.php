<?php

declare(strict_types=1);

namespace App\Domains\Staff\Infrastructure\Http\Controllers;

use App\Domains\Staff\Application\Dtos\RevealTeamMemberTemporaryPasswordInput;
use App\Domains\Staff\Application\UseCases\RevealTeamMemberTemporaryPassword;
use App\Domains\Staff\Infrastructure\Http\Resources\TemporaryPasswordResource;
use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponder;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

final class TeamTemporaryPasswordController extends Controller
{
    private const UNCACHEABLE = 'no-store, private';

    public function show(
        Request $request,
        string $staffMember,
        RevealTeamMemberTemporaryPassword $revealTemporaryPassword,
        ApiResponder $responder,
    ): Response {
        try {
            $response = $revealTemporaryPassword->handle(new RevealTeamMemberTemporaryPasswordInput($staffMember));

            if ($response->failed()) {
                return $responder->failure($response->error(), $response->warnings());
            }

            return $responder->success(
                $response,
                TemporaryPasswordResource::make($response->value()),
                Response::HTTP_OK,
            )->header('Cache-Control', self::UNCACHEABLE);
        } catch (Throwable $unexpected) {
            return $responder->unexpected($request, $unexpected);
        }
    }
}

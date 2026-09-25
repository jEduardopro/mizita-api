<?php

declare(strict_types=1);

namespace App\Domains\Staff\Infrastructure\Http\Controllers;

use App\Domains\Staff\Application\Dtos\RemoveTeamMemberPhotoInput;
use App\Domains\Staff\Application\Dtos\ReplaceTeamMemberPhotoInput;
use App\Domains\Staff\Application\UseCases\RemoveTeamMemberPhoto;
use App\Domains\Staff\Application\UseCases\ReplaceTeamMemberPhoto;
use App\Domains\Staff\Infrastructure\Http\Requests\UploadProfilePhotoRequest;
use App\Domains\Staff\Infrastructure\Http\Resources\TeamMemberResource;
use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponder;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

final class TeamMemberPhotoController extends Controller
{
    public function store(
        UploadProfilePhotoRequest $request,
        string $staffMember,
        ReplaceTeamMemberPhoto $replaceTeamMemberPhoto,
        ApiResponder $responder,
    ): Response {
        try {
            /** @var UploadedFile $photo */
            $photo = $request->file('photo');

            $response = $replaceTeamMemberPhoto->handle(new ReplaceTeamMemberPhotoInput(
                staffMemberId: $staffMember,
                sourcePath: (string) $photo->getRealPath(),
                fileName: $photo->getClientOriginalName(),
                mimeType: (string) $photo->getMimeType(),
                sizeInBytes: (int) $photo->getSize(),
            ));

            if ($response->failed()) {
                return $responder->failure($response->error(), $response->warnings());
            }

            return $responder->success(
                $response,
                TeamMemberResource::make($response->value()),
                Response::HTTP_OK,
            );
        } catch (Throwable $unexpected) {
            return $responder->unexpected($request, $unexpected);
        }
    }

    public function destroy(
        Request $request,
        string $staffMember,
        RemoveTeamMemberPhoto $removeTeamMemberPhoto,
        ApiResponder $responder,
    ): Response {
        try {
            $response = $removeTeamMemberPhoto->handle(new RemoveTeamMemberPhotoInput($staffMember));

            if ($response->failed()) {
                return $responder->failure($response->error(), $response->warnings());
            }

            return $responder->success(
                $response,
                TeamMemberResource::make($response->value()),
                Response::HTTP_OK,
            );
        } catch (Throwable $unexpected) {
            return $responder->unexpected($request, $unexpected);
        }
    }
}

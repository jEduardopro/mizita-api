<?php

declare(strict_types=1);

namespace App\Domains\Staff\Infrastructure\Http\Controllers;

use App\Domains\Staff\Application\Dtos\RemoveMyProfilePhotoInput;
use App\Domains\Staff\Application\Dtos\ReplaceMyProfilePhotoInput;
use App\Domains\Staff\Application\UseCases\RemoveMyProfilePhoto;
use App\Domains\Staff\Application\UseCases\ReplaceMyProfilePhoto;
use App\Domains\Staff\Infrastructure\Http\Requests\UploadProfilePhotoRequest;
use App\Domains\Staff\Infrastructure\Http\Resources\MyProfileResource;
use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponder;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

final class MyProfilePhotoController extends Controller
{
    public function store(
        UploadProfilePhotoRequest $request,
        ReplaceMyProfilePhoto $replaceMyProfilePhoto,
        ApiResponder $responder,
    ): Response {
        /** @var User $account */
        $account = $request->user();

        try {
            /** @var UploadedFile $photo */
            $photo = $request->file('photo');

            $response = $replaceMyProfilePhoto->handle(new ReplaceMyProfilePhotoInput(
                accountId: $account->uuid,
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
                MyProfileResource::make($response->value()),
                Response::HTTP_OK,
            );
        } catch (Throwable $unexpected) {
            return $responder->unexpected($request, $unexpected);
        }
    }

    public function destroy(
        Request $request,
        RemoveMyProfilePhoto $removeMyProfilePhoto,
        ApiResponder $responder,
    ): Response {
        /** @var User $account */
        $account = $request->user();

        try {
            $response = $removeMyProfilePhoto->handle(new RemoveMyProfilePhotoInput($account->uuid));

            if ($response->failed()) {
                return $responder->failure($response->error(), $response->warnings());
            }

            return $responder->success(
                $response,
                MyProfileResource::make($response->value()),
                Response::HTTP_OK,
            );
        } catch (Throwable $unexpected) {
            return $responder->unexpected($request, $unexpected);
        }
    }
}

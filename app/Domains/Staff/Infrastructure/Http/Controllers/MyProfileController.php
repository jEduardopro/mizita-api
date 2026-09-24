<?php

declare(strict_types=1);

namespace App\Domains\Staff\Infrastructure\Http\Controllers;

use App\Domains\Staff\Application\Dtos\ShowMyProfileInput;
use App\Domains\Staff\Application\Dtos\UpdateMyProfileInput;
use App\Domains\Staff\Application\UseCases\ShowMyProfile;
use App\Domains\Staff\Application\UseCases\UpdateMyProfile;
use App\Domains\Staff\Infrastructure\Http\Requests\UpdateMyProfileRequest;
use App\Domains\Staff\Infrastructure\Http\Resources\MyProfileResource;
use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponder;
use App\Models\User;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

final class MyProfileController extends Controller
{
    public function show(
        Request $request,
        ShowMyProfile $showMyProfile,
        ApiResponder $responder,
    ): Response {
        /** @var User $account */
        $account = $request->user();

        try {
            $response = $showMyProfile->handle(new ShowMyProfileInput($account->uuid));

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

    public function update(
        UpdateMyProfileRequest $request,
        UpdateMyProfile $updateMyProfile,
        ApiResponder $responder,
    ): Response {
        /** @var User $account */
        $account = $request->user();

        try {
            $response = $updateMyProfile->handle(
                UpdateMyProfileInput::fromRequest($request->validated(), $account->uuid),
            );

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

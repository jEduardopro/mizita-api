<?php

declare(strict_types=1);

namespace App\Domains\Accounts\Infrastructure\Http\Controllers;

use App\Domains\Accounts\Application\Dtos\SignInWithGoogleIdTokenInput;
use App\Domains\Accounts\Application\UseCases\SignInWithGoogleIdToken;
use App\Domains\Accounts\Infrastructure\Auth\AccountAuthenticator;
use App\Domains\Accounts\Infrastructure\Http\Requests\GoogleIdTokenRequest;
use App\Domains\Accounts\Infrastructure\Http\Resources\AccessTokenResource;
use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponder;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

final class GoogleIdTokenController extends Controller
{
    public function __invoke(
        GoogleIdTokenRequest $request,
        SignInWithGoogleIdToken $signIn,
        AccountAuthenticator $authenticator,
        ApiResponder $responder,
    ): JsonResponse {
        try {
            $response = $signIn->handle(SignInWithGoogleIdTokenInput::fromRequest($request->validated()));

            if ($response->failed()) {
                return $responder->failure($response->error(), $response->warnings());
            }

            $account = $response->value();

            return $responder->success(
                $response,
                AccessTokenResource::make($account, $authenticator->issueAccessTokenFor($account->id)),
                Response::HTTP_OK,
            );
        } catch (Throwable $unexpected) {
            return $responder->unexpected($request, $unexpected);
        }
    }
}

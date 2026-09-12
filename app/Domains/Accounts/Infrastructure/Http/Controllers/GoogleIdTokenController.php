<?php

declare(strict_types=1);

namespace App\Domains\Accounts\Infrastructure\Http\Controllers;

use App\Domains\Accounts\Application\Dtos\SignInWithGoogleIdTokenInput;
use App\Domains\Accounts\Application\UseCases\SignInWithGoogleIdToken;
use App\Domains\Accounts\Exceptions\GoogleEmailNotVerified;
use App\Domains\Accounts\Exceptions\InvalidGoogleIdToken;
use App\Domains\Accounts\Infrastructure\Auth\AccountAuthenticator;
use App\Domains\Accounts\Infrastructure\Http\Requests\GoogleIdTokenRequest;
use App\Domains\Accounts\Infrastructure\Http\Resources\AccessTokenResource;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

/**
 * The native entry point: a Google ID token in, a Sanctum bearer token out.
 *
 * Unauthenticated by design - it is the sign in - so the rate limiter on its
 * route group is the only thing standing between it and the internet.
 */
final class GoogleIdTokenController extends Controller
{
    public function __invoke(
        GoogleIdTokenRequest $request,
        SignInWithGoogleIdToken $signIn,
        AccountAuthenticator $authenticator,
    ): JsonResponse {
        try {
            $account = $signIn->handle(new SignInWithGoogleIdTokenInput(
                idToken: $request->string('id_token')->toString(),
            ));
        } catch (InvalidGoogleIdToken) {
            return $this->failure('messages.errors.google_invalid_id_token', Response::HTTP_UNAUTHORIZED);
        } catch (GoogleEmailNotVerified) {
            return $this->failure('messages.errors.google_email_not_verified', Response::HTTP_FORBIDDEN);
        }

        return AccessTokenResource::make($account, $authenticator->issueAccessTokenFor($account->id))
            ->response();
    }

    private function failure(string $messageKey, int $status): JsonResponse
    {
        return new JsonResponse(['message' => __($messageKey)], $status);
    }
}

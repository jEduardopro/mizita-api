<?php

declare(strict_types=1);

namespace App\Domains\Accounts\Infrastructure\Http\Controllers;

use App\Domains\Accounts\Application\Dtos\ShowSignInSecurityInput;
use App\Domains\Accounts\Application\UseCases\ShowSignInSecurity;
use App\Domains\Accounts\Infrastructure\Http\Resources\SignInSecurityResource;
use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponder;
use App\Models\User;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

final class SignInSecurityController extends Controller
{
    public const RATE_LIMITER = 'sign-in-security';

    public function __invoke(
        Request $request,
        ShowSignInSecurity $showSignInSecurity,
        ApiResponder $responder,
    ): Response {
        /** @var User $account */
        $account = $request->user();

        try {
            $response = $showSignInSecurity->handle(new ShowSignInSecurityInput($account->uuid));

            if ($response->failed()) {
                return $responder->failure($response->error(), $response->warnings());
            }

            return $responder->success(
                $response,
                SignInSecurityResource::make($response->value()),
                Response::HTTP_OK,
            );
        } catch (Throwable $unexpected) {
            return $responder->unexpected($request, $unexpected);
        }
    }
}

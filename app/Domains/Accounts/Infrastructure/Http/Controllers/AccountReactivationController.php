<?php

declare(strict_types=1);

namespace App\Domains\Accounts\Infrastructure\Http\Controllers;

use App\Domains\Accounts\Application\Dtos\AccountReactivatedData;
use App\Domains\Accounts\Application\Dtos\ReactivateAccountInput;
use App\Domains\Accounts\Application\Dtos\ShowAccountReactivationInput;
use App\Domains\Accounts\Application\UseCases\ReactivateAccount;
use App\Domains\Accounts\Application\UseCases\ShowAccountReactivation;
use App\Domains\Accounts\Infrastructure\Auth\AccountAuthenticator;
use App\Domains\Accounts\Infrastructure\Auth\PendingReactivation;
use App\Domains\Accounts\Infrastructure\Http\Resources\AccountReactivatedResource;
use App\Domains\Accounts\Infrastructure\Http\Resources\AccountReactivationResource;
use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponder;
use App\Shared\Contracts\Clock;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

final class AccountReactivationController extends Controller
{
    public const REACTIVATE_ROUTE = 'account.reactivate';

    public function show(
        Request $request,
        PendingReactivation $pendingReactivation,
        Clock $clock,
        ShowAccountReactivation $showAccountReactivation,
        ApiResponder $responder,
    ): Response {
        try {
            $response = $showAccountReactivation->handle(
                ShowAccountReactivationInput::forPendingAccount($pendingReactivation->pendingAccountId($clock->now())),
            );

            if ($response->failed()) {
                return $responder->failure($response->error(), $response->warnings());
            }

            return $responder->success(
                $response,
                AccountReactivationResource::make($response->value()),
                Response::HTTP_OK,
            );
        } catch (Throwable $unexpected) {
            return $responder->unexpected($request, $unexpected);
        }
    }

    public function store(
        Request $request,
        PendingReactivation $pendingReactivation,
        Clock $clock,
        ReactivateAccount $reactivateAccount,
        AccountAuthenticator $authenticator,
        ApiResponder $responder,
    ): Response {
        try {
            $response = $reactivateAccount->handle(
                ReactivateAccountInput::forPendingAccount($pendingReactivation->pendingAccountId($clock->now())),
            );

            if ($response->failed()) {
                return $responder->failure($response->error(), $response->warnings());
            }

            $reactivated = $response->value();

            $pendingReactivation->forget();

            $this->continueSignInFor($reactivated, $request, $authenticator);

            return $responder->success(
                $response,
                AccountReactivatedResource::make($reactivated),
                Response::HTTP_OK,
            );
        } catch (Throwable $unexpected) {
            return $responder->unexpected($request, $unexpected);
        }
    }

    public function destroy(PendingReactivation $pendingReactivation): Response
    {
        $pendingReactivation->forget();

        return response()->noContent();
    }

    private function continueSignInFor(
        AccountReactivatedData $reactivated,
        Request $request,
        AccountAuthenticator $authenticator,
    ): void {
        if ($reactivated->requiresSecondFactor) {
            $authenticator->challengeSecondFactorFor($reactivated->accountId);

            return;
        }

        $authenticator->startSessionFor($reactivated->accountId);

        $request->session()->regenerate();
    }
}

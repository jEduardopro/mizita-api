<?php

declare(strict_types=1);

namespace App\Domains\Accounts\Infrastructure\Http\Controllers;

use App\Domains\Accounts\Application\Dtos\DeleteAccountInput;
use App\Domains\Accounts\Application\Dtos\PreviewAccountDeletionInput;
use App\Domains\Accounts\Application\UseCases\DeleteAccount;
use App\Domains\Accounts\Application\UseCases\PreviewAccountDeletion;
use App\Domains\Accounts\Infrastructure\Http\Requests\DeleteAccountRequest;
use App\Domains\Accounts\Infrastructure\Http\Resources\AccountDeletionPreviewResource;
use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponder;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

final class AccountDeletionController extends Controller
{
    private const SESSION_GUARD = 'web';

    public function show(
        Request $request,
        PreviewAccountDeletion $previewAccountDeletion,
        ApiResponder $responder,
    ): Response {
        /** @var User $account */
        $account = $request->user();

        try {
            $response = $previewAccountDeletion->handle(new PreviewAccountDeletionInput($account->uuid));

            if ($response->failed()) {
                return $responder->failure($response->error(), $response->warnings());
            }

            return $responder->success(
                $response,
                AccountDeletionPreviewResource::make($response->value()),
                Response::HTTP_OK,
            );
        } catch (Throwable $unexpected) {
            return $responder->unexpected($request, $unexpected);
        }
    }

    public function destroy(
        DeleteAccountRequest $request,
        DeleteAccount $deleteAccount,
        ApiResponder $responder,
    ): Response {
        /** @var User $account */
        $account = $request->user();

        try {
            $response = $deleteAccount->handle(DeleteAccountInput::fromRequest($request->validated(), $account->uuid));

            if ($response->failed()) {
                return $responder->failure($response->error(), $response->warnings());
            }

            $this->endCurrentSession($request);

            return response()->noContent();
        } catch (Throwable $unexpected) {
            return $responder->unexpected($request, $unexpected);
        }
    }

    private function endCurrentSession(Request $request): void
    {
        Auth::guard(self::SESSION_GUARD)->logout();

        if (! $request->hasSession()) {
            return;
        }

        $request->session()->invalidate();
        $request->session()->regenerateToken();
    }
}

<?php

declare(strict_types=1);

namespace App\Domains\Integrations\Infrastructure\Http\Controllers;

use App\Domains\Integrations\Application\Dtos\DisconnectCalendarInput;
use App\Domains\Integrations\Application\UseCases\DisconnectCalendar;
use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponder;
use App\Models\User;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

final class GoogleCalendarConnectionController extends Controller
{
    public function destroy(
        Request $request,
        DisconnectCalendar $disconnectCalendar,
        ApiResponder $responder,
    ): Response {
        /** @var User $account */
        $account = $request->user();

        try {
            $response = $disconnectCalendar->handle(new DisconnectCalendarInput($account->uuid));

            if ($response->failed()) {
                return $responder->failure($response->error(), $response->warnings());
            }

            return response()->noContent();
        } catch (Throwable $unexpected) {
            return $responder->unexpected($request, $unexpected);
        }
    }
}

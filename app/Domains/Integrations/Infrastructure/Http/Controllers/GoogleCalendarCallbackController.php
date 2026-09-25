<?php

declare(strict_types=1);

namespace App\Domains\Integrations\Infrastructure\Http\Controllers;

use App\Domains\Integrations\Application\Dtos\CompleteCalendarAuthorizationInput;
use App\Domains\Integrations\Application\UseCases\CompleteCalendarAuthorization;
use App\Http\Controllers\Controller;
use App\Http\Responses\WebResponder;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Throwable;

final class GoogleCalendarCallbackController extends Controller
{
    public const CALLBACK_ROUTE = 'integrations.google-calendar.callback';

    public const INTEGRATIONS_ROUTE = 'integrations';

    private const ERROR_KEY = 'google_calendar';

    private const STATUS_FLASH_KEY = 'status';

    private const CONNECTED_STATUS = 'google-calendar-connected';

    public function __invoke(
        Request $request,
        CompleteCalendarAuthorization $completeAuthorization,
        WebResponder $responder,
    ): RedirectResponse {
        /** @var User $account */
        $account = $request->user();

        try {
            $response = $completeAuthorization->handle(
                CompleteCalendarAuthorizationInput::fromRequest($request->query(), $account->uuid),
            );

            if ($response->failed()) {
                return $responder->backTo(self::INTEGRATIONS_ROUTE, self::ERROR_KEY, $response->error());
            }

            return redirect()
                ->route(self::INTEGRATIONS_ROUTE)
                ->with(self::STATUS_FLASH_KEY, self::CONNECTED_STATUS);
        } catch (Throwable $unexpected) {
            return $responder->unexpected($request, $unexpected, self::INTEGRATIONS_ROUTE, self::ERROR_KEY);
        }
    }
}

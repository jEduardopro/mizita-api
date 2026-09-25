<?php

declare(strict_types=1);

namespace App\Domains\Businesses\Infrastructure\Http\Controllers;

use App\Domains\Businesses\Application\UseCases\ShowCalendarSettings;
use App\Domains\Businesses\Infrastructure\Http\Resources\CalendarSettingsResource;
use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponder;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

final class CalendarSettingsController extends Controller
{
    public function show(
        Request $request,
        ShowCalendarSettings $showCalendarSettings,
        ApiResponder $responder,
    ): Response {
        try {
            $response = $showCalendarSettings->handle();

            if ($response->failed()) {
                return $responder->failure($response->error(), $response->warnings());
            }

            return $responder->success(
                $response,
                CalendarSettingsResource::make($response->value()),
                Response::HTTP_OK,
            );
        } catch (Throwable $unexpected) {
            return $responder->unexpected($request, $unexpected);
        }
    }
}

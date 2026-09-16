<?php

declare(strict_types=1);

namespace App\Domains\Businesses\Infrastructure\Http\Controllers;

use App\Domains\Businesses\Application\Dtos\UpdateBusinessSettingsInput;
use App\Domains\Businesses\Application\UseCases\ShowBusinessSettings;
use App\Domains\Businesses\Application\UseCases\UpdateBusinessSettings;
use App\Domains\Businesses\Infrastructure\Http\Requests\UpdateBusinessSettingsRequest;
use App\Domains\Businesses\Infrastructure\Http\Resources\BusinessSettingsResource;
use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponder;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

final class BusinessSettingsController extends Controller
{
    public function show(
        Request $request,
        ShowBusinessSettings $showBusinessSettings,
        ApiResponder $responder,
    ): Response {
        try {
            $response = $showBusinessSettings->handle();

            if ($response->failed()) {
                return $responder->failure($response->error(), $response->warnings());
            }

            return $responder->success(
                $response,
                BusinessSettingsResource::make($response->value()),
                Response::HTTP_OK,
            );
        } catch (Throwable $unexpected) {
            return $responder->unexpected($request, $unexpected);
        }
    }

    public function update(
        UpdateBusinessSettingsRequest $request,
        UpdateBusinessSettings $updateBusinessSettings,
        ApiResponder $responder,
    ): Response {
        try {
            $response = $updateBusinessSettings->handle(
                UpdateBusinessSettingsInput::fromRequest($request->validated()),
            );

            if ($response->failed()) {
                return $responder->failure($response->error(), $response->warnings());
            }

            return $responder->success(
                $response,
                BusinessSettingsResource::make($response->value()),
                Response::HTTP_OK,
            );
        } catch (Throwable $unexpected) {
            return $responder->unexpected($request, $unexpected);
        }
    }
}

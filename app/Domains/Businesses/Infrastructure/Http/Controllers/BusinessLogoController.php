<?php

declare(strict_types=1);

namespace App\Domains\Businesses\Infrastructure\Http\Controllers;

use App\Domains\Businesses\Application\Dtos\AttachBusinessLogoInput;
use App\Domains\Businesses\Application\UseCases\AttachBusinessLogo;
use App\Domains\Businesses\Application\UseCases\RemoveBusinessLogo;
use App\Domains\Businesses\Infrastructure\Http\Requests\UploadBusinessLogoRequest;
use App\Domains\Businesses\Infrastructure\Http\Resources\BusinessSettingsResource;
use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponder;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

final class BusinessLogoController extends Controller
{
    public function store(
        UploadBusinessLogoRequest $request,
        AttachBusinessLogo $attachBusinessLogo,
        ApiResponder $responder,
    ): Response {
        try {
            /** @var UploadedFile $logo */
            $logo = $request->file('logo');

            $response = $attachBusinessLogo->handle(new AttachBusinessLogoInput(
                sourcePath: (string) $logo->getRealPath(),
                fileName: $logo->getClientOriginalName(),
                mimeType: (string) $logo->getMimeType(),
                sizeInBytes: (int) $logo->getSize(),
            ));

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

    public function destroy(
        Request $request,
        RemoveBusinessLogo $removeBusinessLogo,
        ApiResponder $responder,
    ): Response {
        try {
            $response = $removeBusinessLogo->handle();

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

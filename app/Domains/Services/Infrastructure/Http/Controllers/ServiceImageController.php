<?php

declare(strict_types=1);

namespace App\Domains\Services\Infrastructure\Http\Controllers;

use App\Domains\Services\Application\Dtos\AttachServiceImageInput;
use App\Domains\Services\Application\Dtos\RemoveServiceImageInput;
use App\Domains\Services\Application\UseCases\AttachServiceImage;
use App\Domains\Services\Application\UseCases\RemoveServiceImage;
use App\Domains\Services\Infrastructure\Http\Requests\UploadServiceImageRequest;
use App\Domains\Services\Infrastructure\Http\Resources\ServiceResource;
use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponder;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

final class ServiceImageController extends Controller
{
    public function store(
        UploadServiceImageRequest $request,
        string $service,
        AttachServiceImage $attachServiceImage,
        ApiResponder $responder,
    ): Response {
        try {
            /** @var UploadedFile $image */
            $image = $request->file('image');

            $response = $attachServiceImage->handle(new AttachServiceImageInput(
                serviceId: $service,
                sourcePath: (string) $image->getRealPath(),
                fileName: $image->getClientOriginalName(),
                mimeType: (string) $image->getMimeType(),
                sizeInBytes: (int) $image->getSize(),
            ));

            if ($response->failed()) {
                return $responder->failure($response->error(), $response->warnings());
            }

            return $responder->success(
                $response,
                ServiceResource::make($response->value()),
                Response::HTTP_OK,
            );
        } catch (Throwable $unexpected) {
            return $responder->unexpected($request, $unexpected);
        }
    }

    public function destroy(
        Request $request,
        string $service,
        RemoveServiceImage $removeServiceImage,
        ApiResponder $responder,
    ): Response {
        try {
            $response = $removeServiceImage->handle(new RemoveServiceImageInput($service));

            if ($response->failed()) {
                return $responder->failure($response->error(), $response->warnings());
            }

            return $responder->success(
                $response,
                ServiceResource::make($response->value()),
                Response::HTTP_OK,
            );
        } catch (Throwable $unexpected) {
            return $responder->unexpected($request, $unexpected);
        }
    }
}

<?php

declare(strict_types=1);

namespace App\Domains\BookingPages\Infrastructure\Http\Controllers;

use App\Domains\BookingPages\Application\Dtos\AttachBookingPageImageInput;
use App\Domains\BookingPages\Application\UseCases\AttachBookingPageBanner;
use App\Domains\BookingPages\Application\UseCases\RemoveBookingPageBanner;
use App\Domains\BookingPages\Infrastructure\Http\Requests\UploadBookingPageImageRequest;
use App\Domains\BookingPages\Infrastructure\Http\Resources\BookingPageResource;
use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponder;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

final class BookingPageBannerController extends Controller
{
    public function store(
        UploadBookingPageImageRequest $request,
        AttachBookingPageBanner $attachBookingPageBanner,
        ApiResponder $responder,
    ): Response {
        try {
            /** @var UploadedFile $image */
            $image = $request->file('image');

            $response = $attachBookingPageBanner->handle(new AttachBookingPageImageInput(
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
                BookingPageResource::make($response->value()),
                Response::HTTP_OK,
            );
        } catch (Throwable $unexpected) {
            return $responder->unexpected($request, $unexpected);
        }
    }

    public function destroy(
        Request $request,
        RemoveBookingPageBanner $removeBookingPageBanner,
        ApiResponder $responder,
    ): Response {
        try {
            $response = $removeBookingPageBanner->handle();

            if ($response->failed()) {
                return $responder->failure($response->error(), $response->warnings());
            }

            return $responder->success(
                $response,
                BookingPageResource::make($response->value()),
                Response::HTTP_OK,
            );
        } catch (Throwable $unexpected) {
            return $responder->unexpected($request, $unexpected);
        }
    }
}

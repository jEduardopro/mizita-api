<?php

declare(strict_types=1);

namespace App\Domains\BookingPages\Infrastructure\Http\Controllers;

use App\Domains\BookingPages\Application\Dtos\AttachBookingPageImageInput;
use App\Domains\BookingPages\Application\Dtos\RemoveBookingPageGalleryImageInput;
use App\Domains\BookingPages\Application\Dtos\ReorderBookingPageGalleryInput;
use App\Domains\BookingPages\Application\UseCases\AddBookingPageGalleryImage;
use App\Domains\BookingPages\Application\UseCases\RemoveBookingPageGalleryImage;
use App\Domains\BookingPages\Application\UseCases\ReorderBookingPageGallery;
use App\Domains\BookingPages\Infrastructure\Http\Requests\ReorderBookingPageGalleryRequest;
use App\Domains\BookingPages\Infrastructure\Http\Requests\UploadBookingPageImageRequest;
use App\Domains\BookingPages\Infrastructure\Http\Resources\BookingPageResource;
use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponder;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

final class BookingPageGalleryController extends Controller
{
    public function store(
        UploadBookingPageImageRequest $request,
        AddBookingPageGalleryImage $addBookingPageGalleryImage,
        ApiResponder $responder,
    ): Response {
        try {
            /** @var UploadedFile $image */
            $image = $request->file('image');

            $response = $addBookingPageGalleryImage->handle(new AttachBookingPageImageInput(
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
                Response::HTTP_CREATED,
            );
        } catch (Throwable $unexpected) {
            return $responder->unexpected($request, $unexpected);
        }
    }

    public function destroy(
        Request $request,
        string $image,
        RemoveBookingPageGalleryImage $removeBookingPageGalleryImage,
        ApiResponder $responder,
    ): Response {
        try {
            $response = $removeBookingPageGalleryImage->handle(
                new RemoveBookingPageGalleryImageInput($image),
            );

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

    public function reorder(
        ReorderBookingPageGalleryRequest $request,
        ReorderBookingPageGallery $reorderBookingPageGallery,
        ApiResponder $responder,
    ): Response {
        try {
            $response = $reorderBookingPageGallery->handle(
                ReorderBookingPageGalleryInput::fromRequest($request->validated()),
            );

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

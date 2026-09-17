<?php

declare(strict_types=1);

namespace App\Domains\Customers\Infrastructure\Http\Controllers;

use App\Domains\Customers\Application\Dtos\AttachCustomerPhotoInput;
use App\Domains\Customers\Application\Dtos\RemoveCustomerPhotoInput;
use App\Domains\Customers\Application\UseCases\AttachCustomerPhoto;
use App\Domains\Customers\Application\UseCases\RemoveCustomerPhoto;
use App\Domains\Customers\Infrastructure\Http\Requests\UploadCustomerPhotoRequest;
use App\Domains\Customers\Infrastructure\Http\Resources\CustomerResource;
use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponder;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

final class CustomerPhotoController extends Controller
{
    public function store(
        UploadCustomerPhotoRequest $request,
        string $customer,
        AttachCustomerPhoto $attachCustomerPhoto,
        ApiResponder $responder,
    ): Response {
        try {
            /** @var UploadedFile $photo */
            $photo = $request->file('photo');

            $response = $attachCustomerPhoto->handle(new AttachCustomerPhotoInput(
                customerId: $customer,
                sourcePath: (string) $photo->getRealPath(),
                fileName: $photo->getClientOriginalName(),
                mimeType: (string) $photo->getMimeType(),
                sizeInBytes: (int) $photo->getSize(),
            ));

            if ($response->failed()) {
                return $responder->failure($response->error(), $response->warnings());
            }

            return $responder->success(
                $response,
                CustomerResource::make($response->value()),
                Response::HTTP_OK,
            );
        } catch (Throwable $unexpected) {
            return $responder->unexpected($request, $unexpected);
        }
    }

    public function destroy(
        Request $request,
        string $customer,
        RemoveCustomerPhoto $removeCustomerPhoto,
        ApiResponder $responder,
    ): Response {
        try {
            $response = $removeCustomerPhoto->handle(new RemoveCustomerPhotoInput($customer));

            if ($response->failed()) {
                return $responder->failure($response->error(), $response->warnings());
            }

            return $responder->success(
                $response,
                CustomerResource::make($response->value()),
                Response::HTTP_OK,
            );
        } catch (Throwable $unexpected) {
            return $responder->unexpected($request, $unexpected);
        }
    }
}

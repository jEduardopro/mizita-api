<?php

declare(strict_types=1);

namespace App\Domains\PublicCatalog\Infrastructure\Http\Controllers;

use App\Domains\PublicCatalog\Application\Dtos\ShowPublicBusinessPageInput;
use App\Domains\PublicCatalog\Application\UseCases\ShowPublicBusinessPage;
use App\Domains\PublicCatalog\Infrastructure\Http\Resources\PublicBusinessPageResource;
use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponder;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

final class PublicBusinessPageController extends Controller
{
    public function show(
        Request $request,
        string $slug,
        ShowPublicBusinessPage $showPublicBusinessPage,
        ApiResponder $responder,
    ): Response {
        try {
            $response = $showPublicBusinessPage->handle(new ShowPublicBusinessPageInput($slug));

            if ($response->failed()) {
                return $responder->failure($response->error(), $response->warnings());
            }

            return $responder->success(
                $response,
                PublicBusinessPageResource::make($response->value()),
                Response::HTTP_OK,
            );
        } catch (Throwable $unexpected) {
            return $responder->unexpected($request, $unexpected);
        }
    }
}

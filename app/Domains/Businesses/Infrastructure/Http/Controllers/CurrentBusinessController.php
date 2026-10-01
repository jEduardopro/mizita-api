<?php

declare(strict_types=1);

namespace App\Domains\Businesses\Infrastructure\Http\Controllers;

use App\Domains\Businesses\Application\Dtos\SelectCurrentBusinessInput;
use App\Domains\Businesses\Application\UseCases\SelectCurrentBusiness;
use App\Domains\Businesses\Infrastructure\Http\Requests\SelectCurrentBusinessRequest;
use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponder;
use App\Models\User;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

final class CurrentBusinessController extends Controller
{
    public function update(
        SelectCurrentBusinessRequest $request,
        SelectCurrentBusiness $selectCurrentBusiness,
        ApiResponder $responder,
    ): Response {
        /** @var User $account */
        $account = $request->user();

        try {
            $response = $selectCurrentBusiness->handle(
                SelectCurrentBusinessInput::fromRequest($request->validated(), $account->uuid),
            );

            if ($response->failed()) {
                return $responder->failure($response->error(), $response->warnings());
            }

            return response()->noContent();
        } catch (Throwable $unexpected) {
            return $responder->unexpected($request, $unexpected);
        }
    }
}

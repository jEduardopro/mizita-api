<?php

declare(strict_types=1);

namespace App\Domains\Notifications\Infrastructure\Http\Controllers;

use App\Domains\Notifications\Application\Dtos\CountUnreadStaffNotificationsInput;
use App\Domains\Notifications\Application\Dtos\ListStaffNotificationsInput;
use App\Domains\Notifications\Application\Dtos\MarkStaffNotificationAsReadInput;
use App\Domains\Notifications\Application\Dtos\ShowStaffNotificationInput;
use App\Domains\Notifications\Application\UseCases\CountUnreadStaffNotifications;
use App\Domains\Notifications\Application\UseCases\ListStaffNotifications;
use App\Domains\Notifications\Application\UseCases\MarkStaffNotificationAsRead;
use App\Domains\Notifications\Application\UseCases\ShowStaffNotification;
use App\Domains\Notifications\Infrastructure\Http\Requests\CountUnreadStaffNotificationsRequest;
use App\Domains\Notifications\Infrastructure\Http\Requests\ListStaffNotificationsRequest;
use App\Domains\Notifications\Infrastructure\Http\Resources\StaffNotificationResource;
use App\Domains\Notifications\Infrastructure\Http\Resources\UnreadNotificationCountResource;
use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponder;
use App\Http\Responses\PaginatedCollection;
use App\Models\User;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

final class StaffNotificationController extends Controller
{
    public function index(
        ListStaffNotificationsRequest $request,
        ListStaffNotifications $listStaffNotifications,
        ApiResponder $responder,
    ): Response {
        /** @var User $reader */
        $reader = $request->user();

        try {
            $response = $listStaffNotifications->handle(
                ListStaffNotificationsInput::fromRequest($request->validated(), $reader->uuid),
            );

            if ($response->failed()) {
                return $responder->failure($response->error(), $response->warnings());
            }

            return $responder->success(
                $response,
                PaginatedCollection::of($response->value(), StaffNotificationResource::class),
                Response::HTTP_OK,
            );
        } catch (Throwable $unexpected) {
            return $responder->unexpected($request, $unexpected);
        }
    }

    public function unreadCount(
        CountUnreadStaffNotificationsRequest $request,
        CountUnreadStaffNotifications $countUnreadStaffNotifications,
        ApiResponder $responder,
    ): Response {
        /** @var User $reader */
        $reader = $request->user();

        try {
            $response = $countUnreadStaffNotifications->handle(
                CountUnreadStaffNotificationsInput::fromRequest($request->validated(), $reader->uuid),
            );

            if ($response->failed()) {
                return $responder->failure($response->error(), $response->warnings());
            }

            return $responder->success(
                $response,
                UnreadNotificationCountResource::make($response->value()),
                Response::HTTP_OK,
            );
        } catch (Throwable $unexpected) {
            return $responder->unexpected($request, $unexpected);
        }
    }

    public function show(
        Request $request,
        string $notification,
        ShowStaffNotification $showStaffNotification,
        ApiResponder $responder,
    ): Response {
        /** @var User $reader */
        $reader = $request->user();

        try {
            $response = $showStaffNotification->handle(new ShowStaffNotificationInput($reader->uuid, $notification));

            if ($response->failed()) {
                return $responder->failure($response->error(), $response->warnings());
            }

            return $responder->success(
                $response,
                StaffNotificationResource::make($response->value()),
                Response::HTTP_OK,
            );
        } catch (Throwable $unexpected) {
            return $responder->unexpected($request, $unexpected);
        }
    }

    public function markAsRead(
        Request $request,
        string $notification,
        MarkStaffNotificationAsRead $markStaffNotificationAsRead,
        ApiResponder $responder,
    ): Response {
        /** @var User $reader */
        $reader = $request->user();

        try {
            $response = $markStaffNotificationAsRead->handle(
                new MarkStaffNotificationAsReadInput($reader->uuid, $notification),
            );

            if ($response->failed()) {
                return $responder->failure($response->error(), $response->warnings());
            }

            return $responder->success(
                $response,
                StaffNotificationResource::make($response->value()),
                Response::HTTP_OK,
            );
        } catch (Throwable $unexpected) {
            return $responder->unexpected($request, $unexpected);
        }
    }
}

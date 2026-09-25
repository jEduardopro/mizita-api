<?php

declare(strict_types=1);

namespace App\Domains\Staff\Infrastructure\Http\Controllers;

use App\Domains\Staff\Application\Dtos\InviteTeamMembersInput;
use App\Domains\Staff\Application\Dtos\ListTeamMembersInput;
use App\Domains\Staff\Application\Dtos\RemoveTeamMemberInput;
use App\Domains\Staff\Application\Dtos\ShowTeamMemberInput;
use App\Domains\Staff\Application\Dtos\UpdateTeamMemberInput;
use App\Domains\Staff\Application\UseCases\InviteTeamMembers;
use App\Domains\Staff\Application\UseCases\ListTeamMembers;
use App\Domains\Staff\Application\UseCases\RemoveTeamMember;
use App\Domains\Staff\Application\UseCases\ShowTeamMember;
use App\Domains\Staff\Application\UseCases\UpdateTeamMember;
use App\Domains\Staff\Infrastructure\Http\Requests\InviteTeamMembersRequest;
use App\Domains\Staff\Infrastructure\Http\Requests\ListTeamMembersRequest;
use App\Domains\Staff\Infrastructure\Http\Requests\UpdateTeamMemberRequest;
use App\Domains\Staff\Infrastructure\Http\Resources\TeamMemberResource;
use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponder;
use App\Http\Responses\PaginatedCollection;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

final class TeamMemberController extends Controller
{
    public function index(
        ListTeamMembersRequest $request,
        ListTeamMembers $listTeamMembers,
        ApiResponder $responder,
    ): Response {
        try {
            $response = $listTeamMembers->handle(ListTeamMembersInput::fromRequest($request->validated()));

            if ($response->failed()) {
                return $responder->failure($response->error(), $response->warnings());
            }

            return $responder->success(
                $response,
                PaginatedCollection::of($response->value(), TeamMemberResource::class),
                Response::HTTP_OK,
            );
        } catch (Throwable $unexpected) {
            return $responder->unexpected($request, $unexpected);
        }
    }

    public function store(
        InviteTeamMembersRequest $request,
        InviteTeamMembers $inviteTeamMembers,
        ApiResponder $responder,
    ): Response {
        try {
            $response = $inviteTeamMembers->handle(InviteTeamMembersInput::fromRequest($request->validated()));

            if ($response->failed()) {
                return $responder->failure($response->error(), $response->warnings());
            }

            return $responder->success(
                $response,
                TeamMemberResource::collection($response->value()),
                Response::HTTP_CREATED,
            );
        } catch (Throwable $unexpected) {
            return $responder->unexpected($request, $unexpected);
        }
    }

    public function show(
        Request $request,
        string $staffMember,
        ShowTeamMember $showTeamMember,
        ApiResponder $responder,
    ): Response {
        try {
            $response = $showTeamMember->handle(new ShowTeamMemberInput($staffMember));

            if ($response->failed()) {
                return $responder->failure($response->error(), $response->warnings());
            }

            return $responder->success(
                $response,
                TeamMemberResource::make($response->value()),
                Response::HTTP_OK,
            );
        } catch (Throwable $unexpected) {
            return $responder->unexpected($request, $unexpected);
        }
    }

    public function update(
        UpdateTeamMemberRequest $request,
        string $staffMember,
        UpdateTeamMember $updateTeamMember,
        ApiResponder $responder,
    ): Response {
        try {
            $response = $updateTeamMember->handle(
                UpdateTeamMemberInput::fromRequest($request->validated(), $staffMember),
            );

            if ($response->failed()) {
                return $responder->failure($response->error(), $response->warnings());
            }

            return $responder->success(
                $response,
                TeamMemberResource::make($response->value()),
                Response::HTTP_OK,
            );
        } catch (Throwable $unexpected) {
            return $responder->unexpected($request, $unexpected);
        }
    }

    public function destroy(
        Request $request,
        string $staffMember,
        RemoveTeamMember $removeTeamMember,
        ApiResponder $responder,
    ): Response {
        try {
            $response = $removeTeamMember->handle(new RemoveTeamMemberInput($staffMember));

            if ($response->failed()) {
                return $responder->failure($response->error(), $response->warnings());
            }

            return response()->noContent();
        } catch (Throwable $unexpected) {
            return $responder->unexpected($request, $unexpected);
        }
    }
}

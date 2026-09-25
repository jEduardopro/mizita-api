<?php

declare(strict_types=1);

namespace App\Domains\Staff\Application\UseCases;

use App\Domains\Staff\Application\Dtos\SendTeamInvitationInput;
use App\Domains\Staff\Contracts\BusinessDirectory;
use App\Domains\Staff\Contracts\TeamInvitationMailer;
use App\Domains\Staff\ValueObjects\TeamInvitation;
use App\Shared\Application\UseCaseResponse;

final class SendTeamInvitation
{
    public function __construct(
        private readonly BusinessDirectory $businesses,
        private readonly TeamInvitationMailer $mailer,
    ) {}

    /**
     * @return UseCaseResponse<null>
     */
    public function handle(SendTeamInvitationInput $input): UseCaseResponse
    {
        $this->mailer->send(new TeamInvitation(
            accountId: $input->accountId,
            businessName: $this->businesses->nameOf($input->businessId),
            temporaryPassword: $input->temporaryPassword,
        ));

        return UseCaseResponse::success();
    }
}

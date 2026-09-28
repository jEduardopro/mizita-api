<?php

declare(strict_types=1);

namespace App\Domains\Integrations\Application\UseCases;

use App\Domains\Integrations\Application\Dtos\ResumeBusinessCalendarSyncInput;
use App\Domains\Integrations\Contracts\CalendarBackfillQueue;
use App\Domains\Integrations\Contracts\CalendarConnectionRepository;
use App\Shared\Application\UseCaseResponse;

final class ResumeBusinessCalendarSync
{
    public function __construct(
        private readonly CalendarConnectionRepository $connections,
        private readonly CalendarBackfillQueue $backfills,
    ) {}

    /**
     * @return UseCaseResponse<int>
     */
    public function handle(ResumeBusinessCalendarSyncInput $input): UseCaseResponse
    {
        $connections = $this->connections->liveInBusiness($input->businessId);

        foreach ($connections as $connection) {
            $this->backfills->schedule($connection->businessId, $connection->id);
        }

        return UseCaseResponse::success(count($connections));
    }
}

<?php

declare(strict_types=1);

namespace App\Domains\BookingPages\Infrastructure\Eloquent;

use App\Domains\BookingPages\Contracts\BookingPageRepository;
use App\Domains\BookingPages\Entities\BookingPage;
use App\Domains\BookingPages\Exceptions\BookingPageNotFound;
use App\Domains\BookingPages\Infrastructure\Eloquent\Mappers\BookingPageMapper;
use App\Domains\BookingPages\Infrastructure\Eloquent\Models\BookingPageModel;
use App\Shared\Contracts\BusinessTeamKey;
use Illuminate\Database\Eloquent\Builder;

final class EloquentBookingPageRepository implements BookingPageRepository
{
    public function __construct(
        private readonly BookingPageMapper $mapper,
        private readonly BusinessTeamKey $teamKeys,
    ) {}

    public function findForBusiness(string $businessId): ?BookingPage
    {
        $model = $this->ofBusinessKey($this->teamKeys->teamKeyFor($businessId))->first();

        if ($model === null) {
            return null;
        }

        return $this->mapper->toEntity($model, $businessId);
    }

    public function ofBusiness(string $businessId): BookingPage
    {
        $page = $this->findForBusiness($businessId);

        if ($page === null) {
            throw BookingPageNotFound::forBusiness($businessId);
        }

        return $page;
    }

    public function save(BookingPage $page): void
    {
        BookingPageModel::query()->updateOrCreate(
            ['uuid' => $page->id],
            $this->mapper->toAttributes($page, $this->teamKeys->teamKeyFor($page->businessId)),
        );
    }

    /**
     * @return Builder<BookingPageModel>
     */
    private function ofBusinessKey(int $businessKey): Builder
    {
        return BookingPageModel::query()->where('business_id', $businessKey);
    }
}

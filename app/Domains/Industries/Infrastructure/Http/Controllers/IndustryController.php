<?php

declare(strict_types=1);

namespace App\Domains\Industries\Infrastructure\Http\Controllers;

use App\Domains\Industries\Application\UseCases\ListIndustries;
use App\Domains\Industries\Infrastructure\Http\Resources\IndustryResource;
use App\Http\Controllers\Controller;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

final class IndustryController extends Controller
{
    public function index(ListIndustries $listIndustries): AnonymousResourceCollection
    {
        return IndustryResource::collection($listIndustries->handle());
    }
}

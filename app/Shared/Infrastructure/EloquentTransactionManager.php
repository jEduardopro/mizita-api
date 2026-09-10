<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure;

use App\Shared\Contracts\TransactionManager;
use Illuminate\Support\Facades\DB;

final class EloquentTransactionManager implements TransactionManager
{
    /**
     * @template TReturn
     *
     * @param  callable(): TReturn  $work
     * @return TReturn
     */
    public function run(callable $work): mixed
    {
        return DB::transaction($work);
    }
}

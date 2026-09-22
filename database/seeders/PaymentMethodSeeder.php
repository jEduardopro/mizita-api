<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Domains\Payments\Infrastructure\Eloquent\Models\PaymentMethodModel;
use App\Domains\Payments\ValueObjects\PaymentMethodCode;
use App\Shared\Contracts\IdGenerator;
use Illuminate\Database\Seeder;

final class PaymentMethodSeeder extends Seeder
{
    public function __construct(
        private readonly IdGenerator $ids,
    ) {}

    public function run(): void
    {
        foreach (PaymentMethodCode::cases() as $position => $code) {
            $this->upsert($code, $position);
        }
    }

    private function upsert(PaymentMethodCode $code, int $position): void
    {
        $method = PaymentMethodModel::query()->firstOrNew(['code' => $code->value]);

        $method->uuid ??= $this->ids->next();
        $method->position = $position;
        $method->active = true;
        $method->requires_integration = false;

        $method->save();
    }
}

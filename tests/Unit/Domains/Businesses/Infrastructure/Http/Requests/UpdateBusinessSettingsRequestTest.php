<?php

declare(strict_types=1);

use App\Domains\Businesses\Application\Dtos\ScheduleInput;
use App\Domains\Businesses\Infrastructure\Http\Requests\UpdateBusinessSettingsRequest;
use Illuminate\Translation\ArrayLoader;
use Illuminate\Translation\Translator;
use Illuminate\Validation\Factory;

/**
 * @param  array<string, mixed>  $payload
 * @return array<string, array<string, mixed>>
 */
function businessSettingsFailures(array $payload): array
{
    $validator = (new Factory(new Translator(new ArrayLoader, 'en')))->make($payload, (new UpdateBusinessSettingsRequest)->rules());
    $validator->passes();

    return $validator->failed();
}

/**
 * @return list<array{weekday: int, starts_at: string, ends_at: string}>
 */
function submittedBusinessWeekOf(int $entries): array
{
    return array_fill(0, $entries, ['weekday' => 1, 'starts_at' => '09:00', 'ends_at' => '10:00']);
}

it('lets through a schedule holding exactly as many intervals as the input accepts', function () {
    expect(businessSettingsFailures(['schedule' => submittedBusinessWeekOf(ScheduleInput::MAXIMUM_ENTRIES)]))->toBe([]);
});

it('refuses a schedule one interval past the cap, on the schedule field', function () {
    expect(array_keys(businessSettingsFailures(['schedule' => submittedBusinessWeekOf(ScheduleInput::MAXIMUM_ENTRIES + 1)])['schedule'] ?? []))
        ->toBe(['Max']);
});

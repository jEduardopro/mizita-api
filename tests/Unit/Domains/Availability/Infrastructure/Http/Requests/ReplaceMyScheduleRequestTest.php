<?php

declare(strict_types=1);

use App\Domains\Availability\Application\Dtos\ReplaceMyScheduleInput;
use App\Domains\Availability\Infrastructure\Http\Requests\ReplaceMyScheduleRequest;
use Illuminate\Translation\ArrayLoader;
use Illuminate\Translation\Translator;
use Illuminate\Validation\Factory;

/**
 * @param  array<string, mixed>  $payload
 * @return array<string, array<string, mixed>>
 */
function replaceMyScheduleFailures(array $payload): array
{
    $validator = (new Factory(new Translator(new ArrayLoader, 'en')))->make($payload, (new ReplaceMyScheduleRequest)->rules());
    $validator->passes();

    return $validator->failed();
}

/**
 * @return list<array{weekday: int, starts_at: string, ends_at: string}>
 */
function requestedWeekOf(int $intervals): array
{
    return array_fill(0, $intervals, ['weekday' => 1, 'starts_at' => '09:00', 'ends_at' => '10:00']);
}

it('lets through a week holding exactly as many intervals as the input accepts', function () {
    expect(replaceMyScheduleFailures(['schedule' => requestedWeekOf(ReplaceMyScheduleInput::MAXIMUM_INTERVALS)]))->toBe([]);
});

it('refuses a week one interval past the cap, on the schedule field', function () {
    expect(replaceMyScheduleFailures(['schedule' => requestedWeekOf(ReplaceMyScheduleInput::MAXIMUM_INTERVALS + 1)]))
        ->toHaveKey('schedule')
        ->and(array_keys(replaceMyScheduleFailures(['schedule' => requestedWeekOf(71)])['schedule']))->toBe(['Max']);
});

it('lets through an empty week', function () {
    expect(replaceMyScheduleFailures(['schedule' => []]))->toBe([]);
});

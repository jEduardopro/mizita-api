<?php

declare(strict_types=1);

use App\Domains\Integrations\ValueObjects\CalendarTokens;

beforeEach(function () {
    $this->tokens = new CalendarTokens(
        accessToken: 'ya29.access-secret',
        refreshToken: '1//refresh-secret',
        accessTokenExpiresAt: new DateTimeImmutable('2026-03-10T10:00:00+00:00'),
    );
});

it('keeps both secrets out of a dump', function (Closure $dump) {
    $dumped = $dump($this->tokens);

    expect($dumped)->not->toContain('ya29.access-secret')
        ->and($dumped)->not->toContain('1//refresh-secret');
})->with([
    'print_r' => [fn (CalendarTokens $tokens) => print_r($tokens, true)],
    'var_dump' => [function (CalendarTokens $tokens) {
        ob_start();
        var_dump($tokens);

        return (string) ob_get_clean();
    }],
]);

it('still shows when the access token expires in a dump', function () {
    expect(print_r($this->tokens, true))->toContain('2026-03-10T10:00:00+00:00');
});

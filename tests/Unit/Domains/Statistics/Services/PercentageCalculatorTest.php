<?php

declare(strict_types=1);

use App\Domains\Statistics\Services\PercentageCalculator;

beforeEach(function () {
    $this->percentages = new PercentageCalculator;
});

describe('a share of a whole', function () {
    it('is the part over the whole as a percentage rounded to two decimals', function (int $part, int $whole, float $share) {
        expect($this->percentages->shareOf($part, $whole))->toBe($share);
    })->with([
        'the whole' => [10000, 10000, 100.0],
        'nothing of it' => [0, 10000, 0.0],
        'a round share' => [7060, 10000, 70.6],
        'a third, rounded down' => [1, 3, 33.33],
        'two thirds, rounded up' => [2, 3, 66.67],
        'a single cent of a large whole' => [1, 1000000, 0.0],
        'half a hundredth, rounded half up' => [1, 20000, 0.01],
        'a part larger than the net whole' => [12000, 10000, 120.0],
        'a part that nets negative' => [-2000, 10000, -20.0],
    ]);

    it('is zero when there is no whole to share, instead of dividing by zero', function (int $part) {
        expect($this->percentages->shareOf($part, 0))->toBe(0.0);
    })->with([
        'nothing of nothing' => 0,
        'something of nothing' => 500,
        'a negative part of nothing' => -500,
    ]);
});

describe('the change between two periods', function () {
    it('is the growth over the previous period as a percentage rounded to two decimals', function (int $previous, int $current, float $change) {
        expect($this->percentages->changeBetween($previous, $current))->toBe($change);
    })->with([
        'no change' => [10000, 10000, 0.0],
        'half again' => [10000, 15000, 50.0],
        'down by half' => [10000, 5000, -50.0],
        'down to nothing' => [10000, 0, -100.0],
        'doubled' => [10000, 20000, 100.0],
        'a small drop, rounded' => [30000, 28300, -5.67],
        'a third up, rounded' => [3, 4, 33.33],
    ]);

    it('is null when the previous period collected nothing', function (int $current) {
        expect($this->percentages->changeBetween(0, $current))->toBeNull();
    })->with([
        'nothing now either' => 0,
        'something now' => 5000,
        'a net refund now' => -5000,
    ]);

    it('reads an improvement from a net refund period as growth', function (int $current, float $change) {
        expect($this->percentages->changeBetween(-1000, $current))->toBe($change);
    })->with([
        'back into positive' => [1000, 200.0],
        'a shallower net refund' => [-500, 50.0],
    ]);

    it('reads a deeper net refund than before as a decline', function () {
        expect($this->percentages->changeBetween(-1000, -2000))->toBe(-100.0);
    });
});

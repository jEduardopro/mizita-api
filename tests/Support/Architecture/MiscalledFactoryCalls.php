<?php

declare(strict_types=1);

namespace Tests\Support\Architecture;

final class MiscalledFactoryCalls
{
    public static function passesEveryRequiredArgument(): ArityFixtureFailure
    {
        return ArityFixtureFailure::withBounds(101, 100);
    }

    public static function omitsTheOptionalArgument(): ArityFixtureFailure
    {
        return ArityFixtureFailure::withOptional(101);
    }

    public static function wrapsAnArgumentInAnotherCall(): ArityFixtureFailure
    {
        return ArityFixtureFailure::withBounds(max(101, 0), 100);
    }

    public static function forgetsARequiredArgument(): ArityFixtureFailure
    {
        return ArityFixtureFailure::withBounds(100);
    }

    public static function passesNothingAtAll(): ArityFixtureFailure
    {
        return ArityFixtureFailure::withOne();
    }

    public static function passesOneArgumentTooMany(): ArityFixtureFailure
    {
        return ArityFixtureFailure::withOne(101, 100);
    }

    public static function spreadsTheArgumentsOverSeveralLines(): ArityFixtureFailure
    {
        return ArityFixtureFailure::withBounds(
            101,
            100,
        );
    }

    public static function namesTheFactoryInsideAString(): string
    {
        return 'ArityFixtureFailure::withBounds() wants both the amount and the maximum';
    }
}

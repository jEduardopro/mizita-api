import { useTranslation } from 'react-i18next';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';

/**
 * A still of a working day: hour lines, two booked blocks and the buffers that
 * follow them. It is illustrative, not live data — the shapes are the product's
 * own vocabulary rather than a decorative graphic.
 */

/** Minutes from midnight. The column runs 09:00 to 13:00. */
const DAY_STARTS_AT = 9 * 60;
const DAY_ENDS_AT = 13 * 60;
const MINUTES_PER_TICK = 30;

/** A 30-minute row is 40px tall. */
const PIXELS_PER_MINUTE = 40 / MINUTES_PER_TICK;

/** The gap the illustration points at, in minutes. */
const OPEN_GAP_MINUTES = 30;
const OPEN_GAP_STARTS_AT = 10 * 60 + 30;

/**
 * The example is set in one business's zone, which is also what the header
 * shows. A timezone identifier is data, not copy, so it is not translated.
 */
const EXAMPLE_TIMEZONE = 'Europe/Madrid';

/**
 * A real Tuesday, so the weekday name comes from `Intl` in whatever language is
 * active instead of being a copy key that could drift from the times beside it.
 * Pinned to a fixed date so the illustration never changes under the reader.
 */
const EXAMPLE_DAY = Date.UTC(2026, 0, 6);

type Block = {
    /** The key of this service's name in the `public` namespace. */
    service: 'cutAndFinish' | 'colour';
    staff: string;
    startsAt: number;
    durationMinutes: number;
    bufferMinutes: number;
    /** The one filled block, so the column has a single focal point. */
    filled?: boolean;
};

const blocks: Block[] = [
    {
        service: 'cutAndFinish',
        staff: 'Ana',
        startsAt: 9 * 60 + 30,
        durationMinutes: 45,
        bufferMinutes: 15,
        filled: true,
    },
    {
        service: 'colour',
        staff: 'Leo',
        startsAt: 11 * 60,
        durationMinutes: 90,
        bufferMinutes: 15,
    },
];

const ticks = Array.from(
    { length: (DAY_ENDS_AT - DAY_STARTS_AT) / MINUTES_PER_TICK + 1 },
    (_, index) => DAY_STARTS_AT + index * MINUTES_PER_TICK,
);

/**
 * Weekday names are lower case in Spanish and capitalised in English. This one
 * heads a column, so it is title-cased either way, using the locale's own
 * casing rules rather than ASCII ones.
 */
function formatWeekday(locale: string): string {
    const weekday = new Intl.DateTimeFormat(locale, {
        weekday: 'long',
        timeZone: 'UTC',
    }).format(EXAMPLE_DAY);

    return weekday.charAt(0).toLocaleUpperCase(locale) + weekday.slice(1);
}

/**
 * An agenda column is a 24-hour grid by product decision, not by locale: the
 * hour labels have to stay narrow and line up with the rules behind them, and
 * "9:00 AM" would not. `Intl` still does the formatting so digits and separators
 * follow the language.
 */
function createTimeFormatter(locale: string): Intl.DateTimeFormat {
    return new Intl.DateTimeFormat(locale, {
        hour: '2-digit',
        minute: '2-digit',
        hourCycle: 'h23',
        timeZone: 'UTC',
    });
}

function offsetOf(minutes: number): number {
    return (minutes - DAY_STARTS_AT) * PIXELS_PER_MINUTE;
}

export function AgendaPreview() {
    const { t, i18n } = useTranslation('public');
    const timeFormatter = createTimeFormatter(i18n.language);

    return (
        <Card className="w-full">
            <CardHeader className="flex-row items-baseline justify-between gap-4">
                <CardTitle>{formatWeekday(i18n.language)}</CardTitle>
                <span className="text-[0.6875rem] tracking-[0.14em] text-muted-foreground uppercase">
                    {EXAMPLE_TIMEZONE}
                </span>
            </CardHeader>

            <CardContent>
                <div
                    className="relative"
                    style={{ height: offsetOf(DAY_ENDS_AT) }}
                    aria-hidden="true"
                >
                    {ticks.map((tick) => (
                        <div
                            key={tick}
                            className="absolute inset-x-0 flex -translate-y-1/2 items-center gap-3"
                            style={{ top: offsetOf(tick) }}
                        >
                            <span className="w-10 shrink-0 text-right text-[0.6875rem] leading-none tabular-nums text-muted-foreground">
                                {timeFormatter.format(EXAMPLE_DAY + tick * 60_000)}
                            </span>
                            <span className="h-px flex-1 bg-border" />
                        </div>
                    ))}

                    {blocks.map((block) => (
                        <div key={block.service}>
                            <div
                                className={
                                    block.filled
                                        ? 'absolute right-0 left-13 flex flex-col justify-center rounded-md bg-primary px-2.5 text-primary-foreground'
                                        : 'absolute right-0 left-13 flex flex-col justify-center rounded-md bg-card px-2.5 ring-1 ring-foreground/15'
                                }
                                style={{
                                    top: offsetOf(block.startsAt),
                                    height: block.durationMinutes * PIXELS_PER_MINUTE,
                                }}
                            >
                                <span className="text-xs font-medium">
                                    {t(`agendaPreview.services.${block.service}`)}
                                </span>
                                <span
                                    className={
                                        block.filled
                                            ? 'text-[0.6875rem] text-primary-foreground/70'
                                            : 'text-[0.6875rem] text-muted-foreground'
                                    }
                                >
                                    {t('agendaPreview.blockMeta', {
                                        staff: block.staff,
                                        minutes: block.durationMinutes,
                                    })}
                                </span>
                            </div>

                            <div
                                className="absolute right-0 left-13 flex items-center justify-end rounded-b-md border-x border-b border-dashed border-border bg-muted/50 pr-2 text-[0.625rem] tracking-[0.12em] text-muted-foreground uppercase"
                                style={{
                                    top:
                                        offsetOf(block.startsAt) +
                                        block.durationMinutes * PIXELS_PER_MINUTE,
                                    height: block.bufferMinutes * PIXELS_PER_MINUTE,
                                }}
                            >
                                {t('agendaPreview.buffer')}
                            </div>
                        </div>
                    ))}

                    <span
                        className="absolute left-13 text-[0.6875rem] text-muted-foreground"
                        style={{ top: offsetOf(OPEN_GAP_STARTS_AT) + 6 }}
                    >
                        {t('agendaPreview.openGap', { minutes: OPEN_GAP_MINUTES })}
                    </span>
                </div>

                <p className="mt-5 border-t border-border pt-4 text-xs leading-relaxed text-muted-foreground">
                    {t('agendaPreview.footnote')}
                </p>
            </CardContent>
        </Card>
    );
}

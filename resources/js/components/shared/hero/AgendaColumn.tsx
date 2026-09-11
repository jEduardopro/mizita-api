import { useTranslation } from 'react-i18next';

/**
 * A still of a working day: hour lines, two booked blocks and the buffers that
 * follow them. It is illustrative, not live data — the shapes are the product's
 * own vocabulary rather than a decorative graphic.
 *
 * It is drawn at the size of a phone screen, so the whole column is one set of
 * constants: the vertical geometry below is the only definition of how tall an
 * hour is, and nothing scales it afterwards. A transform would have softened the
 * hour rules, which are a single pixel each.
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
 * The first minute nothing is booked any more: the colour block ends at 12:30
 * and its buffer runs to 12:45. The collage's slot chip reads this, so the chip
 * and the column can never claim different things about the same day.
 */
export const FIRST_FREE_MINUTE = 12 * 60 + 45;

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

/** The width of the screen this column is drawn for. */
export const AGENDA_SCREEN_WIDTH = 194;

/**
 * Its height: the padding, the day header, the gap under it and the column
 * itself. Written down because the phone around it is sized from the content,
 * not the other way round.
 */
export const AGENDA_SCREEN_HEIGHT =
    12 + 14 + 10 + (DAY_ENDS_AT - DAY_STARTS_AT) * PIXELS_PER_MINUTE + 12;

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
 *
 * Exported so anything pointing at a time in this day — the collage's slot chip —
 * formats it exactly as the column does.
 */
export function formatAgendaTime(locale: string, minutes: number): string {
    return new Intl.DateTimeFormat(locale, {
        hour: '2-digit',
        minute: '2-digit',
        hourCycle: 'h23',
        timeZone: 'UTC',
    }).format(EXAMPLE_DAY + minutes * 60_000);
}

function offsetOf(minutes: number): number {
    return (minutes - DAY_STARTS_AT) * PIXELS_PER_MINUTE;
}

export function AgendaColumn() {
    const { t, i18n } = useTranslation('public');

    return (
        <div className="flex h-full flex-col px-3 py-3">
            <div className="flex h-3.5 items-baseline justify-between gap-2">
                <span className="text-[0.625rem] leading-none font-medium tracking-[-0.01em]">
                    {formatWeekday(i18n.language)}
                </span>
                <span className="text-[0.5625rem] leading-none tracking-[0.1em] text-muted-foreground uppercase">
                    {EXAMPLE_TIMEZONE}
                </span>
            </div>

            <div className="relative mt-2.5" style={{ height: offsetOf(DAY_ENDS_AT) }}>
                {ticks.map((tick) => (
                    <div
                        key={tick}
                        className="absolute inset-x-0 flex -translate-y-1/2 items-center gap-1.5"
                        style={{ top: offsetOf(tick) }}
                    >
                        <span className="w-8 shrink-0 text-right text-[0.5625rem] leading-none tabular-nums text-muted-foreground">
                            {formatAgendaTime(i18n.language, tick)}
                        </span>
                        <span className="h-px flex-1 bg-border" />
                    </div>
                ))}

                {blocks.map((block) => (
                    <div key={block.service}>
                        <div
                            className={
                                block.filled
                                    ? 'absolute right-0 left-9.5 flex flex-col justify-center rounded-md bg-primary px-2 text-primary-foreground'
                                    : 'absolute right-0 left-9.5 flex flex-col justify-center rounded-md bg-card px-2 ring-1 ring-foreground/15'
                            }
                            style={{
                                top: offsetOf(block.startsAt),
                                height: block.durationMinutes * PIXELS_PER_MINUTE,
                            }}
                        >
                            <span className="text-[0.625rem] leading-tight font-medium">
                                {t(`agendaPreview.services.${block.service}`)}
                            </span>
                            <span
                                className={
                                    block.filled
                                        ? 'text-[0.5625rem] leading-tight text-primary-foreground/70'
                                        : 'text-[0.5625rem] leading-tight text-muted-foreground'
                                }
                            >
                                {t('agendaPreview.blockMeta', {
                                    staff: block.staff,
                                    minutes: block.durationMinutes,
                                })}
                            </span>
                        </div>

                        <div
                            className="absolute right-0 left-9.5 flex items-center justify-end rounded-b-md border-x border-b border-dashed border-border bg-muted/50 pr-1.5 text-[0.5625rem] tracking-[0.1em] text-muted-foreground uppercase"
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
                    className="absolute left-9.5 text-[0.5625rem] text-muted-foreground"
                    style={{ top: offsetOf(OPEN_GAP_STARTS_AT) + 5 }}
                >
                    {t('agendaPreview.openGap', { minutes: OPEN_GAP_MINUTES })}
                </span>
            </div>
        </div>
    );
}

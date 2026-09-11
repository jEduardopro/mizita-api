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

type Block = {
    label: string;
    staff: string;
    startsAt: number;
    durationMinutes: number;
    bufferMinutes: number;
    /** The one filled block, so the column has a single focal point. */
    filled?: boolean;
};

const blocks: Block[] = [
    {
        label: 'Cut and finish',
        staff: 'Ana',
        startsAt: 9 * 60 + 30,
        durationMinutes: 45,
        bufferMinutes: 15,
        filled: true,
    },
    {
        label: 'Colour',
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

function formatTime(minutes: number): string {
    const hours = Math.floor(minutes / 60);
    const rest = minutes % 60;

    return `${String(hours).padStart(2, '0')}:${String(rest).padStart(2, '0')}`;
}

function offsetOf(minutes: number): number {
    return (minutes - DAY_STARTS_AT) * PIXELS_PER_MINUTE;
}

export function AgendaPreview() {
    return (
        <Card className="w-full">
            <CardHeader className="flex-row items-baseline justify-between gap-4">
                <CardTitle>Tuesday</CardTitle>
                <span className="text-[0.6875rem] tracking-[0.14em] text-muted-foreground uppercase">
                    Europe/Madrid
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
                                {formatTime(tick)}
                            </span>
                            <span className="h-px flex-1 bg-border" />
                        </div>
                    ))}

                    {blocks.map((block) => (
                        <div key={block.label}>
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
                                <span className="text-xs font-medium">{block.label}</span>
                                <span
                                    className={
                                        block.filled
                                            ? 'text-[0.6875rem] text-primary-foreground/70'
                                            : 'text-[0.6875rem] text-muted-foreground'
                                    }
                                >
                                    {block.staff} · {block.durationMinutes} min
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
                                Buffer
                            </div>
                        </div>
                    ))}

                    <span
                        className="absolute left-13 text-[0.6875rem] text-muted-foreground"
                        style={{ top: offsetOf(10 * 60 + 30) + 6 }}
                    >
                        30 min open
                    </span>
                </div>

                <p className="mt-5 border-t border-border pt-4 text-xs leading-relaxed text-muted-foreground">
                    What is bookable is what is left: business hours, narrowed to the staff
                    member's hours, minus time off, minus everything already booked.
                </p>
            </CardContent>
        </Card>
    );
}

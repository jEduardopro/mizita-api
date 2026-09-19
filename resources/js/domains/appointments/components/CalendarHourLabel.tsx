const MILLISECONDS_PER_HOUR = 3_600_000;

export type CalendarGridStep = {
    hour: number;
    minute: number;
};

type Props = {
    gridStep: CalendarGridStep;
    locale: string;
};

export function CalendarHourLabel({ gridStep, locale }: Props) {
    if (gridStep.minute !== 0) {
        return null;
    }

    const label = new Intl.DateTimeFormat(locale, {
        hour: 'numeric',
        hour12: true,
        timeZone: 'UTC',
    }).format(gridStep.hour * MILLISECONDS_PER_HOUR);

    return (
        <span className="absolute -top-[0.75em] right-full pr-2 whitespace-nowrap tabular-nums text-muted-foreground">
            {label}
        </span>
    );
}

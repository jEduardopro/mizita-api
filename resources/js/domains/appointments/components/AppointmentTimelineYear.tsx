type Props = {
    year: number;
};

export function AppointmentTimelineYear({ year }: Props) {
    return (
        <li className="col-span-2 flex items-center gap-3 py-1">
            <span aria-hidden="true" className="h-px flex-1 bg-border" />

            <span className="text-xs font-medium tracking-[0.18em] text-muted-foreground tabular-nums">
                {year}
            </span>

            <span aria-hidden="true" className="h-px flex-1 bg-border" />
        </li>
    );
}

const LEAF_FORMAT: Intl.DateTimeFormatOptions = {
    day: 'numeric',
    month: 'short',
    year: 'numeric',
};

type Props = {
    deadline: Date;
    locale: string;
};

export function DeletionDeadlineLeaf({ deadline, locale }: Props) {
    const parts = new Intl.DateTimeFormat(locale, LEAF_FORMAT).formatToParts(deadline);
    const partOf = (type: Intl.DateTimeFormatPartTypes) =>
        parts.find((part) => part.type === type)?.value ?? '';

    return (
        <div
            aria-hidden="true"
            className="grid w-16 shrink-0 overflow-hidden rounded-xl border border-destructive/25 bg-background text-center shadow-sm"
        >
            <span className="bg-destructive/10 py-1 text-[0.6875rem] font-semibold tracking-[0.12em] text-destructive uppercase">
                {partOf('month')}
            </span>
            <span className="pt-1.5 font-heading text-2xl leading-none font-semibold tracking-[-0.04em] tabular-nums">
                {partOf('day')}
            </span>
            <span className="pt-1 pb-1.5 text-[0.6875rem] text-muted-foreground tabular-nums">
                {partOf('year')}
            </span>
        </div>
    );
}

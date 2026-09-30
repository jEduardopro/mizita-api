type Props = {
    label: string;
    detail?: string;
    amount: string;
    caption: string;
    sharePercent: number;
};

const FULL_SHARE = 100;

const NO_SHARE = 0;

function barWidth(sharePercent: number): string {
    return `${Math.min(FULL_SHARE, Math.max(NO_SHARE, sharePercent))}%`;
}

export function ShareRow({ label, detail, amount, caption, sharePercent }: Props) {
    return (
        <li className="grid gap-2 rounded-lg bg-muted/50 p-3">
            <div className="flex items-start justify-between gap-3">
                <div className="grid min-w-0 gap-0.5">
                    <p className="truncate font-medium">{label}</p>

                    {detail === undefined ? null : (
                        <p className="truncate text-xs text-muted-foreground">{detail}</p>
                    )}
                </div>

                <p className="shrink-0 font-semibold tabular-nums">{amount}</p>
            </div>

            <div aria-hidden="true" className="h-1.5 overflow-hidden rounded-full bg-foreground/10">
                <div className="h-full rounded-full bg-success" style={{ width: barWidth(sharePercent) }} />
            </div>

            <p className="text-xs text-muted-foreground">{caption}</p>
        </li>
    );
}

import { cn } from 'cn';

export type ChargeAmountTone = 'muted' | 'strong';

const LABEL_TONES: Record<ChargeAmountTone, string> = {
    muted: 'text-muted-foreground',
    strong: 'font-medium text-foreground',
};

const VALUE_TONES: Record<ChargeAmountTone, string> = {
    muted: 'text-muted-foreground',
    strong: 'font-semibold text-foreground',
};

type Props = {
    label: string;
    value: string;
    tone?: ChargeAmountTone;
    error?: string;
};

export function ChargeAmountRow({ label, value, tone = 'muted', error }: Props) {
    return (
        <div className="grid gap-1">
            <div className="flex items-baseline justify-between gap-4 text-sm">
                <span className={LABEL_TONES[tone]}>{label}</span>

                <span className={cn('tabular-nums', VALUE_TONES[tone])}>{value}</span>
            </div>

            {error === undefined ? null : (
                <span className="text-xs text-destructive">{error}</span>
            )}
        </div>
    );
}

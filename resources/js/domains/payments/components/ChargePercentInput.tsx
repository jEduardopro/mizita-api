import { normalizePercentInput } from '@/components/form/amount-input';
import { Input } from '@/components/ui/input';

const PERCENT_AFFIX = '-%';

type Props = {
    id: string;
    label: string;
    value: string;
    onChange: (value: string) => void;
};

export function ChargePercentInput({ id, label, value, onChange }: Props) {
    return (
        <div className="relative w-24 sm:w-28">
            <span
                aria-hidden="true"
                className="pointer-events-none absolute top-1/2 left-2.5 -translate-y-1/2 text-sm text-muted-foreground"
            >
                {PERCENT_AFFIX}
            </span>

            <Input
                id={id}
                aria-label={label}
                type="text"
                inputMode="decimal"
                autoComplete="off"
                value={value}
                onChange={(event) => onChange(normalizePercentInput(event.target.value))}
                className="h-11 pl-8 text-right text-base tabular-nums md:h-9"
            />
        </div>
    );
}

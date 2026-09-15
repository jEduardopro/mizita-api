import { cn } from 'cn';
import type { ComponentProps } from 'react';
import { fieldMessage, FieldMessage } from '@/components/form/FieldMessage';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

const MAXIMUM_DECIMALS = 2;

type Props = Omit<ComponentProps<'input'>, 'value' | 'onChange' | 'type' | 'inputMode'> & {
    id: string;
    label: string;
    value: string;
    onChange: (value: string) => void;
    currencySymbol: string;
    error?: string;
    hint?: string;
};

function normalized(raw: string): string {
    const cleaned = raw.replace(',', '.').replace(/[^\d.]/g, '');
    const [whole, ...decimals] = cleaned.split('.');

    if (decimals.length === 0) {
        return whole;
    }

    return `${whole}.${decimals.join('').slice(0, MAXIMUM_DECIMALS)}`;
}

function padded(value: string): string {
    const [whole, decimals = ''] = value.split('.');

    if (whole === '') {
        return '';
    }

    return `${whole}.${decimals.padEnd(MAXIMUM_DECIMALS, '0')}`;
}

export function PriceField({
    id,
    label,
    value,
    onChange,
    currencySymbol,
    error,
    hint,
    className,
    ...props
}: Props) {
    const message = fieldMessage({ id, error, hint });

    return (
        <div className="grid gap-2">
            <Label htmlFor={id}>{label}</Label>

            <div className="relative">
                <span
                    aria-hidden="true"
                    className="pointer-events-none absolute top-1/2 left-3 -translate-y-1/2 text-sm text-muted-foreground"
                >
                    {currencySymbol}
                </span>

                <Input
                    id={id}
                    type="text"
                    inputMode="decimal"
                    autoComplete="off"
                    aria-invalid={!! error}
                    aria-describedby={message?.id}
                    {...props}
                    value={value}
                    onChange={(event) => onChange(normalized(event.target.value))}
                    onBlur={() => onChange(padded(value))}
                    className={cn('h-11 pl-7 text-base md:text-base', className)}
                />
            </div>

            <FieldMessage message={message} />
        </div>
    );
}

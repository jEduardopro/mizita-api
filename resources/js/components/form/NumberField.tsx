import { cn } from 'cn';
import type { ComponentProps, ReactNode } from 'react';
import { fieldMessage, FieldMessage } from '@/components/form/FieldMessage';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

export type NumberValue = number | '';

type Props = Omit<ComponentProps<'input'>, 'value' | 'onChange' | 'type' | 'inputMode'> & {
    id: string;
    label: string;
    value: NumberValue;
    onChange: (value: NumberValue) => void;
    suffix?: string;
    labelAdornment?: ReactNode;
    error?: string;
    hint?: string;
};

function parseDigits(raw: string): NumberValue {
    const digits = raw.replace(/\D/g, '');

    return digits === '' ? '' : Number.parseInt(digits, 10);
}

export function NumberField({
    id,
    label,
    value,
    onChange,
    suffix,
    labelAdornment,
    error,
    hint,
    className,
    ...props
}: Props) {
    const message = fieldMessage({ id, error, hint });

    return (
        <div className="grid gap-2">
            <div className="flex items-center gap-1.5">
                <Label htmlFor={id}>{label}</Label>
                {labelAdornment}
            </div>

            <div className="relative">
                <Input
                    id={id}
                    type="text"
                    inputMode="numeric"
                    autoComplete="off"
                    aria-invalid={!! error}
                    aria-describedby={message?.id}
                    {...props}
                    value={value === '' ? '' : String(value)}
                    onChange={(event) => onChange(parseDigits(event.target.value))}
                    className={cn('h-11 text-base md:text-base', suffix ? 'pr-16' : undefined, className)}
                />

                {suffix ? (
                    <span
                        aria-hidden="true"
                        className="pointer-events-none absolute top-1/2 right-3 -translate-y-1/2 text-sm text-muted-foreground"
                    >
                        {suffix}
                    </span>
                ) : null}
            </div>

            <FieldMessage message={message} />
        </div>
    );
}

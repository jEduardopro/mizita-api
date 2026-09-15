import { cn } from 'cn';
import { Check } from 'lucide-react';
import { fieldMessage, FieldMessage } from '@/components/form/FieldMessage';

export type ColorSwatch<TValue extends string> = {
    value: TValue;
    label: string;
    swatchClassName: string;
};

type Props<TValue extends string> = {
    id: string;
    label: string;
    options: readonly ColorSwatch<TValue>[];
    value: TValue;
    onChange: (value: TValue) => void;
    error?: string;
    hint?: string;
};

export function ColorSwatchField<TValue extends string>({
    id,
    label,
    options,
    value,
    onChange,
    error,
    hint,
}: Props<TValue>) {
    const message = fieldMessage({ id, error, hint });

    return (
        <fieldset className="grid gap-2">
            <legend className="mb-2 text-sm leading-none font-medium">{label}</legend>

            <div className="flex flex-wrap gap-1.5">
                {options.map((option) => (
                    <label key={option.value} className="relative inline-flex">
                        <input
                            type="radio"
                            name={id}
                            value={option.value}
                            checked={option.value === value}
                            onChange={() => onChange(option.value)}
                            aria-invalid={!! error}
                            aria-describedby={message?.id}
                            className="peer sr-only"
                        />

                        <span
                            aria-hidden="true"
                            className={cn(
                                'flex size-11 items-center justify-center rounded-full ring-offset-2 ring-offset-background md:size-9',
                                'peer-checked:ring-2 peer-checked:ring-foreground',
                                'peer-focus-visible:ring-3 peer-focus-visible:ring-ring/50',
                                'peer-checked:[&>svg]:opacity-100',
                                option.swatchClassName,
                            )}
                        >
                            <Check className="size-4 text-background opacity-0 motion-safe:transition-opacity" />
                        </span>

                        <span className="sr-only">{option.label}</span>
                    </label>
                ))}
            </div>

            <FieldMessage message={message} />
        </fieldset>
    );
}

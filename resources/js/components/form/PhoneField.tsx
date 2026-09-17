import { cn } from 'cn';
import { ChevronDown } from 'lucide-react';
import { fieldMessage, FieldMessage } from '@/components/form/FieldMessage';
import { Input } from '@/components/ui/input';

type PhoneCountry = {
    code: string;
    label: string;
};

type Props = {
    id: string;
    label: string;
    countryLabel: string;
    numberLabel: string;
    countries: readonly PhoneCountry[];
    country: string;
    onCountryChange: (code: string) => void;
    number: string;
    onNumberChange: (value: string) => void;
    error?: string;
    hint?: string;
};

export function PhoneField({
    id,
    label,
    countryLabel,
    numberLabel,
    countries,
    country,
    onCountryChange,
    number,
    onNumberChange,
    error,
    hint,
}: Props) {
    const labelId = `${id}-label`;
    const message = fieldMessage({ id, error, hint });

    return (
        <div role="group" aria-labelledby={labelId} className="grid gap-2">
            <span id={labelId} className="text-sm font-medium">
                {label}
            </span>

            <div className="flex flex-wrap items-end gap-2">
                <div className="relative shrink-0">
                    <select
                        id={`${id}-country`}
                        aria-label={countryLabel}
                        aria-invalid={!! error}
                        value={country}
                        onChange={(event) => onCountryChange(event.target.value)}
                        className={cn(
                            'h-11 w-[7.5rem] appearance-none rounded-lg border border-input bg-transparent py-1 pr-8 pl-2.5 text-base transition-colors outline-none sm:w-36',
                            'focus-visible:border-ring focus-visible:ring-3 focus-visible:ring-ring/50',
                            'aria-invalid:border-destructive aria-invalid:ring-3 aria-invalid:ring-destructive/20',
                            'dark:bg-input/30 dark:aria-invalid:border-destructive/50 dark:aria-invalid:ring-destructive/40',
                        )}
                    >
                        {countries.map((option) => (
                            <option key={option.code} value={option.code}>
                                {option.label}
                            </option>
                        ))}
                    </select>

                    <ChevronDown
                        aria-hidden="true"
                        className="pointer-events-none absolute top-1/2 right-2.5 size-4 -translate-y-1/2 text-muted-foreground"
                    />
                </div>

                <Input
                    id={id}
                    type="tel"
                    inputMode="tel"
                    autoComplete="tel-national"
                    aria-label={numberLabel}
                    aria-invalid={!! error}
                    aria-describedby={message?.id}
                    value={number}
                    onChange={(event) => onNumberChange(event.target.value)}
                    className="h-11 min-w-32 flex-1 text-base md:text-base"
                />
            </div>

            <FieldMessage message={message} />
        </div>
    );
}

import { cn } from 'cn';
import { ChevronDown } from 'lucide-react';
import { fieldMessage, FieldMessage } from '@/components/form/FieldMessage';
import { Input } from '@/components/ui/input';

/** A country as this field needs it: a code to store, and a name to show. */
type PhoneCountry = {
    code: string;
    /** Pre-translated, and already carrying the dial code, e.g. `Mexico (+52)`. */
    label: string;
};

type Props = {
    id: string;
    /** The name of the pair, pre-translated. */
    label: string;
    /** Pre-translated "Optional", shown beside the name. */
    optionalLabel: string;
    /** The accessible name of the country control. */
    countryLabel: string;
    /** The accessible name of the number control. */
    numberLabel: string;
    countries: readonly PhoneCountry[];
    country: string;
    onCountryChange: (code: string) => void;
    number: string;
    onNumberChange: (value: string) => void;
    error?: string;
    hint?: string;
};

/**
 * The prefix and the number, written as one field.
 *
 * They are a group with one visible name and two controls, which is one job — so
 * this is its own component rather than a select with its label switched off next
 * to an input with its label switched off. A boolean that hides a label is how
 * two controls end up sharing one name by accident.
 *
 * The countries arrive as a prop: which markets the product sells in is a
 * decision for the screen and the catalogue, never something a form control gets
 * to know. There is no mask and no `maxLength` either — how a phone number may be
 * written is a validation rule, and the FormRequest owns those.
 */
export function PhoneField({
    id,
    label,
    optionalLabel,
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
            <span id={labelId} className="flex items-baseline gap-1.5 text-sm font-medium">
                {label}
                <span className="text-xs font-normal text-muted-foreground">{optionalLabel}</span>
            </span>

            {/*
             * One row, always. Stacked, the prefix reads as a question of its own;
             * side by side it reads as the first part of the number — which is
             * what it is. `min-w-0` on the number is what keeps the pair inside a
             * 320px screen instead of pushing the page sideways.
             */}
            <div className="flex items-end gap-2">
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
                    className="h-11 min-w-0 flex-1 text-base md:text-base"
                />
            </div>

            <FieldMessage message={message} />
        </div>
    );
}

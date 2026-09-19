import { DialCodePicker, type DialCodeOption } from '@/components/form/DialCodePicker';
import { fieldMessage, FieldMessage } from '@/components/form/FieldMessage';
import { Input } from '@/components/ui/input';

type Props = {
    id: string;
    label: string;
    countryLabel: string;
    numberLabel: string;
    countries: readonly DialCodeOption[];
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
                <DialCodePicker
                    id={`${id}-country`}
                    label={countryLabel}
                    options={countries}
                    value={country}
                    onChange={onCountryChange}
                    invalid={!! error}
                />

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

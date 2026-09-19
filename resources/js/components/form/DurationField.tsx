import { cn } from 'cn';
import type { Duration, DurationUnit } from '@/components/form/duration-units';
import { CONTROL_DENSITY_CLASSES, useFormDensity } from '@/components/form/form-density';
import type { NumberValue } from '@/components/form/NumberField';
import { SelectControl } from '@/components/form/SelectControl';
import { Input } from '@/components/ui/input';

export type DurationUnitOption = {
    value: DurationUnit;
    label: string;
};

type Naming = { labelledBy: string; label?: never } | { label: string; labelledBy?: never };

type Props = Naming & {
    id: string;
    value: Duration;
    onChange: (value: Duration) => void;
    units: readonly DurationUnitOption[];
    unitLabel: string;
    maxLength?: number;
    invalid?: boolean;
    describedBy?: string;
};

function parseDigits(raw: string): NumberValue {
    const digits = raw.replace(/\D/g, '');

    return digits === '' ? '' : Number.parseInt(digits, 10);
}

export function DurationField({
    id,
    value,
    onChange,
    units,
    unitLabel,
    maxLength,
    invalid = false,
    describedBy,
    label,
    labelledBy,
}: Props) {
    const density = useFormDensity();

    function selectUnit(selected: string): void {
        const unit = units.find((option) => option.value === selected);

        if (unit !== undefined) {
            onChange({ ...value, unit: unit.value });
        }
    }

    return (
        <div
            role="group"
            aria-label={label}
            aria-labelledby={labelledBy}
            className="flex items-center gap-2"
        >
            <Input
                id={id}
                type="text"
                inputMode="numeric"
                autoComplete="off"
                maxLength={maxLength}
                aria-invalid={invalid}
                aria-describedby={describedBy}
                value={value.amount === '' ? '' : String(value.amount)}
                onChange={(event) => onChange({ ...value, amount: parseDigits(event.target.value) })}
                className={cn('min-w-0 flex-1', CONTROL_DENSITY_CLASSES[density])}
            />

            <div className="w-32 shrink-0">
                <SelectControl
                    id={`${id}-unit`}
                    aria-label={unitLabel}
                    aria-invalid={invalid}
                    options={units}
                    value={value.unit}
                    onChange={(event) => selectUnit(event.target.value)}
                />
            </div>
        </div>
    );
}

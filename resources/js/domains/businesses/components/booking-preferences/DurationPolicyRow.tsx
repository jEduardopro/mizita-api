import type { ReactNode } from 'react';
import type { Duration } from '@/components/form/duration-units';
import { DurationField, type DurationUnitOption } from '@/components/form/DurationField';
import { fieldMessage } from '@/components/form/FieldMessage';
import {
    SettingsFieldRow,
    settingsFieldLabelId,
} from '@/domains/businesses/components/settings/SettingsFieldRow';

const AMOUNT_MAX_LENGTH = 4;

type DurationRowProps = {
    id: string;
    label: string;
    helper: string;
    unitLabel: string;
    units: readonly DurationUnitOption[];
    value: Duration;
    onChange: (value: Duration) => void;
    error?: string;
    hint?: string;
    labelAdornment?: ReactNode;
    disabled?: boolean;
};

export function DurationPolicyRow({
    id,
    label,
    helper,
    unitLabel,
    units,
    value,
    onChange,
    error,
    hint,
    labelAdornment,
    disabled = false,
}: DurationRowProps) {
    const message = fieldMessage({ id, error, hint });

    return (
        <SettingsFieldRow
            htmlFor={id}
            label={label}
            helper={helper}
            labelAdornment={labelAdornment}
            message={message}
        >
            <fieldset disabled={disabled} className="min-w-0">
                <DurationField
                    id={id}
                    labelledBy={settingsFieldLabelId(id)}
                    value={value}
                    onChange={onChange}
                    units={units}
                    unitLabel={unitLabel}
                    maxLength={AMOUNT_MAX_LENGTH}
                    invalid={error !== undefined}
                    describedBy={message?.id}
                />
            </fieldset>
        </SettingsFieldRow>
    );
}

import { Info } from 'lucide-react';
import type { ReactNode } from 'react';
import { useTranslation } from 'react-i18next';
import type { Duration, DurationUnit } from '@/components/form/duration-units';
import { DurationField, type DurationUnitOption } from '@/components/form/DurationField';
import { fieldMessage } from '@/components/form/FieldMessage';
import { TEXTAREA_DENSITY_CLASSES, useFormDensity } from '@/components/form/form-density';
import { SelectControl } from '@/components/form/SelectControl';
import { Switch } from '@/components/ui/switch';
import { Textarea } from '@/components/ui/textarea';
import { Tooltip, TooltipContent, TooltipTrigger } from '@/components/ui/tooltip';
import {
    BOOKING_WINDOW_UNITS,
    CANCELLATION_WINDOW_LABEL_KEYS,
    CANCELLATION_WINDOWS,
    DURATION_UNIT_LABEL_KEYS,
    LEAD_TIME_UNITS,
    SLOT_SIZE_UNITS,
} from './booking-policy-options';
import {
    SettingsFieldRow,
    settingsFieldLabelId,
} from '@/domains/businesses/components/settings/SettingsFieldRow';
import { SettingsSection } from '@/domains/businesses/components/settings/SettingsSection';
import { BOOKING_PREFERENCES_SECTION_IDS } from './booking-preferences-values';
import type { BookingPreferencesFormController } from './use-booking-preferences-form';

const LEAD_TIME_ID = 'business-lead-time';

const BOOKING_WINDOW_ID = 'business-booking-window';

const SLOT_SIZE_ID = 'business-slot-size';

const CANCELLATION_ID = 'business-cancellation-window';

const POLICY_MESSAGE_ID = 'business-policy-message';

const POLICY_DISPLAY_ID = 'business-policy-display';

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
};

function DurationPolicyRow({
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
        </SettingsFieldRow>
    );
}

type Props = {
    form: BookingPreferencesFormController;
};

export function BookingPolicySection({ form }: Props) {
    const { t } = useTranslation('admin');
    const density = useFormDensity();

    function unitOptions(units: readonly DurationUnit[]): DurationUnitOption[] {
        return units.map((unit) => ({ value: unit, label: t(DURATION_UNIT_LABEL_KEYS[unit]) }));
    }

    function selectCancellationWindow(selected: string): void {
        const choice = CANCELLATION_WINDOWS.find((candidate) => candidate === selected);

        if (choice !== undefined) {
            form.update('cancellationWindow', choice);
        }
    }

    const cancellationMessage = fieldMessage({
        id: CANCELLATION_ID,
        error: form.errorFor('cancellationWindow'),
    });

    const policyMessageState = fieldMessage({
        id: POLICY_MESSAGE_ID,
        error: form.errorFor('policyMessage'),
    });

    const displayMessage = fieldMessage({
        id: POLICY_DISPLAY_ID,
        error: form.errorFor('displayPolicyOnBookingPage'),
    });

    const cancellationOptions = CANCELLATION_WINDOWS.map((choice) => ({
        value: choice,
        label: t(CANCELLATION_WINDOW_LABEL_KEYS[choice]),
    }));

    return (
        <SettingsSection
            id={BOOKING_PREFERENCES_SECTION_IDS.policy}
            title={t('bookingPreferences.policy.title')}
            description={t('bookingPreferences.policy.description')}
        >
            <DurationPolicyRow
                id={LEAD_TIME_ID}
                label={t('bookingPreferences.policy.leadTime.label')}
                helper={t('bookingPreferences.policy.leadTime.helper')}
                unitLabel={t('bookingPreferences.policy.leadTime.unit')}
                units={unitOptions(LEAD_TIME_UNITS)}
                value={form.values.leadTime}
                onChange={(value) => form.update('leadTime', value)}
                error={form.errorFor('leadTime')}
            />

            <DurationPolicyRow
                id={BOOKING_WINDOW_ID}
                label={t('bookingPreferences.policy.bookingWindow.label')}
                helper={t('bookingPreferences.policy.bookingWindow.helper')}
                unitLabel={t('bookingPreferences.policy.bookingWindow.unit')}
                units={unitOptions(BOOKING_WINDOW_UNITS)}
                value={form.values.bookingWindow}
                onChange={(value) => form.update('bookingWindow', value)}
                hint={t('bookingPreferences.policy.bookingWindow.hint')}
                error={form.errorFor('bookingWindow')}
            />

            <DurationPolicyRow
                id={SLOT_SIZE_ID}
                label={t('bookingPreferences.policy.slotSize.label')}
                helper={t('bookingPreferences.policy.slotSize.helper')}
                unitLabel={t('bookingPreferences.policy.slotSize.unit')}
                units={unitOptions(SLOT_SIZE_UNITS)}
                value={form.values.slotSize}
                onChange={(value) => form.update('slotSize', value)}
                hint={t('bookingPreferences.policy.slotSize.hint')}
                error={form.errorFor('slotSize')}
                labelAdornment={
                    <Tooltip>
                        <TooltipTrigger asChild>
                            <button
                                type="button"
                                aria-label={t('bookingPreferences.policy.slotSize.help')}
                                className="inline-flex size-6 items-center justify-center rounded-full text-muted-foreground outline-none hover:text-foreground focus-visible:ring-3 focus-visible:ring-ring/50"
                            >
                                <Info aria-hidden="true" className="size-4" />
                            </button>
                        </TooltipTrigger>

                        <TooltipContent>
                            {t('bookingPreferences.policy.slotSize.tooltip')}
                        </TooltipContent>
                    </Tooltip>
                }
            />

            <SettingsFieldRow
                htmlFor={CANCELLATION_ID}
                label={t('bookingPreferences.policy.cancellation.label')}
                helper={t('bookingPreferences.policy.cancellation.helper')}
                message={cancellationMessage}
            >
                <SelectControl
                    id={CANCELLATION_ID}
                    options={cancellationOptions}
                    value={form.values.cancellationWindow}
                    onChange={(event) => selectCancellationWindow(event.target.value)}
                    aria-invalid={form.errorFor('cancellationWindow') !== undefined}
                    aria-describedby={cancellationMessage?.id}
                />
            </SettingsFieldRow>

            <SettingsFieldRow
                htmlFor={POLICY_MESSAGE_ID}
                label={t('bookingPreferences.policy.message.label')}
                helper={t('bookingPreferences.policy.message.helper')}
                message={policyMessageState}
            >
                <Textarea
                    id={POLICY_MESSAGE_ID}
                    rows={4}
                    placeholder={t('bookingPreferences.policy.message.placeholder')}
                    value={form.values.policyMessage}
                    onChange={(event) => form.update('policyMessage', event.target.value)}
                    aria-invalid={form.errorFor('policyMessage') !== undefined}
                    aria-describedby={policyMessageState?.id}
                    className={TEXTAREA_DENSITY_CLASSES[density]}
                />
            </SettingsFieldRow>

            <SettingsFieldRow
                htmlFor={POLICY_DISPLAY_ID}
                label={t('bookingPreferences.policy.display.label')}
                helper={t('bookingPreferences.policy.display.helper')}
                message={displayMessage}
            >
                <div className="flex min-h-11 items-center md:min-h-9">
                    <Switch
                        id={POLICY_DISPLAY_ID}
                        checked={form.values.displayPolicyOnBookingPage}
                        onCheckedChange={(checked) =>
                            form.update('displayPolicyOnBookingPage', checked)
                        }
                        aria-describedby={displayMessage?.id}
                    />
                </div>
            </SettingsFieldRow>
        </SettingsSection>
    );
}

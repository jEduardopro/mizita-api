import { useTranslation } from 'react-i18next';
import { ComboboxField, comboboxOptionsStatus } from '@/components/form/ComboboxField';
import { FormField } from '@/components/form/FormField';
import { usePublicStateChoices } from '../../queries';
import {
    BOOKING_STATE_COUNTRY,
    isOtherStateSelected,
    stateChoiceOf,
    withCatalogState,
    withOtherState,
    withOtherStateName,
    withStateChoice,
    type BookingStateValue,
} from './booking-state';

const STATE_MODE_TOGGLE_CLASSES =
    'inline-flex min-h-11 items-center justify-self-start rounded-md text-left text-sm text-muted-foreground underline decoration-muted-foreground/50 underline-offset-4 transition-colors outline-none hover:text-foreground hover:decoration-foreground focus-visible:ring-3 focus-visible:ring-ring/50';

type Props = {
    id: string;
    value: BookingStateValue;
    onChange(value: BookingStateValue): void;
    required: boolean;
    error?: string;
};

export function BookingStateField({ id, value, onChange, required, error }: Props) {
    const { t } = useTranslation('public');
    const { t: tCommon } = useTranslation('common');
    const states = usePublicStateChoices(BOOKING_STATE_COUNTRY);
    const isOther = isOtherStateSelected(value);

    return (
        <div className="grid gap-1">
            {isOther ? (
                <FormField
                    id={`${id}-other`}
                    label={t('booking.flow.details.address.state.otherInput.label')}
                    placeholder={t('booking.flow.details.address.state.otherInput.placeholder')}
                    autoComplete="address-level1"
                    autoFocus
                    required={required}
                    value={value.otherName}
                    onChange={(event) => onChange(withOtherStateName(value, event.target.value))}
                    error={error}
                />
            ) : (
                <ComboboxField
                    id={id}
                    label={t('booking.flow.details.address.state.label')}
                    placeholder={t('booking.flow.details.address.state.placeholder')}
                    required={required}
                    options={states.options}
                    value={stateChoiceOf(value)}
                    onChange={(choice) => onChange(withStateChoice(value, choice, states.options))}
                    optionsStatus={comboboxOptionsStatus(states.isPending, states.isError)}
                    onRetryOptions={states.refetch}
                    messages={{
                        empty: t('booking.flow.details.address.state.empty'),
                        optionsError: t('booking.flow.details.address.state.error'),
                        retry: tCommon('actions.tryAgain'),
                        results: states.isPending
                            ? t('booking.flow.details.address.state.loading')
                            : t('booking.flow.details.address.state.results', {
                                  count: states.options.length,
                              }),
                    }}
                    error={error}
                />
            )}

            <button
                type="button"
                onClick={() => onChange(isOther ? withCatalogState(value) : withOtherState(value))}
                className={STATE_MODE_TOGGLE_CLASSES}
            >
                {isOther
                    ? t('booking.flow.details.address.state.chooseFromList')
                    : t('booking.flow.details.address.state.notListed')}
            </button>
        </div>
    );
}

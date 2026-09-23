import {
    durationFromMinutes,
    minutesFromDuration,
    type Duration,
} from '@/components/form/duration-units';
import type {
    BusinessSettings,
    ContactFieldName,
    ContactFieldsSettings,
    UpdateBusinessSettingsPayload,
} from '@/domains/businesses/types';
import {
    BOOKING_WINDOW_UNITS,
    cancellationWindowFrom,
    cancellationWindowMinutesFrom,
    DEFAULT_BOOKING_WINDOW_UNIT,
    DEFAULT_CANCELLATION_WINDOW,
    DEFAULT_LEAD_TIME_UNIT,
    DEFAULT_SLOT_SIZE_MINUTES,
    DEFAULT_SLOT_SIZE_UNIT,
    LEAD_TIME_UNITS,
    SLOT_SIZE_UNITS,
    type CancellationWindow,
} from './booking-policy-options';
import { DEFAULT_CONTACT_FIELDS } from './contact-field-levels';

export const BOOKING_PREFERENCES_SECTION_IDS = {
    policy: 'policy',
    contactFields: 'contact-fields',
} as const;

export const BOOKING_PREFERENCES_SECTIONS = [
    {
        id: BOOKING_PREFERENCES_SECTION_IDS.policy,
        labelKey: 'bookingPreferences.sections.policy',
    },
    {
        id: BOOKING_PREFERENCES_SECTION_IDS.contactFields,
        labelKey: 'bookingPreferences.sections.contactFields',
    },
] as const;

export type BookingPreferencesFormValues = {
    leadTime: Duration;
    bookingWindow: Duration;
    slotSize: Duration;
    cancellationWindow: CancellationWindow;
    policyMessage: string;
    displayPolicyOnBookingPage: boolean;
    contactFields: ContactFieldsSettings;
};

export type BookingPreferencesField = keyof BookingPreferencesFormValues;

export const serverFields: Record<BookingPreferencesField, string> = {
    leadTime: 'booking_policy.lead_time_minutes',
    bookingWindow: 'booking_policy.booking_window_minutes',
    slotSize: 'booking_policy.slot_granularity_minutes',
    cancellationWindow: 'booking_policy.cancellation_window_minutes',
    policyMessage: 'booking_policy.policy_message',
    displayPolicyOnBookingPage: 'booking_policy.display_on_booking_page',
    contactFields: 'contact_fields',
};

export function contactFieldServerKey(name: ContactFieldName): string {
    return `${serverFields.contactFields}.${name}`;
}

export function initialBookingPreferencesValues(
    settings: BusinessSettings | null,
): BookingPreferencesFormValues {
    if (settings === null) {
        return {
            leadTime: { amount: 0, unit: DEFAULT_LEAD_TIME_UNIT },
            bookingWindow: { amount: 0, unit: DEFAULT_BOOKING_WINDOW_UNIT },
            slotSize: { amount: DEFAULT_SLOT_SIZE_MINUTES, unit: DEFAULT_SLOT_SIZE_UNIT },
            cancellationWindow: DEFAULT_CANCELLATION_WINDOW,
            policyMessage: '',
            displayPolicyOnBookingPage: false,
            contactFields: DEFAULT_CONTACT_FIELDS,
        };
    }

    const policy = settings.booking_policy;

    return {
        leadTime: durationFromMinutes(
            policy.lead_time_minutes,
            LEAD_TIME_UNITS,
            DEFAULT_LEAD_TIME_UNIT,
        ),
        bookingWindow: durationFromMinutes(
            policy.booking_window_minutes ?? 0,
            BOOKING_WINDOW_UNITS,
            DEFAULT_BOOKING_WINDOW_UNIT,
        ),
        slotSize: durationFromMinutes(
            policy.slot_granularity_minutes,
            SLOT_SIZE_UNITS,
            DEFAULT_SLOT_SIZE_UNIT,
        ),
        cancellationWindow: cancellationWindowFrom(policy.cancellation_window_minutes),
        policyMessage: policy.policy_message ?? '',
        displayPolicyOnBookingPage: policy.display_on_booking_page,
        contactFields: { ...settings.contact_fields },
    };
}

function trimmedOrNull(value: string): string | null {
    const trimmed = value.trim();

    return trimmed === '' ? null : trimmed;
}

function bookingWindowMinutesFrom(bookingWindow: Duration): number | null {
    const minutes = minutesFromDuration(bookingWindow);

    return minutes === 0 ? null : minutes;
}

export function bookingPreferencesPayloadFrom(
    values: BookingPreferencesFormValues,
): UpdateBusinessSettingsPayload {
    return {
        booking_policy: {
            lead_time_minutes: minutesFromDuration(values.leadTime),
            booking_window_minutes: bookingWindowMinutesFrom(values.bookingWindow),
            slot_granularity_minutes: minutesFromDuration(values.slotSize),
            cancellation_window_minutes: cancellationWindowMinutesFrom(values.cancellationWindow),
            policy_message: trimmedOrNull(values.policyMessage),
            display_on_booking_page: values.displayPolicyOnBookingPage,
        },
        contact_fields: { ...values.contactFields },
    };
}

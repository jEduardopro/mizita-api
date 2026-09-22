import type { AppointmentPayment } from '../types';

export const ITEMS_STEP = 'items';

export const METHOD_STEP = 'method';

export const CHARGE_STEPS = [ITEMS_STEP, METHOD_STEP] as const;

export type ChargeStep = (typeof CHARGE_STEPS)[number];

export function initialChargeStep(existingPayment: AppointmentPayment | null): ChargeStep {
    return existingPayment === null ? ITEMS_STEP : METHOD_STEP;
}

export function nextChargeStep(step: ChargeStep): ChargeStep | null {
    const position = CHARGE_STEPS.indexOf(step) + 1;

    return position < CHARGE_STEPS.length ? CHARGE_STEPS[position] : null;
}

export function previousChargeStep(step: ChargeStep): ChargeStep | null {
    const position = CHARGE_STEPS.indexOf(step) - 1;

    return position < 0 ? null : CHARGE_STEPS[position];
}

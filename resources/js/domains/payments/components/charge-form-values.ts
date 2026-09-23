import { amountInputFromCents, centsFromAmountInput } from '@/components/form/amount-input';
import type { ServiceColor } from '@/lib/service-color';
import { ITEMS_STEP, METHOD_STEP, type ChargeStep } from './charge-steps';
import type { AppointmentPayment, ChargePayload, DiscountType } from '../types';

const BASIS_POINTS = 10_000;

const MINIMUM_TRANSACTION_CENTS = 1;

const FILLED_AMOUNT = /^\d+(?:\.\d{0,2})?$/;

export const TOTAL_FIELD = 'total';

export const AMOUNT_FIELD = 'amount_cents';

export const PAYMENT_METHOD_FIELD = 'payment_method_id';

export type ChargeServiceLine = {
    name: string;
    color: ServiceColor;
    priceCents: number;
};

export type ChargeAddOn = {
    id: string;
    name: string;
    amount: string;
};

export type ChargeDiscountSide = 'percent' | 'amount';

export type ChargeDiscount = {
    side: ChargeDiscountSide;
    percent: string;
    amount: string;
};

export type ChargeFormValues = {
    partial: boolean;
    partialAmount: string;
    addOns: ChargeAddOn[];
    discount: ChargeDiscount | null;
    paymentMethodId: string;
};

export type ChargeTotals = {
    subtotalCents: number;
    discountCents: number;
    balanceCents: number;
    amountCents: number;
    remainingCents: number;
};

export type ChargeErrorCode =
    | 'itemName'
    | 'itemAmount'
    | 'emptyTotal'
    | 'amountAboveBalance'
    | 'methodRequired';

export type ChargeFieldErrors = Record<string, ChargeErrorCode>;

export function addOnNameField(index: number): string {
    return `add_ons.${index}.name`;
}

export function addOnAmountField(index: number): string {
    return `add_ons.${index}.amount_cents`;
}

export function initialChargeValues(): ChargeFormValues {
    return {
        partial: false,
        partialAmount: '',
        addOns: [],
        discount: null,
        paymentMethodId: '',
    };
}

export function newAddOn(): ChargeAddOn {
    return { id: crypto.randomUUID(), name: '', amount: amountInputFromCents(0) };
}

export function newDiscount(): ChargeDiscount {
    return { side: 'amount', percent: '', amount: '' };
}

function basisPointsFromPercentInput(percent: string): number {
    return centsFromAmountInput(percent);
}

function percentInputFromBasisPoints(basisPoints: number): string {
    return amountInputFromCents(basisPoints);
}

function centsFromBasisPoints(subtotalCents: number, basisPoints: number): number {
    return Math.min(Math.round((subtotalCents * basisPoints) / BASIS_POINTS), subtotalCents);
}

function basisPointsFromCents(discountCents: number, subtotalCents: number): number {
    return subtotalCents === 0 ? 0 : Math.round((discountCents * BASIS_POINTS) / subtotalCents);
}

function discountCentsOf(discount: ChargeDiscount | null, subtotalCents: number): number {
    if (discount === null) {
        return 0;
    }

    if (discount.side === 'percent') {
        return discount.percent === ''
            ? 0
            : centsFromBasisPoints(subtotalCents, basisPointsFromPercentInput(discount.percent));
    }

    return discount.amount === ''
        ? 0
        : Math.min(centsFromAmountInput(discount.amount), subtotalCents);
}

export function chargeTotals(values: ChargeFormValues, serviceCents: number): ChargeTotals {
    const subtotalCents = values.addOns.reduce(
        (total, addOn) => total + centsFromAmountInput(addOn.amount),
        serviceCents,
    );
    const discountCents = discountCentsOf(values.discount, subtotalCents);
    const balanceCents = subtotalCents - discountCents;
    const amountCents = values.partial ? centsFromAmountInput(values.partialAmount) : balanceCents;

    return {
        subtotalCents,
        discountCents,
        balanceCents,
        amountCents,
        remainingCents: Math.max(0, balanceCents - amountCents),
    };
}

export function outstandingTotals(payment: AppointmentPayment): ChargeTotals {
    return {
        subtotalCents: payment.subtotal_cents,
        discountCents: payment.discount_amount_cents,
        balanceCents: payment.balance_cents,
        amountCents: payment.balance_cents,
        remainingCents: 0,
    };
}

export function discountPercentValue(values: ChargeFormValues, subtotalCents: number): string {
    const discount = values.discount;

    if (discount === null) {
        return '';
    }

    if (discount.side === 'percent') {
        return discount.percent;
    }

    return discount.amount === ''
        ? ''
        : percentInputFromBasisPoints(
              basisPointsFromCents(discountCentsOf(discount, subtotalCents), subtotalCents),
          );
}

export function discountAmountValue(values: ChargeFormValues, subtotalCents: number): string {
    const discount = values.discount;

    if (discount === null) {
        return '';
    }

    if (discount.side === 'amount') {
        return discount.amount;
    }

    return discount.percent === ''
        ? ''
        : amountInputFromCents(discountCentsOf(discount, subtotalCents));
}

export function withDiscountPercent(values: ChargeFormValues, percent: string): ChargeFormValues {
    return { ...values, discount: { side: 'percent', percent, amount: '' } };
}

export function withDiscountAmount(values: ChargeFormValues, amount: string): ChargeFormValues {
    return { ...values, discount: { side: 'amount', percent: '', amount } };
}

function partialAmountErrors(values: ChargeFormValues, totals: ChargeTotals): ChargeFieldErrors {
    if (! values.partial) {
        return {};
    }

    if (
        ! FILLED_AMOUNT.test(values.partialAmount.trim()) ||
        totals.amountCents < MINIMUM_TRANSACTION_CENTS
    ) {
        return { [AMOUNT_FIELD]: 'itemAmount' };
    }

    if (totals.amountCents > totals.balanceCents) {
        return { [AMOUNT_FIELD]: 'amountAboveBalance' };
    }

    return {};
}

function itemsStepErrors(values: ChargeFormValues, totals: ChargeTotals): ChargeFieldErrors {
    const errors: ChargeFieldErrors = partialAmountErrors(values, totals);

    values.addOns.forEach((addOn, index) => {
        if (addOn.name.trim() === '') {
            errors[addOnNameField(index)] = 'itemName';
        }

        if (! FILLED_AMOUNT.test(addOn.amount.trim())) {
            errors[addOnAmountField(index)] = 'itemAmount';
        }
    });

    if (totals.balanceCents <= 0) {
        errors[TOTAL_FIELD] = 'emptyTotal';
    }

    return errors;
}

function methodStepErrors(values: ChargeFormValues, totals: ChargeTotals): ChargeFieldErrors {
    const errors: ChargeFieldErrors = partialAmountErrors(values, totals);

    if (values.paymentMethodId === '') {
        errors[PAYMENT_METHOD_FIELD] = 'methodRequired';
    }

    return errors;
}

const STEP_VALIDATORS: Record<
    ChargeStep,
    (values: ChargeFormValues, totals: ChargeTotals) => ChargeFieldErrors
> = {
    [ITEMS_STEP]: itemsStepErrors,
    [METHOD_STEP]: methodStepErrors,
};

export function chargeStepErrors(
    step: ChargeStep,
    values: ChargeFormValues,
    totals: ChargeTotals,
): ChargeFieldErrors {
    return STEP_VALIDATORS[step](values, totals);
}

function discountPayloadFrom(
    values: ChargeFormValues,
    totals: ChargeTotals,
): { type: DiscountType; value: number } | null {
    const discount = values.discount;

    if (discount === null) {
        return null;
    }

    if (discount.side === 'percent') {
        return discount.percent === ''
            ? null
            : { type: 'percentage', value: basisPointsFromPercentInput(discount.percent) };
    }

    return discount.amount === '' ? null : { type: 'fixed', value: totals.discountCents };
}

export function chargePayloadFrom(values: ChargeFormValues, totals: ChargeTotals): ChargePayload {
    return {
        add_ons: values.addOns.map((addOn) => ({
            name: addOn.name.trim(),
            amount_cents: centsFromAmountInput(addOn.amount),
        })),
        discount: discountPayloadFrom(values, totals),
        payment_method_id: values.paymentMethodId,
        amount_cents: totals.amountCents,
    };
}

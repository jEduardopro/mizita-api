import type { FormEvent } from 'react';
import { useState } from 'react';
import { useTranslation } from 'react-i18next';
import { amountInputFromCents } from '@/components/form/amount-input';
import { useServerErrors } from '@/hooks/use-server-errors';
import { raiseSuccessToast } from '@/lib/toast';
import {
    addOnAmountField,
    addOnNameField,
    AMOUNT_FIELD,
    chargePayloadFrom,
    chargeStepErrors,
    chargeTotals,
    discountAmountValue,
    discountPercentValue,
    initialChargeValues,
    newAddOn,
    newDiscount,
    outstandingTotals,
    PAYMENT_METHOD_FIELD,
    withDiscountAmount,
    withDiscountPercent,
    type ChargeErrorCode,
    type ChargeFormValues,
    type ChargeServiceLine,
    type ChargeTotals,
} from './charge-form-values';
import {
    initialChargeStep,
    nextChargeStep,
    previousChargeStep,
    type ChargeStep,
} from './charge-steps';
import { usePaymentMethods, useChargeAppointment, useRecordPaymentTransaction } from '../queries';
import type { AppointmentPayment, PaymentMethod } from '../types';

const MESSAGE_KEYS = {
    itemName: 'payments.errors.itemName',
    itemAmount: 'payments.errors.itemAmount',
    emptyTotal: 'payments.errors.emptyTotal',
    amountAboveBalance: 'payments.errors.amountAboveBalance',
    methodRequired: 'payments.method.required',
} as const satisfies Record<ChargeErrorCode, string>;

export type ChargeFormParams = {
    open: boolean;
    appointmentId: string;
    customerName: string;
    serviceLine: ChargeServiceLine;
    currencyCode: string;
    existingPayment: AppointmentPayment | null;
    onPaid: (payment: AppointmentPayment) => void;
};

export type ChargeFormController = {
    step: ChargeStep;
    canGoBack: boolean;
    goBack: () => void;
    values: ChargeFormValues;
    totals: ChargeTotals;
    customerName: string;
    serviceLine: ChargeServiceLine;
    currencyCode: string;
    discountPercent: string;
    discountAmount: string;
    methods: PaymentMethod[];
    isLoadingMethods: boolean;
    hasMethodsError: boolean;
    setPartial: (partial: boolean) => void;
    setPartialAmount: (amount: string) => void;
    addAddOn: () => void;
    setAddOnName: (index: number, name: string) => void;
    setAddOnAmount: (index: number, amount: string) => void;
    removeAddOn: (index: number) => void;
    addDiscount: () => void;
    setDiscountPercent: (percent: string) => void;
    setDiscountAmount: (amount: string) => void;
    removeDiscount: () => void;
    selectMethod: (paymentMethodId: string) => void;
    errorFor: (field: string) => string | undefined;
    isSubmitting: boolean;
    advance: () => Promise<boolean>;
};

export type ChargeFormSurfaceProps = {
    form: ChargeFormController;
    title: string;
    actionLabel: string;
    backLabel: string;
    onBack: () => void;
    onCancel: () => void;
    onSubmit: (event: FormEvent<HTMLFormElement>) => void;
};

export function useChargeForm({
    open,
    appointmentId,
    customerName,
    serviceLine,
    currencyCode,
    existingPayment,
    onPaid,
}: ChargeFormParams): ChargeFormController {
    const { t } = useTranslation('admin');
    const firstStep = initialChargeStep(existingPayment);
    const [step, setStep] = useState<ChargeStep>(firstStep);
    const [values, setValues] = useState<ChargeFormValues>(initialChargeValues);
    const [attemptedStep, setAttemptedStep] = useState<ChargeStep | null>(null);
    const [wasOpen, setWasOpen] = useState(open);
    const serverErrors = useServerErrors();
    const paymentMethods = usePaymentMethods();
    const chargeAppointment = useChargeAppointment(appointmentId);
    const recordTransaction = useRecordPaymentTransaction(appointmentId);

    if (open !== wasOpen) {
        setWasOpen(open);

        if (open) {
            setStep(firstStep);
            setValues(initialChargeValues());
            setAttemptedStep(null);
        }
    }

    const totals =
        existingPayment === null
            ? chargeTotals(values, serviceLine.priceCents)
            : outstandingTotals(existingPayment);
    const stepErrors = chargeStepErrors(step, values, totals);

    function errorFor(field: string): string | undefined {
        if (attemptedStep !== step) {
            return undefined;
        }

        const code = stepErrors[field];

        return code === undefined ? serverErrors.fieldErrors[field] : t(MESSAGE_KEYS[code]);
    }

    function write(build: (current: ChargeFormValues) => ChargeFormValues, field?: string) {
        setValues(build);

        if (field !== undefined) {
            serverErrors.clearField(field);
        }
    }

    function patch(change: Partial<ChargeFormValues>, field?: string) {
        write((current) => ({ ...current, ...change }), field);
    }

    function setPartial(partial: boolean) {
        patch({
            partial,
            partialAmount: partial
                ? amountInputFromCents(totals.balanceCents)
                : values.partialAmount,
        });
    }

    function addAddOn() {
        write((current) => ({ ...current, addOns: [...current.addOns, newAddOn()] }));
    }

    function setAddOnName(index: number, name: string) {
        write(
            (current) => ({
                ...current,
                addOns: current.addOns.map((addOn, position) =>
                    position === index ? { ...addOn, name } : addOn,
                ),
            }),
            addOnNameField(index),
        );
    }

    function setAddOnAmount(index: number, amount: string) {
        write(
            (current) => ({
                ...current,
                addOns: current.addOns.map((addOn, position) =>
                    position === index ? { ...addOn, amount } : addOn,
                ),
            }),
            addOnAmountField(index),
        );
    }

    function removeAddOn(index: number) {
        write((current) => ({
            ...current,
            addOns: current.addOns.filter((_, position) => position !== index),
        }));
    }

    async function submit(): Promise<boolean> {
        try {
            const payment =
                existingPayment === null
                    ? await chargeAppointment.mutateAsync(chargePayloadFrom(values, totals))
                    : await recordTransaction.mutateAsync({
                          paymentId: existingPayment.id,
                          payload: {
                              payment_method_id: values.paymentMethodId,
                              amount_cents: totals.amountCents,
                          },
                      });

            raiseSuccessToast(t('payments.toasts.charged'));
            onPaid(payment);

            return true;
        } catch (error) {
            serverErrors.capture(error, t('payments.errors.chargeFailed'));

            return false;
        }
    }

    async function advance(): Promise<boolean> {
        setAttemptedStep(step);

        if (Object.keys(stepErrors).length > 0) {
            return false;
        }

        const next = nextChargeStep(step);

        if (next !== null) {
            setAttemptedStep(null);
            setStep(next);

            return true;
        }

        serverErrors.reset();

        return submit();
    }

    function goBack() {
        const previous = previousChargeStep(step);

        if (previous === null || step === firstStep) {
            return;
        }

        setAttemptedStep(null);
        setStep(previous);
    }

    return {
        step,
        canGoBack: step !== firstStep,
        goBack,
        values,
        totals,
        customerName,
        serviceLine,
        currencyCode,
        discountPercent: discountPercentValue(values, totals.subtotalCents),
        discountAmount: discountAmountValue(values, totals.subtotalCents),
        methods: paymentMethods.data ?? [],
        isLoadingMethods: paymentMethods.isPending,
        hasMethodsError: paymentMethods.isError,
        setPartial,
        setPartialAmount: (amount) => patch({ partialAmount: amount }, AMOUNT_FIELD),
        addAddOn,
        setAddOnName,
        setAddOnAmount,
        removeAddOn,
        addDiscount: () => patch({ discount: newDiscount() }),
        setDiscountPercent: (percent) => write((current) => withDiscountPercent(current, percent)),
        setDiscountAmount: (amount) => write((current) => withDiscountAmount(current, amount)),
        removeDiscount: () => patch({ discount: null }),
        selectMethod: (paymentMethodId) => patch({ paymentMethodId }, PAYMENT_METHOD_FIELD),
        errorFor,
        isSubmitting: chargeAppointment.isPending || recordTransaction.isPending,
        advance,
    };
}

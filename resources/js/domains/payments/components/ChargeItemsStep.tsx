import { Plus, X } from 'lucide-react';
import { useId } from 'react';
import { useTranslation } from 'react-i18next';
import { fieldMessage, FieldMessage } from '@/components/form/FieldMessage';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Separator } from '@/components/ui/separator';
import { Switch } from '@/components/ui/switch';
import { formatMoneyFromCents } from '@/lib/money';
import {
    addOnAmountField,
    addOnNameField,
    AMOUNT_FIELD,
    TOTAL_FIELD,
    type ChargeAddOn,
} from './charge-form-values';
import { ChargeAmountInput } from './ChargeAmountInput';
import { ChargeAmountRow } from './ChargeAmountRow';
import { ChargeDiscountRow } from './ChargeDiscountRow';
import { ChargeLineItemRow } from './ChargeLineItemRow';
import { ChargePartyHeader } from './ChargePartyHeader';
import type { ChargeFormController } from './use-charge-form';

const LINK_ACTION = 'h-11 w-fit justify-start px-0 underline md:h-9';

type AddOnRowProps = {
    addOn: ChargeAddOn;
    position: number;
    nameError?: string;
    amountError?: string;
    onNameChange: (name: string) => void;
    onAmountChange: (amount: string) => void;
    onRemove: () => void;
};

function AddOnRow({
    addOn,
    position,
    nameError,
    amountError,
    onNameChange,
    onAmountChange,
    onRemove,
}: AddOnRowProps) {
    const { t } = useTranslation('admin');
    const rowId = useId();
    const nameMessage = fieldMessage({ id: `${rowId}-name`, error: nameError });
    const itemLabel = addOn.name.trim() === '' ? position : addOn.name;

    return (
        <ChargeLineItemRow
            color={null}
            name={
                <div className="grid gap-1">
                    <Input
                        id={`${rowId}-name`}
                        aria-label={t('payments.items.namePlaceholder')}
                        placeholder={t('payments.items.namePlaceholder')}
                        autoComplete="off"
                        value={addOn.name}
                        aria-invalid={nameError !== undefined}
                        aria-describedby={nameMessage?.id}
                        onChange={(event) => onNameChange(event.target.value)}
                        className="h-11 text-base md:h-9"
                    />

                    <FieldMessage message={nameMessage} />
                </div>
            }
            amount={
                <ChargeAmountInput
                    id={`${rowId}-amount`}
                    label={t('payments.items.newAmountLabel')}
                    value={addOn.amount}
                    onChange={onAmountChange}
                    error={amountError}
                />
            }
            message={
                <FieldMessage message={fieldMessage({ id: `${rowId}-amount`, error: amountError })} />
            }
            action={
                <Button
                    type="button"
                    variant="ghost"
                    size="icon"
                    onClick={onRemove}
                    aria-label={t('payments.items.remove', { item: itemLabel })}
                    className="size-11 shrink-0 md:size-8"
                >
                    <X />
                </Button>
            }
        />
    );
}

type Props = {
    form: ChargeFormController;
};

export function ChargeItemsStep({ form }: Props) {
    const { t, i18n } = useTranslation('admin');
    const partialId = useId();
    const { values, totals, serviceLine } = form;

    function money(cents: number): string {
        return formatMoneyFromCents(cents, form.currencyCode, i18n.language);
    }

    return (
        <div className="grid gap-5">
            <ChargePartyHeader name={form.customerName} />

            <div className="flex items-center justify-between gap-3 rounded-xl border border-border px-3 py-2">
                <Label
                    htmlFor={partialId}
                    className="grid min-h-11 flex-1 content-center gap-0.5 py-1"
                >
                    <span className="text-base md:text-sm">{t('payments.partial.label')}</span>

                    <span className="text-xs font-normal text-muted-foreground">
                        {t('payments.partial.hint')}
                    </span>
                </Label>

                <Switch
                    id={partialId}
                    checked={values.partial}
                    onCheckedChange={form.setPartial}
                />
            </div>

            <div className="grid gap-3">
                <ChargeLineItemRow
                    color={serviceLine.color}
                    name={<span className="block truncate text-sm">{serviceLine.name}</span>}
                    amount={
                        values.partial ? (
                            <ChargeAmountInput
                                id={`${partialId}-service`}
                                label={t('payments.items.amountLabel', { item: serviceLine.name })}
                                value={values.partialAmount}
                                onChange={form.setPartialAmount}
                                error={form.errorFor(AMOUNT_FIELD)}
                            />
                        ) : (
                            <span className="text-sm tabular-nums">
                                {money(serviceLine.priceCents)}
                            </span>
                        )
                    }
                    message={
                        <FieldMessage
                            message={fieldMessage({
                                id: `${partialId}-service`,
                                error: form.errorFor(AMOUNT_FIELD),
                            })}
                        />
                    }
                />

                {values.addOns.map((addOn, index) => (
                    <AddOnRow
                        key={addOn.id}
                        addOn={addOn}
                        position={index + 1}
                        nameError={form.errorFor(addOnNameField(index))}
                        amountError={form.errorFor(addOnAmountField(index))}
                        onNameChange={(name) => form.setAddOnName(index, name)}
                        onAmountChange={(amount) => form.setAddOnAmount(index, amount)}
                        onRemove={() => form.removeAddOn(index)}
                    />
                ))}

                <Button type="button" variant="link" onClick={form.addAddOn} className={LINK_ACTION}>
                    <Plus />
                    {t('payments.items.add')}
                </Button>
            </div>

            <Separator />

            <ChargeAmountRow
                label={t('payments.items.subtotal')}
                value={money(totals.subtotalCents)}
            />

            {values.discount === null ? (
                <Button
                    type="button"
                    variant="link"
                    onClick={form.addDiscount}
                    className={LINK_ACTION}
                >
                    <Plus />
                    {t('payments.discount.add')}
                </Button>
            ) : (
                <ChargeDiscountRow
                    percent={form.discountPercent}
                    amount={form.discountAmount}
                    onPercentChange={form.setDiscountPercent}
                    onAmountChange={form.setDiscountAmount}
                    onRemove={form.removeDiscount}
                />
            )}

            <Separator />

            <ChargeAmountRow
                label={t('payments.items.balance')}
                value={money(totals.balanceCents)}
                tone="strong"
                error={form.errorFor(TOTAL_FIELD)}
            />

            {values.partial ? (
                <ChargeAmountRow
                    label={t('payments.partial.remaining')}
                    value={money(totals.remainingCents)}
                />
            ) : null}
        </div>
    );
}

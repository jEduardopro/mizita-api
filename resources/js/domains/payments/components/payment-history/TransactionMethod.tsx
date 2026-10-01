import { paymentMethodIcon } from '../payment-method-labels';

type Props = {
    code: string;
    name: string;
};

export function TransactionMethod({ code, name }: Props) {
    const Icon = paymentMethodIcon(code);

    return (
        <span className="inline-flex min-w-0 items-center gap-2">
            <Icon aria-hidden="true" className="size-4 shrink-0 text-muted-foreground" />
            <span className="truncate">{name}</span>
        </span>
    );
}

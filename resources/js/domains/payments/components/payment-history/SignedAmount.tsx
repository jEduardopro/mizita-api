import { cn } from 'cn';
import { formatMoneyFromCents } from '@/lib/money';

type Props = {
    cents: number;
    currencyCode: string;
    className?: string;
};

export function SignedAmount({ cents, currencyCode, className }: Props) {
    return (
        <span
            className={cn(
                'whitespace-nowrap tabular-nums',
                cents < 0 ? 'text-muted-foreground' : 'font-medium',
                className,
            )}
        >
            {formatMoneyFromCents(cents, currencyCode)}
        </span>
    );
}

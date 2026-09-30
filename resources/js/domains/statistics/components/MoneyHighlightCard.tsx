import { cn } from 'cn';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { formatFixedMoneyFromCents } from '@/lib/money';
import { HeadlineFigure } from './HeadlineFigure';

type Props = {
    title: string;
    caption: string;
    cents: number;
    className?: string;
};

export function MoneyHighlightCard({ title, caption, cents, className }: Props) {
    return (
        <Card className={cn('justify-between', className)}>
            <CardHeader>
                <CardTitle>{title}</CardTitle>
                <CardDescription className="first-letter:uppercase">{caption}</CardDescription>
            </CardHeader>

            <CardContent className="@container">
                <HeadlineFigure value={formatFixedMoneyFromCents(cents)} className="text-success" />
            </CardContent>
        </Card>
    );
}

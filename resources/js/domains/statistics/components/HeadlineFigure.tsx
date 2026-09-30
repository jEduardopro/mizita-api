import { cn } from 'cn';

type Props = {
    value: string;
    className?: string;
};

export function HeadlineFigure({ value, className }: Props) {
    return (
        <p
            className={cn(
                'text-[clamp(1.75rem,13cqi,2.75rem)] leading-none font-semibold tracking-tight break-words tabular-nums',
                className,
            )}
        >
            {value}
        </p>
    );
}

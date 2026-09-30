type Props = {
    label: string;
    value: string;
};

export function MetricRow({ label, value }: Props) {
    return (
        <div className="flex items-baseline justify-between gap-3 py-2">
            <dt className="text-muted-foreground">{label}</dt>
            <dd className="font-medium tabular-nums">{value}</dd>
        </div>
    );
}

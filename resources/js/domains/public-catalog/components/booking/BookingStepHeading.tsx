type Props = {
    eyebrow: string;
    title: string;
    description?: string;
};

export function BookingStepHeading({ eyebrow, title, description }: Props) {
    return (
        <header className="grid gap-2">
            <p className="text-[0.6875rem] font-medium tracking-[0.12em] text-muted-foreground uppercase">
                {eyebrow}
            </p>

            <h1 className="font-heading text-[clamp(1.5rem,5vw,2rem)] leading-tight font-semibold tracking-[-0.02em] text-balance">
                {title}
            </h1>

            {description === undefined ? null : (
                <p className="text-sm leading-relaxed text-muted-foreground text-pretty">
                    {description}
                </p>
            )}
        </header>
    );
}

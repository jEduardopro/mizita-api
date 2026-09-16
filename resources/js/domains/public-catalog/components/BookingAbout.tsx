type Props = {
    about: string;
};

export function BookingAbout({ about }: Props) {
    return (
        <p className="max-w-2xl text-[0.9375rem] leading-relaxed whitespace-pre-line text-pretty text-muted-foreground">
            {about}
        </p>
    );
}

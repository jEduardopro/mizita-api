type Props = {
    message: string | null;
};

export function FormStatus({ message }: Props) {
    if (! message) {
        return null;
    }

    return (
        <p
            role="status"
            className="rounded-lg bg-muted px-3 py-2 text-sm text-muted-foreground"
        >
            {message}
        </p>
    );
}

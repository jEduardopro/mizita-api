type Props = {
    /** The flash message shared by the backend, or null when there is none. */
    message: string | null;
};

/**
 * A flash message from the session, shown above a form. Renders nothing when
 * there is nothing to say.
 */
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

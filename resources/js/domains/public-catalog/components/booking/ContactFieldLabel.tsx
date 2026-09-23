type Props = {
    text: string;
    required: boolean;
};

export function ContactFieldLabel({ text, required }: Props) {
    return (
        <span>
            {text}
            {required ? (
                <span aria-hidden="true" className="ms-0.5 text-destructive">
                    *
                </span>
            ) : null}
        </span>
    );
}

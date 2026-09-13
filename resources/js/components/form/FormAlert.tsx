import { CircleAlert } from 'lucide-react';
import { Alert, AlertTitle } from '@/components/ui/alert';

type Props = {
    /** What went wrong with the submission as a whole, pre-translated. */
    message: string;
};

/**
 * The form-level failure: what the server said about the request rather than
 * about one field.
 *
 * The caller renders it only when there is something to say, and that is the
 * announcement — a `role="alert"` node entering the document is what a screen
 * reader reports. An always-mounted live region would be the other way of doing
 * it and a worse one: it announces on every re-render that touches its text, and
 * it leaves an empty box in the layout the rest of the time.
 *
 * The icon carries the same meaning as the colour, because colour alone is not a
 * signal for everyone reading the screen.
 */
export function FormAlert({ message }: Props) {
    return (
        <Alert
            variant="destructive"
            className="items-start border-destructive/25 bg-destructive/5 px-3 py-2.5"
        >
            <CircleAlert aria-hidden="true" />
            <AlertTitle className="font-normal text-pretty">{message}</AlertTitle>
        </Alert>
    );
}

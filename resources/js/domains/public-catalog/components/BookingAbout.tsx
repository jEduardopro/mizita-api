import type { PublicLink } from '../types';
import { BookingContactDetails } from './BookingContactDetails';

type Props = {
    about: string;
    phone: string | null;
    links: PublicLink[];
};

export function BookingAbout({ about, phone, links }: Props) {
    return (
        <div className="grid gap-8">
            {about === '' ? null : (
                <p className="max-w-2xl text-[0.9375rem] leading-relaxed whitespace-pre-line text-pretty text-muted-foreground">
                    {about}
                </p>
            )}

            <BookingContactDetails phone={phone} links={links} />
        </div>
    );
}

import { useState, type FormEvent } from 'react';
import { useTranslation } from 'react-i18next';
import { useServerErrors } from '@/hooks/use-server-errors';
import { errorCodeFrom, formMessageFrom } from '@/lib/http';
import { raiseSuccessToast } from '@/lib/toast';
import { useChangeStaffBookingSlug } from '../queries';
import { BOOKING_SLUG_REJECTION_CODES } from '../types';

const SLUG_FIELD = 'slug';

function rejectsSlug(error: unknown): boolean {
    const code = errorCodeFrom(error);

    return BOOKING_SLUG_REJECTION_CODES.some((rejection) => rejection === code);
}

export type BookingSlugFormController = {
    slug: string;
    update: (value: string) => void;
    error: string | undefined;
    canSubmit: boolean;
    isSubmitting: boolean;
    submit: (event: FormEvent<HTMLFormElement>) => void;
};

type Params = {
    staffMemberId: string;
    currentSlug: string;
    onSaved: () => void;
};

export function useBookingSlugForm({ staffMemberId, currentSlug, onSaved }: Params): BookingSlugFormController {
    const { t } = useTranslation('admin');
    const { fieldErrors, capture, clearField, reset } = useServerErrors();
    const changeSlug = useChangeStaffBookingSlug();
    const [slug, setSlug] = useState(currentSlug);
    const [rejectionMessage, setRejectionMessage] = useState<string | undefined>(undefined);

    const trimmedSlug = slug.trim();

    function update(value: string) {
        setSlug(value);
        setRejectionMessage(undefined);
        clearField(SLUG_FIELD);
    }

    function handleFailure(error: unknown) {
        if (rejectsSlug(error)) {
            setRejectionMessage(formMessageFrom(error, t('profile.bookingLink.dialog.taken')));

            return;
        }

        capture(error, t('profile.bookingLink.dialog.saveFailed'));
    }

    async function save() {
        reset();
        setRejectionMessage(undefined);

        try {
            await changeSlug.mutateAsync({ staffMemberId, payload: { slug: trimmedSlug } });
        } catch (error) {
            handleFailure(error);

            return;
        }

        raiseSuccessToast(t('profile.bookingLink.dialog.saved'));
        onSaved();
    }

    function submit(event: FormEvent<HTMLFormElement>) {
        event.preventDefault();
        void save();
    }

    return {
        slug,
        update,
        error: fieldErrors[SLUG_FIELD] ?? rejectionMessage,
        canSubmit: trimmedSlug !== '' && trimmedSlug !== currentSlug,
        isSubmitting: changeSlug.isPending,
        submit,
    };
}

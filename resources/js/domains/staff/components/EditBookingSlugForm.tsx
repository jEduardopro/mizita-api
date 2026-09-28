import { QrCode } from 'lucide-react';
import { useTranslation } from 'react-i18next';
import { FormField } from '@/components/form/FormField';
import { SubmitButton } from '@/components/form/SubmitButton';
import { Button } from '@/components/ui/button';
import { DialogClose, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { bookingLinkPrefixOf, withoutProtocol } from './booking-link';
import { useBookingSlugForm } from './use-booking-slug-form';

const SLUG_INPUT_ID = 'booking-slug';

const PREVIEW_LABEL_ID = 'booking-slug-preview';

const BODY_CLASSES = 'grid min-h-0 flex-1 content-start gap-5 overflow-y-auto overscroll-contain p-4 sm:p-5';

const FOOTER_CLASSES =
    'mx-0 mb-0 shrink-0 rounded-b-none px-4 pt-4 pb-[calc(1rem+env(safe-area-inset-bottom))] sm:rounded-b-xl sm:px-5 sm:pb-4';

const FOOTER_BUTTON_CLASSES = 'h-11 px-4 md:h-9';

type Props = {
    staffMemberId: string;
    slug: string;
    url: string;
    onSaved: () => void;
};

export function EditBookingSlugForm({ staffMemberId, slug, url, onSaved }: Props) {
    const { t } = useTranslation('admin');
    const { t: tCommon } = useTranslation('common');
    const form = useBookingSlugForm({ staffMemberId, currentSlug: slug, onSaved });

    const prefix = withoutProtocol(bookingLinkPrefixOf(url, slug));

    return (
        <form onSubmit={form.submit} className="flex min-h-0 flex-1 flex-col">
            <div className={BODY_CLASSES}>
                <DialogHeader className="gap-3">
                    <span className="grid size-10 place-items-center rounded-lg bg-muted">
                        <QrCode aria-hidden="true" className="size-5 text-foreground" />
                    </span>

                    <DialogTitle>{t('profile.bookingLink.dialog.title')}</DialogTitle>

                    <DialogDescription className="text-pretty">
                        {t('profile.bookingLink.dialog.description')}
                    </DialogDescription>
                </DialogHeader>

                <FormField
                    id={SLUG_INPUT_ID}
                    type="text"
                    autoComplete="off"
                    autoCapitalize="none"
                    autoCorrect="off"
                    spellCheck={false}
                    enterKeyHint="done"
                    autoFocus
                    required
                    label={t('profile.bookingLink.dialog.slug')}
                    value={form.slug}
                    onChange={(event) => form.update(event.target.value)}
                    error={form.error}
                />

                <div className="grid gap-1.5">
                    <p id={PREVIEW_LABEL_ID} className="text-xs font-medium text-muted-foreground">
                        {t('profile.bookingLink.dialog.preview')}
                    </p>

                    <p
                        aria-labelledby={PREVIEW_LABEL_ID}
                        className="rounded-lg border border-dashed border-border px-3 py-2.5 text-sm break-all"
                    >
                        <span className="text-muted-foreground">{prefix}</span>
                        <span className="font-medium text-foreground">{form.slug.trim()}</span>
                    </p>
                </div>
            </div>

            <DialogFooter className={FOOTER_CLASSES}>
                <DialogClose asChild>
                    <Button type="button" variant="ghost" disabled={form.isSubmitting} className={FOOTER_BUTTON_CLASSES}>
                        {tCommon('actions.cancel')}
                    </Button>
                </DialogClose>

                <SubmitButton
                    variant="brand"
                    className={FOOTER_BUTTON_CLASSES}
                    label={t('profile.bookingLink.dialog.save')}
                    submittingLabel={t('profile.bookingLink.dialog.saving')}
                    isSubmitting={form.isSubmitting}
                    disabled={! form.canSubmit}
                />
            </DialogFooter>
        </form>
    );
}

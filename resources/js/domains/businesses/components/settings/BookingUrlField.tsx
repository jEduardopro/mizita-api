import { cn } from 'cn';
import { useTranslation } from 'react-i18next';
import { fieldMessage, FieldMessage } from '@/components/form/FieldMessage';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { bookingPageHost } from './booking-page-url';
import { BookingUrlActions } from './BookingUrlActions';

const FIELD_ID = 'booking-page-slug';

type Props = {
    value: string;
    savedSlug: string;
    onChange: (value: string) => void;
    error?: string;
};

export function BookingUrlField({ value, savedSlug, onChange, error }: Props) {
    const { t } = useTranslation('admin');

    const isDraftUnsaved = savedSlug !== '' && value.trim() !== savedSlug;

    const message = fieldMessage({
        id: FIELD_ID,
        error,
        hint: isDraftUnsaved
            ? t('businessSettings.brand.url.unsavedHint', { slug: savedSlug })
            : t('businessSettings.brand.url.hint'),
    });

    return (
        <div className="grid gap-2">
            <Label htmlFor={FIELD_ID}>{t('businessSettings.brand.url.label')}</Label>

            <div className="flex flex-wrap items-center gap-2">
                <div
                    className={cn(
                        'flex min-w-50 flex-1 items-stretch overflow-hidden rounded-lg border transition-colors focus-within:ring-3 focus-within:ring-ring/50',
                        error
                            ? 'border-destructive focus-within:border-destructive'
                            : 'border-input focus-within:border-ring',
                    )}
                >
                    <span
                        aria-hidden="true"
                        className="flex max-w-[45%] shrink items-center truncate border-r border-input bg-muted px-3 text-sm text-muted-foreground"
                    >
                        {bookingPageHost()}/
                    </span>

                    <Input
                        id={FIELD_ID}
                        value={value}
                        onChange={(event) => onChange(event.target.value)}
                        aria-invalid={!! error}
                        aria-describedby={message?.id}
                        autoComplete="off"
                        autoCapitalize="none"
                        autoCorrect="off"
                        spellCheck={false}
                        className="h-11 min-w-0 flex-1 rounded-none border-0 text-base focus-visible:ring-0 md:text-base dark:bg-transparent"
                    />
                </div>

                <BookingUrlActions slug={savedSlug} />
            </div>

            <FieldMessage message={message} />
        </div>
    );
}

import { cn } from 'cn';
import { useTranslation } from 'react-i18next';
import { fieldMessage, FieldMessage } from '@/components/form/FieldMessage';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

const FIELD_ID = 'booking-page-slug';

type Props = {
    value: string;
    onChange: (value: string) => void;
    error?: string;
};

export function BookingUrlField({ value, onChange, error }: Props) {
    const { t } = useTranslation('admin');

    const message = fieldMessage({
        id: FIELD_ID,
        error,
        hint: t('businessSettings.brand.url.hint'),
    });

    return (
        <div className="grid gap-2">
            <Label htmlFor={FIELD_ID}>{t('businessSettings.brand.url.label')}</Label>

            <div
                className={cn(
                    'flex items-stretch overflow-hidden rounded-lg border transition-colors focus-within:ring-3 focus-within:ring-ring/50',
                    error
                        ? 'border-destructive focus-within:border-destructive'
                        : 'border-input focus-within:border-ring',
                )}
            >
                <span
                    aria-hidden="true"
                    className="flex max-w-[45%] shrink items-center truncate border-r border-input bg-muted px-3 text-sm text-muted-foreground"
                >
                    {window.location.host}/
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

            <FieldMessage message={message} />
        </div>
    );
}

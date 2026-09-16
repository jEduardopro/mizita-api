import { cn } from 'cn';
import { Monitor, Moon, Sun, type LucideIcon } from 'lucide-react';
import { useTranslation } from 'react-i18next';
import { fieldMessage, FieldMessage } from '@/components/form/FieldMessage';
import { RadioGroup, RadioGroupItem } from '@/components/ui/radio-group';
import { PAGE_THEMES, type PageTheme } from '@/domains/businesses/types';

const FIELD_ID = 'page-theme';

const THEME_ICONS: Record<PageTheme, LucideIcon> = {
    system: Monitor,
    light: Sun,
    dark: Moon,
};

const LABEL_KEYS = {
    system: 'businessSettings.theme.themes.system',
    light: 'businessSettings.theme.themes.light',
    dark: 'businessSettings.theme.themes.dark',
} as const satisfies Record<PageTheme, string>;

type Props = {
    value: PageTheme;
    onChange: (value: PageTheme) => void;
    error?: string;
};

export function ThemeField({ value, onChange, error }: Props) {
    const { t } = useTranslation('admin');

    const message = fieldMessage({ id: FIELD_ID, error });

    function selectTheme(candidate: string): void {
        const theme = PAGE_THEMES.find((option) => option === candidate);

        if (theme !== undefined) {
            onChange(theme);
        }
    }

    return (
        <div className="grid gap-2">
            <span id={`${FIELD_ID}-label`} className="text-sm leading-none font-medium">
                {t('businessSettings.theme.label')}
            </span>

            <RadioGroup
                value={value}
                onValueChange={selectTheme}
                aria-labelledby={`${FIELD_ID}-label`}
                aria-describedby={message?.id}
                className="grid-cols-3 gap-2 sm:gap-3"
            >
                {PAGE_THEMES.map((theme) => {
                    const Icon = THEME_ICONS[theme];
                    const isSelected = theme === value;

                    return (
                        <label
                            key={theme}
                            htmlFor={`${FIELD_ID}-${theme}`}
                            className={cn(
                                'flex min-h-24 flex-col items-center justify-center gap-3 rounded-xl border p-3 text-center transition-colors has-[:focus-visible]:ring-3 has-[:focus-visible]:ring-ring/50',
                                isSelected
                                    ? 'border-primary bg-surface-brand'
                                    : 'border-border hover:bg-muted',
                            )}
                        >
                            <Icon aria-hidden="true" className="size-6 text-muted-foreground" />

                            <span className="text-xs font-medium text-pretty">
                                {t(LABEL_KEYS[theme])}
                            </span>

                            <RadioGroupItem id={`${FIELD_ID}-${theme}`} value={theme} className="sr-only" />
                        </label>
                    );
                })}
            </RadioGroup>

            <FieldMessage message={message} />
        </div>
    );
}

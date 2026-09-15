import { Monitor, Moon, Sun, type LucideIcon } from 'lucide-react';
import { useTranslation } from 'react-i18next';
import { DropdownMenuRadioGroup, DropdownMenuRadioItem } from '@/components/ui/dropdown-menu';
import { useAppearance } from '@/hooks/use-appearance';
import { setAppearance, type Appearance } from '@/lib/appearance';

type AppearanceOption = {
    value: Appearance;
    labelKey: 'account.light' | 'account.dark' | 'account.system';
    icon: LucideIcon;
};

const appearanceOptions: AppearanceOption[] = [
    { value: 'light', labelKey: 'account.light', icon: Sun },
    { value: 'dark', labelKey: 'account.dark', icon: Moon },
    { value: 'system', labelKey: 'account.system', icon: Monitor },
];

const fallbackAppearance: Appearance = 'system';

function toAppearance(value: string): Appearance {
    const option = appearanceOptions.find((candidate) => candidate.value === value);

    return option === undefined ? fallbackAppearance : option.value;
}

export function AppearanceMenu() {
    const { t } = useTranslation('admin');
    const { appearance } = useAppearance();

    return (
        <DropdownMenuRadioGroup
            value={appearance}
            onValueChange={(value) => setAppearance(toAppearance(value))}
        >
            {appearanceOptions.map((option) => {
                const Icon = option.icon;

                return (
                    <DropdownMenuRadioItem
                        key={option.value}
                        value={option.value}
                        className="gap-2 py-3 md:py-2"
                    >
                        <Icon className="size-4" />
                        {t(option.labelKey)}
                    </DropdownMenuRadioItem>
                );
            })}
        </DropdownMenuRadioGroup>
    );
}

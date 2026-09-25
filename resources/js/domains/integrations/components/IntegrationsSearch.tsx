import { Search } from 'lucide-react';
import { useId } from 'react';
import { useTranslation } from 'react-i18next';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

type Props = {
    value: string;
    onValueChange: (value: string) => void;
};

export function IntegrationsSearch({ value, onValueChange }: Props) {
    const { t } = useTranslation('admin');
    const searchId = useId();

    return (
        <div role="search" className="relative w-full sm:max-w-sm">
            <Label htmlFor={searchId} className="sr-only">
                {t('integrations.search.label')}
            </Label>

            <Search
                aria-hidden="true"
                className="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground"
            />

            <Input
                id={searchId}
                type="search"
                autoComplete="off"
                enterKeyHint="search"
                placeholder={t('integrations.search.placeholder')}
                value={value}
                onChange={(event) => onValueChange(event.target.value)}
                className="h-11 pl-9 text-base md:h-9 md:text-base"
            />
        </div>
    );
}

import { Search } from 'lucide-react';
import { useId } from 'react';
import { useTranslation } from 'react-i18next';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

type Props = {
    search: string;
    onSearchChange: (value: string) => void;
};

export function CustomersToolbar({ search, onSearchChange }: Props) {
    const { t } = useTranslation('admin');
    const searchId = useId();

    return (
        <div className="relative">
            <Label htmlFor={searchId} className="sr-only">
                {t('customers.search.label')}
            </Label>

            <Search
                aria-hidden="true"
                className="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground"
            />

            <Input
                id={searchId}
                type="search"
                autoComplete="off"
                placeholder={t('customers.search.placeholder')}
                value={search}
                onChange={(event) => onSearchChange(event.target.value)}
                className="h-11 pl-9 text-base sm:max-w-sm md:h-9 md:text-base"
            />
        </div>
    );
}

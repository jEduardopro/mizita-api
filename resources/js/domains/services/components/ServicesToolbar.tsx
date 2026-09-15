import { LayoutList, Search, Table2 } from 'lucide-react';
import { useId } from 'react';
import { useTranslation } from 'react-i18next';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { ToggleGroup, ToggleGroupItem } from '@/components/ui/toggle-group';

export const SERVICES_VIEWS = ['table', 'list'] as const;

export type ServicesView = (typeof SERVICES_VIEWS)[number];

function isServicesView(value: string): value is ServicesView {
    return (SERVICES_VIEWS as readonly string[]).includes(value);
}

type Props = {
    search: string;
    onSearchChange: (value: string) => void;
    view: ServicesView;
    onViewChange: (view: ServicesView) => void;
};

export function ServicesToolbar({ search, onSearchChange, view, onViewChange }: Props) {
    const { t } = useTranslation('admin');
    const searchId = useId();

    return (
        <div className="flex flex-col gap-3 sm:flex-row sm:items-center">
            <div className="relative flex-1">
                <Label htmlFor={searchId} className="sr-only">
                    {t('services.search.label')}
                </Label>

                <Search
                    aria-hidden="true"
                    className="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground"
                />

                <Input
                    id={searchId}
                    type="search"
                    autoComplete="off"
                    placeholder={t('services.search.placeholder')}
                    value={search}
                    onChange={(event) => onSearchChange(event.target.value)}
                    className="h-11 pl-9 text-base md:h-9 md:text-base"
                />
            </div>

            <ToggleGroup
                type="single"
                variant="outline"
                spacing={0}
                value={view}
                onValueChange={(value) => {
                    if (isServicesView(value)) {
                        onViewChange(value);
                    }
                }}
                aria-label={t('services.view.label')}
                className="self-end sm:self-auto"
            >
                <ToggleGroupItem
                    value="table"
                    aria-label={t('services.view.table')}
                    className="size-11 md:size-9"
                >
                    <Table2 aria-hidden="true" />
                </ToggleGroupItem>

                <ToggleGroupItem
                    value="list"
                    aria-label={t('services.view.list')}
                    className="size-11 md:size-9"
                >
                    <LayoutList aria-hidden="true" />
                </ToggleGroupItem>
            </ToggleGroup>
        </div>
    );
}

import { Link } from '@inertiajs/react';
import { Info } from 'lucide-react';
import { Trans } from 'react-i18next';

type Props = {
    businessSettingsHref: string;
};

export function BusinessHoursNotice({ businessSettingsHref }: Props) {
    return (
        <p className="flex gap-2.5 rounded-lg bg-muted px-4 py-3 text-sm leading-relaxed text-foreground/80 md:py-2.5">
            <Info aria-hidden="true" className="mt-0.5 size-4 shrink-0" />

            <span>
                <Trans
                    i18nKey="workingHours.notice"
                    ns="admin"
                    components={{
                        brand: (
                            <Link
                                href={businessSettingsHref}
                                className="font-medium text-foreground underline underline-offset-4"
                            />
                        ),
                        section: <strong className="font-medium text-foreground" />,
                    }}
                />
            </span>
        </p>
    );
}

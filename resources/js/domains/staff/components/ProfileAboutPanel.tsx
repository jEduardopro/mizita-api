import { ChevronDown, CircleUserRound, Clock, Lock, Mail, Phone, type LucideIcon } from 'lucide-react';
import type { ReactNode } from 'react';
import { useTranslation } from 'react-i18next';
import { formatPhoneNumber } from '@/lib/phone';
import type { ProfilePhone } from '../types';

const INLINE_ACTION =
    'inline-flex min-h-11 items-center text-left underline underline-offset-4 outline-none hover:text-foreground focus-visible:rounded-sm focus-visible:ring-3 focus-visible:ring-ring/50 md:min-h-9';

type InfoRowProps = {
    icon: LucideIcon;
    label: string;
    children: ReactNode;
};

function InfoRow({ icon: Icon, label, children }: InfoRowProps) {
    return (
        <div className="flex items-start gap-3">
            <dt className="flex h-11 shrink-0 items-center md:h-9">
                <Icon aria-hidden="true" className="size-4 text-muted-foreground" />
                <span className="sr-only">{label}</span>
            </dt>

            <dd className="flex min-h-11 min-w-0 flex-1 flex-col justify-center py-0.5 text-sm break-words md:min-h-9">
                {children}
            </dd>
        </div>
    );
}

type Props = {
    phone: ProfilePhone | null;
    email: string;
    about: string | null;
    roleLabel: string;
    hoursSummary: ReactNode;
    onAddPhone: () => void;
    onAddAbout: () => void;
};

export function ProfileAboutPanel({
    phone,
    email,
    about,
    roleLabel,
    hoursSummary,
    onAddPhone,
    onAddAbout,
}: Props) {
    const { t } = useTranslation('admin');

    return (
        <dl className="grid max-w-2xl gap-2">
            <InfoRow icon={Phone} label={t('profile.about.phone')}>
                {phone === null ? (
                    <button type="button" onClick={onAddPhone} className={INLINE_ACTION}>
                        {t('profile.about.addPhone')}
                    </button>
                ) : (
                    <a href={`tel:${phone.e164}`} className="w-fit underline-offset-4 hover:underline">
                        {formatPhoneNumber(phone)}
                    </a>
                )}
            </InfoRow>

            <InfoRow icon={Mail} label={t('profile.about.email')}>
                <a href={`mailto:${email}`} className="w-fit break-all underline-offset-4 hover:underline">
                    {email}
                </a>
            </InfoRow>

            <InfoRow icon={Clock} label={t('profile.about.hours')}>
                {hoursSummary}
            </InfoRow>

            <InfoRow icon={CircleUserRound} label={t('profile.about.about')}>
                {about === null ? (
                    <button type="button" onClick={onAddAbout} className={INLINE_ACTION}>
                        {t('profile.about.addAbout')}
                    </button>
                ) : (
                    <p className="py-2 whitespace-pre-line text-pretty">{about}</p>
                )}
            </InfoRow>

            <InfoRow icon={Lock} label={t('profile.about.role')}>
                <span className="inline-flex w-fit items-center gap-1.5 text-muted-foreground">
                    {roleLabel}
                    <ChevronDown aria-hidden="true" className="size-3.5 opacity-60" />
                </span>
            </InfoRow>
        </dl>
    );
}

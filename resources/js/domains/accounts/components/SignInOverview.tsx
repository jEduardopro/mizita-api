import { cn } from 'cn';
import { ChevronRight } from 'lucide-react';
import type { ReactNode } from 'react';
import { useTranslation } from 'react-i18next';
import { SettingsPaneBody } from '@/components/admin/settings/SettingsPane';
import { Badge } from '@/components/ui/badge';
import type { TwoFactorStatus } from '../types';
import { SECURITY_BADGE_CLASSES, SECURITY_ON_TONE_CLASSES, TwoFactorStatusBadge } from './TwoFactorStatusBadge';

const ROW_GRID = 'grid gap-1 py-4 sm:grid-cols-[10rem_minmax(0,1fr)] sm:gap-4';

const MASKED_PASSWORD = '••••••••';

type StaticRowProps = {
    label: string;
    children: ReactNode;
};

function StaticRow({ label, children }: StaticRowProps) {
    return (
        <div className={ROW_GRID}>
            <dt className="text-sm text-foreground">{label}</dt>
            <dd className="min-w-0 text-sm break-words text-muted-foreground">{children}</dd>
        </div>
    );
}

type ActionRowProps = {
    label: string;
    actionLabel: string;
    onOpen: () => void;
    children: ReactNode;
};

function ActionRow({ label, actionLabel, onOpen, children }: ActionRowProps) {
    return (
        <div>
            <button
                type="button"
                onClick={onOpen}
                className="flex w-full items-center gap-3 rounded-md text-left outline-none hover:bg-muted/50 focus-visible:ring-3 focus-visible:ring-ring/50"
            >
                <span className={cn(ROW_GRID, 'min-w-0 flex-1')}>
                    <span className="text-sm text-foreground">{label}</span>
                    <span className="grid min-w-0 gap-2 text-sm text-muted-foreground">{children}</span>
                </span>

                <span className="sr-only">{actionLabel}</span>

                <ChevronRight aria-hidden="true" className="size-4 shrink-0 text-muted-foreground" />
            </button>
        </div>
    );
}

type StatusLineProps = {
    label: string;
    isLoading: boolean;
    children: ReactNode;
};

function StatusLine({ label, isLoading, children }: StatusLineProps) {
    return (
        <span className="flex flex-wrap items-center gap-2">
            {label}
            {isLoading ? (
                <span aria-hidden="true" className="inline-block h-5 w-16 animate-pulse rounded-md bg-muted" />
            ) : (
                children
            )}
        </span>
    );
}

function PasskeyStatusBadge({ passkeyCount }: { passkeyCount: number }) {
    const { t } = useTranslation('admin');
    const hasPasskeys = passkeyCount > 0;

    return (
        <Badge variant="secondary" className={cn(SECURITY_BADGE_CLASSES, hasPasskeys && SECURITY_ON_TONE_CLASSES)}>
            {hasPasskeys ? t('security.passkeyCount', { count: passkeyCount }) : t('security.disabled')}
        </Badge>
    );
}

type Props = {
    email: string;
    hasPassword: boolean;
    roleLabel: string;
    twoFactorStatus: TwoFactorStatus | undefined;
    passkeyCount: number | undefined;
    isLoadingMethods: boolean;
    onOpenPassword: () => void;
    onOpenMethods: () => void;
};

export function SignInOverview({
    email,
    hasPassword,
    roleLabel,
    twoFactorStatus,
    passkeyCount,
    isLoadingMethods,
    onOpenPassword,
    onOpenMethods,
}: Props) {
    const { t } = useTranslation('admin');

    return (
        <SettingsPaneBody className="divide-y divide-border">
            <dl className="grid">
                <StaticRow label={t('security.email')}>{email}</StaticRow>
            </dl>

            <ActionRow
                label={t('security.password')}
                actionLabel={hasPassword ? t('security.changePassword') : t('security.createPassword')}
                onOpen={onOpenPassword}
            >
                {hasPassword ? (
                    <span aria-hidden="true" className="tracking-widest">
                        {MASKED_PASSWORD}
                    </span>
                ) : (
                    t('security.passwordNotSet')
                )}
            </ActionRow>

            <ActionRow label={t('security.methods')} actionLabel={t('security.openMethods')} onOpen={onOpenMethods}>
                <StatusLine label={t('security.twoFactor.title')} isLoading={isLoadingMethods}>
                    {twoFactorStatus === undefined ? null : <TwoFactorStatusBadge status={twoFactorStatus} />}
                </StatusLine>

                <StatusLine label={t('security.passkey.title')} isLoading={isLoadingMethods}>
                    {passkeyCount === undefined ? null : <PasskeyStatusBadge passkeyCount={passkeyCount} />}
                </StatusLine>
            </ActionRow>

            <dl className="grid">
                <StaticRow label={t('security.permission')}>{roleLabel}</StaticRow>
            </dl>
        </SettingsPaneBody>
    );
}

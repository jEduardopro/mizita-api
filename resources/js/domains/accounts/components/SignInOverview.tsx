import { cn } from 'cn';
import { ChevronRight } from 'lucide-react';
import type { ReactNode } from 'react';
import { useTranslation } from 'react-i18next';
import { SettingsPaneBody } from '@/components/admin/settings/SettingsPane';
import { Badge } from '@/components/ui/badge';

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
    status: string;
};

function StatusLine({ label, status }: StatusLineProps) {
    return (
        <span className="flex flex-wrap items-center gap-2">
            {label}
            <Badge variant="secondary" className="rounded-md font-normal">
                {status}
            </Badge>
        </span>
    );
}

type Props = {
    email: string;
    hasPassword: boolean;
    roleLabel: string;
    onOpenPassword: () => void;
    onOpenMethods: () => void;
};

export function SignInOverview({ email, hasPassword, roleLabel, onOpenPassword, onOpenMethods }: Props) {
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
                <StatusLine label={t('security.twoFactor.title')} status={t('security.disabled')} />
                <StatusLine label={t('security.passkey.title')} status={t('security.disabled')} />
            </ActionRow>

            <dl className="grid">
                <StaticRow label={t('security.permission')}>{roleLabel}</StaticRow>
            </dl>
        </SettingsPaneBody>
    );
}

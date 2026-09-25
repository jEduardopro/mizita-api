import { Fingerprint, Plus } from 'lucide-react';
import { useId, useState } from 'react';
import { useTranslation } from 'react-i18next';
import { Button } from '@/components/ui/button';
import { supportsPasskeys } from '@/lib/webauthn';
import type { Passkey, SignInSecurity } from '../types';
import { AddPasskeyDialog } from './AddPasskeyDialog';
import { DeletePasskeyDialog } from './DeletePasskeyDialog';
import { PasskeyRow } from './PasskeyRow';
import { PasswordRequiredNotice } from './PasswordRequiredNotice';

type DeletionTarget = Pick<Passkey, 'id' | 'name'>;

function PasskeysEmptyState() {
    const { t } = useTranslation('admin');

    return (
        <div className="grid justify-items-start gap-3 rounded-lg border border-dashed border-border px-4 py-5 sm:grid-cols-[auto_minmax(0,1fr)] sm:gap-x-3">
            <Fingerprint aria-hidden="true" className="size-5 text-muted-foreground sm:mt-0.5" />

            <p className="text-sm text-pretty text-muted-foreground">{t('security.passkey.empty')}</p>
        </div>
    );
}

function PasskeysUnsupportedNotice() {
    const { t } = useTranslation('admin');

    return (
        <p role="note" className="rounded-lg bg-muted px-4 py-3 text-sm text-pretty text-foreground/80">
            {t('security.passkey.unsupported')}
        </p>
    );
}

type PasskeyListProps = {
    passkeys: Passkey[];
    onDelete: (target: DeletionTarget) => void;
};

function PasskeyList({ passkeys, onDelete }: PasskeyListProps) {
    const { t } = useTranslation('admin');
    const headingId = useId();

    if (passkeys.length === 0) {
        return <PasskeysEmptyState />;
    }

    return (
        <div className="grid gap-1">
            <h5 id={headingId} className="text-xs font-medium tracking-wide text-muted-foreground uppercase">
                {t('security.passkey.list')}
            </h5>

            <ul aria-labelledby={headingId} className="divide-y divide-border">
                {passkeys.map((passkey) => (
                    <PasskeyRow
                        key={passkey.id}
                        name={passkey.name}
                        authenticator={passkey.authenticator}
                        createdAt={passkey.created_at}
                        lastUsedAt={passkey.last_used_at}
                        onDelete={() => onDelete({ id: passkey.id, name: passkey.name })}
                    />
                ))}
            </ul>
        </div>
    );
}

type AddPasskeyActionProps = {
    hasPassword: boolean;
    onCreatePassword: () => void;
    onAdd: () => void;
};

function AddPasskeyAction({ hasPassword, onCreatePassword, onAdd }: AddPasskeyActionProps) {
    const { t } = useTranslation('admin');

    if (! supportsPasskeys()) {
        return <PasskeysUnsupportedNotice />;
    }

    if (! hasPassword) {
        return <PasswordRequiredNotice onCreatePassword={onCreatePassword} />;
    }

    return (
        <Button
            type="button"
            variant="outline"
            onClick={onAdd}
            className="h-11 justify-self-start rounded-full px-5 md:h-9"
        >
            <Plus aria-hidden="true" />
            {t('security.passkey.add')}
        </Button>
    );
}

type Props = {
    security: SignInSecurity;
    onCreatePassword: () => void;
};

export function PasskeysSection({ security, onCreatePassword }: Props) {
    const { t } = useTranslation('admin');
    const headingId = useId();
    const [isAddOpen, setAddOpen] = useState(false);
    const [deletionTarget, setDeletionTarget] = useState<DeletionTarget | null>(null);
    const [isDeleteOpen, setDeleteOpen] = useState(false);

    function requestDeletion(target: DeletionTarget) {
        setDeletionTarget(target);
        setDeleteOpen(true);
    }

    return (
        <section aria-labelledby={headingId} className="grid gap-4 py-5 first:pt-1">
            <div className="grid gap-1">
                <h4 id={headingId} className="text-sm font-semibold">
                    {t('security.passkey.title')}
                </h4>

                <p className="text-sm text-pretty text-muted-foreground">{t('security.passkey.body')}</p>
            </div>

            <PasskeyList passkeys={security.passkeys} onDelete={requestDeletion} />

            <AddPasskeyAction
                hasPassword={security.has_password}
                onCreatePassword={onCreatePassword}
                onAdd={() => setAddOpen(true)}
            />

            <AddPasskeyDialog open={isAddOpen} onOpenChange={setAddOpen} />

            {deletionTarget === null ? null : (
                <DeletePasskeyDialog
                    passkeyId={deletionTarget.id}
                    name={deletionTarget.name}
                    open={isDeleteOpen}
                    onOpenChange={setDeleteOpen}
                />
            )}
        </section>
    );
}

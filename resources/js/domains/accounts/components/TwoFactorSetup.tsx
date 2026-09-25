import { cn } from 'cn';
import { ChevronDown, Copy, Smartphone } from 'lucide-react';
import { useEffect, useId, useRef, type ReactNode } from 'react';
import { useTranslation } from 'react-i18next';
import { SettingsPaneBody, SettingsPaneFooter } from '@/components/admin/settings/SettingsPane';
import { OtpField } from '@/components/form/OtpField';
import { SubmitButton } from '@/components/form/SubmitButton';
import { Button } from '@/components/ui/button';
import { Collapsible, CollapsibleContent, CollapsibleTrigger } from '@/components/ui/collapsible';
import { Skeleton } from '@/components/ui/skeleton';
import { ConfirmPasswordDialog } from './ConfirmPasswordDialog';
import { RecoveryCodesPanel } from './RecoveryCodesPanel';
import { useTwoFactorSetup, type SetupMaterial, type TwoFactorSetupController } from './use-two-factor-setup';

const SVG_DATA_URL_PREFIX = 'data:image/svg+xml;utf8,';

const SECRET_KEY_GROUP = /.{1,4}/g;

const ACTION_BUTTON_CLASSES = 'h-11 rounded-full px-5 md:h-9';

const FOOTER_BUTTON_CLASSES = 'h-11 px-5 md:h-9';

const VIEWFINDER_CORNERS = [
    'top-0 left-0 rounded-tl-lg border-t-2 border-l-2',
    'top-0 right-0 rounded-tr-lg border-t-2 border-r-2',
    'bottom-0 left-0 rounded-bl-lg border-b-2 border-l-2',
    'bottom-0 right-0 rounded-br-lg border-r-2 border-b-2',
] as const;

function groupedSecretKey(secretKey: string): string {
    return secretKey.match(SECRET_KEY_GROUP)?.join(' ') ?? secretKey;
}

function QrViewfinder({ children }: { children: ReactNode }) {
    return (
        <div className="relative w-fit p-2">
            {VIEWFINDER_CORNERS.map((corner) => (
                <span key={corner} aria-hidden="true" className={cn('absolute size-5 border-foreground', corner)} />
            ))}

            {children}
        </div>
    );
}

type SecretKeyDisclosureProps = {
    secretKey: string;
    onCopy: () => void;
};

function SecretKeyDisclosure({ secretKey, onCopy }: SecretKeyDisclosureProps) {
    const { t } = useTranslation('admin');
    const labelId = useId();

    return (
        <Collapsible className="grid w-full justify-items-start gap-2">
            <CollapsibleTrigger asChild>
                <Button type="button" variant="ghost" className="-ml-3 h-11 px-3 md:h-9">
                    {t('security.twoFactor.secretKey.toggle')}
                    <ChevronDown
                        aria-hidden="true"
                        className="motion-safe:transition-transform group-data-[state=open]/button:rotate-180"
                    />
                </Button>
            </CollapsibleTrigger>

            <CollapsibleContent className="w-full">
                <div className="grid gap-1.5 rounded-lg bg-muted px-4 py-3">
                    <p id={labelId} className="text-xs font-medium text-muted-foreground">
                        {t('security.twoFactor.secretKey.label')}
                    </p>

                    <div className="flex items-center gap-2">
                        <code
                            aria-labelledby={labelId}
                            className="min-w-0 flex-1 font-mono text-base tracking-wider break-all text-foreground"
                        >
                            {groupedSecretKey(secretKey)}
                        </code>

                        <Button type="button" variant="ghost" onClick={onCopy} className="size-11 shrink-0 p-0 md:size-9">
                            <Copy aria-hidden="true" />
                            <span className="sr-only">{t('security.twoFactor.copy')}</span>
                        </Button>
                    </div>

                    <p className="text-xs text-pretty text-muted-foreground">{t('security.twoFactor.secretKey.hint')}</p>
                </div>
            </CollapsibleContent>
        </Collapsible>
    );
}

type SetupMaterialViewProps = {
    material: SetupMaterial;
    onRetry: () => void;
    onCopySecretKey: () => void;
};

function SetupMaterialView({ material, onRetry, onCopySecretKey }: SetupMaterialViewProps) {
    const { t } = useTranslation('admin');
    const { t: tCommon } = useTranslation('common');

    if (material.status === 'loading') {
        return (
            <div aria-busy="true">
                <p role="status" className="sr-only">
                    {t('security.twoFactor.qr.loading')}
                </p>

                <QrViewfinder>
                    <Skeleton className="size-46 rounded-md" />
                </QrViewfinder>
            </div>
        );
    }

    if (material.status === 'failed') {
        return (
            <div role="alert" className="grid justify-items-start gap-3 rounded-lg bg-muted px-4 py-4">
                <p className="text-sm text-pretty text-foreground/80">{t('security.twoFactor.qr.loadFailed')}</p>

                <Button
                    type="button"
                    variant="outline"
                    onClick={onRetry}
                    disabled={material.isRetrying}
                    aria-busy={material.isRetrying}
                    className={ACTION_BUTTON_CLASSES}
                >
                    {material.isRetrying ? tCommon('actions.retrying') : tCommon('actions.tryAgain')}
                </Button>
            </div>
        );
    }

    return (
        <div className="grid justify-items-start gap-3">
            <QrViewfinder>
                <div className="rounded-md bg-white p-3">
                    <img
                        src={SVG_DATA_URL_PREFIX + encodeURIComponent(material.qrCodeSvg)}
                        alt={t('security.twoFactor.qr.label')}
                        className="block size-40"
                    />
                </div>
            </QrViewfinder>

            {material.authenticatorUrl === null ? null : (
                <Button asChild variant="outline" className={cn(ACTION_BUTTON_CLASSES, 'lg:hidden')}>
                    <a href={material.authenticatorUrl}>
                        <Smartphone aria-hidden="true" />
                        {t('security.twoFactor.openInApp')}
                    </a>
                </Button>
            )}

            <SecretKeyDisclosure secretKey={material.secretKey} onCopy={onCopySecretKey} />
        </div>
    );
}

type SetupStepItemProps = {
    position: number;
    title: string;
    body: string;
    children: ReactNode;
};

function SetupStepItem({ position, title, body, children }: SetupStepItemProps) {
    return (
        <li className="relative grid grid-cols-[1.75rem_minmax(0,1fr)] gap-x-3 gap-y-4 before:absolute before:top-9 before:-bottom-6 before:left-3.5 before:w-px before:bg-border last:before:hidden">
            <span
                aria-hidden="true"
                className="grid size-7 place-items-center rounded-full border border-foreground/15 bg-background text-xs font-semibold text-foreground tabular-nums"
            >
                {position}
            </span>

            <div className="grid gap-1 self-center">
                <h4 className="text-sm font-semibold text-foreground">{title}</h4>
                <p className="text-sm text-pretty text-muted-foreground">{body}</p>
            </div>

            <div className="col-start-2 min-w-0">{children}</div>
        </li>
    );
}

function VerifyStep({ setup }: { setup: TwoFactorSetupController }) {
    const { t } = useTranslation('admin');

    return (
        <form onSubmit={setup.submit} className="flex min-h-0 flex-1 flex-col">
            <SettingsPaneBody>
                <ol className="grid gap-8 pt-1">
                    <SetupStepItem
                        position={1}
                        title={t('security.twoFactor.setup.scan.title')}
                        body={t('security.twoFactor.setup.scan.body')}
                    >
                        <SetupMaterialView
                            material={setup.material}
                            onRetry={setup.retryMaterial}
                            onCopySecretKey={setup.copySecretKey}
                        />
                    </SetupStepItem>

                    <SetupStepItem
                        position={2}
                        title={t('security.twoFactor.setup.verify.title')}
                        body={t('security.twoFactor.setup.verify.body')}
                    >
                        <OtpField
                            id="two-factor-code"
                            label={t('security.twoFactor.code.label')}
                            hint={t('security.twoFactor.code.hint')}
                            value={setup.code}
                            onChange={setup.updateCode}
                            onComplete={setup.submitCode}
                            error={setup.codeError}
                            disabled={setup.isCancelling}
                        />
                    </SetupStepItem>
                </ol>
            </SettingsPaneBody>

            <SettingsPaneFooter className="flex-col-reverse items-stretch sm:flex-row sm:items-center">
                <SubmitButton
                    type="button"
                    variant="ghost"
                    onClick={setup.cancel}
                    disabled={setup.isBusy}
                    isSubmitting={setup.isCancelling}
                    label={t('security.twoFactor.cancelSetup')}
                    submittingLabel={t('security.twoFactor.disabling')}
                    className={FOOTER_BUTTON_CLASSES}
                />

                <SubmitButton
                    variant="brand"
                    label={t('security.twoFactor.confirm')}
                    submittingLabel={t('security.twoFactor.confirming')}
                    isSubmitting={setup.isConfirming}
                    disabled={! setup.canSubmit || setup.isBusy}
                    className={FOOTER_BUTTON_CLASSES}
                />
            </SettingsPaneFooter>
        </form>
    );
}

function RecoveryCodesStep({ onFinished }: { onFinished: () => void }) {
    const { t } = useTranslation('admin');
    const headingRef = useRef<HTMLHeadingElement>(null);

    useEffect(() => {
        headingRef.current?.focus({ preventScroll: true });
    }, []);

    return (
        <>
            <SettingsPaneBody className="grid content-start gap-3">
                <h4 ref={headingRef} tabIndex={-1} className="pt-1 text-sm font-semibold text-foreground outline-none">
                    {t('security.twoFactor.recoveryCodes.title')}
                </h4>

                <RecoveryCodesPanel />
            </SettingsPaneBody>

            <SettingsPaneFooter>
                <Button type="button" variant="brand" onClick={onFinished} className={cn(FOOTER_BUTTON_CLASSES, 'w-full sm:w-auto')}>
                    {t('security.twoFactor.done')}
                </Button>
            </SettingsPaneFooter>
        </>
    );
}

type Props = {
    onCancelled: () => void;
    onFinished: () => void;
};

export function TwoFactorSetup({ onCancelled, onFinished }: Props) {
    const setup = useTwoFactorSetup({ onCancelled });

    return (
        <>
            {setup.step === 'verify' ? <VerifyStep setup={setup} /> : <RecoveryCodesStep onFinished={onFinished} />}

            <ConfirmPasswordDialog {...setup.passwordDialog} />
        </>
    );
}

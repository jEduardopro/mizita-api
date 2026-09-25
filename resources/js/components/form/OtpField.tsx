import { REGEXP_ONLY_DIGITS } from 'input-otp';
import { fieldMessage, FieldMessage } from '@/components/form/FieldMessage';
import { InputOTP, InputOTPGroup, InputOTPSlot } from '@/components/ui/input-otp';
import { Label } from '@/components/ui/label';

export const OTP_LENGTH = 6;

const SLOT_INDEXES = Array.from({ length: OTP_LENGTH }, (_, index) => index);

type Props = {
    id: string;
    label: string;
    value: string;
    onChange: (value: string) => void;
    onComplete?: (value: string) => void;
    error?: string;
    hint?: string;
    autoFocus?: boolean;
    disabled?: boolean;
};

export function OtpField({ id, label, value, onChange, onComplete, error, hint, autoFocus, disabled }: Props) {
    const message = fieldMessage({ id, error, hint });
    const isInvalid = !! error;

    return (
        <div className="grid gap-2">
            <Label htmlFor={id}>{label}</Label>

            <InputOTP
                id={id}
                maxLength={OTP_LENGTH}
                pattern={REGEXP_ONLY_DIGITS}
                inputMode="numeric"
                autoComplete="one-time-code"
                autoFocus={autoFocus}
                disabled={disabled}
                value={value}
                onChange={onChange}
                onComplete={onComplete}
                aria-invalid={isInvalid}
                aria-describedby={message?.id}
                containerClassName="w-full max-w-72"
            >
                <InputOTPGroup className="w-full">
                    {SLOT_INDEXES.map((index) => (
                        <InputOTPSlot
                            key={index}
                            index={index}
                            aria-invalid={isInvalid}
                            className="h-12 min-w-0 flex-1 text-lg tabular-nums"
                        />
                    ))}
                </InputOTPGroup>
            </InputOTP>

            <FieldMessage message={message} />
        </div>
    );
}

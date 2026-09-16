import { cn } from 'cn';
import { ImagePlus, X } from 'lucide-react';
import { useRef, useState, type DragEvent } from 'react';
import { fieldMessage, FieldMessage } from '@/components/form/FieldMessage';
import { Button, buttonVariants } from '@/components/ui/button';

export type ImageFieldMessages = {
    choose: string;
    upload: string;
    replace: string;
    remove: string;
    tooLarge: string;
    unsupported: string;
};

type Props = {
    id: string;
    label: string;
    shownUrl: string | null;
    onSelect: (file: File) => void;
    onClear: () => void;
    accept: readonly string[];
    maximumBytes: number;
    messages: ImageFieldMessages;
    error?: string;
    hint?: string;
    tileClassName?: string;
};

export function ImageField({
    id,
    label,
    shownUrl,
    onSelect,
    onClear,
    accept,
    maximumBytes,
    messages,
    error,
    hint,
    tileClassName,
}: Props) {
    const inputRef = useRef<HTMLInputElement>(null);
    const [rejection, setRejection] = useState<string | null>(null);

    const message = fieldMessage({ id, error: error ?? rejection ?? undefined, hint });

    function selectFile(candidate: File | undefined) {
        if (inputRef.current) {
            inputRef.current.value = '';
        }

        if (candidate === undefined) {
            return;
        }

        if (! accept.includes(candidate.type)) {
            setRejection(messages.unsupported);

            return;
        }

        if (candidate.size > maximumBytes) {
            setRejection(messages.tooLarge);

            return;
        }

        setRejection(null);
        onSelect(candidate);
    }

    function drop(event: DragEvent<HTMLLabelElement>) {
        event.preventDefault();
        selectFile(event.dataTransfer.files[0]);
    }

    return (
        <div className="grid gap-2">
            <span id={`${id}-label`} className="text-sm leading-none font-medium">
                {label}
            </span>

            <div className="flex flex-wrap items-start gap-3">
                <input
                    ref={inputRef}
                    id={id}
                    type="file"
                    accept={accept.join(',')}
                    aria-labelledby={`${id}-label`}
                    aria-describedby={message?.id}
                    onChange={(event) => selectFile(event.target.files?.[0])}
                    className="peer sr-only"
                />

                <label
                    htmlFor={id}
                    onDragOver={(event) => event.preventDefault()}
                    onDrop={drop}
                    className={cn(
                        'relative flex shrink-0 cursor-pointer items-center justify-center overflow-hidden rounded-xl border border-dashed border-input bg-muted/40 text-center transition-colors',
                        tileClassName ?? 'size-50',
                        'hover:border-ring peer-focus-visible:border-ring peer-focus-visible:ring-3 peer-focus-visible:ring-ring/50',
                        message?.tone === 'critical' ? 'border-destructive' : undefined,
                    )}
                >
                    {shownUrl ? (
                        <img
                            src={shownUrl}
                            alt=""
                            className="absolute inset-0 size-full object-cover"
                        />
                    ) : (
                        <span className="grid justify-items-center gap-2 px-4 text-xs text-balance text-muted-foreground">
                            <ImagePlus aria-hidden="true" className="size-6" />
                            {messages.choose}
                        </span>
                    )}
                </label>

                <span className="flex min-w-28 flex-1 flex-col items-start gap-2">
                    <FieldMessage message={message} />

                    {shownUrl ? null : (
                        <label
                            htmlFor={id}
                            className={cn(
                                buttonVariants({ variant: 'outline' }),
                                'h-11 cursor-pointer px-3 sm:hidden',
                            )}
                        >
                            <ImagePlus aria-hidden="true" />
                            {messages.upload}
                        </label>
                    )}
                </span>
            </div>

            {shownUrl ? (
                <div className="flex flex-wrap gap-2">
                    <Button
                        type="button"
                        variant="outline"
                        onClick={() => inputRef.current?.click()}
                        className="h-11 px-4 md:h-9"
                    >
                        {messages.replace}
                    </Button>

                    <Button
                        type="button"
                        variant="ghost"
                        onClick={() => {
                            setRejection(null);
                            onClear();
                        }}
                        className="h-11 px-4 md:h-9"
                    >
                        <X aria-hidden="true" />
                        {messages.remove}
                    </Button>
                </div>
            ) : null}
        </div>
    );
}

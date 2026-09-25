const PLAIN_TEXT = 'text/plain';

function supportsDeferredClipboardWrite(): boolean {
    return typeof ClipboardItem !== 'undefined' && typeof navigator.clipboard?.write === 'function';
}

export function copyPendingText(pendingText: Promise<string>): Promise<void> {
    if (! supportsDeferredClipboardWrite()) {
        return pendingText.then((text) => navigator.clipboard.writeText(text));
    }

    const blob = pendingText.then((text) => new Blob([text], { type: PLAIN_TEXT }));

    return navigator.clipboard.write([new ClipboardItem({ [PLAIN_TEXT]: blob })]);
}

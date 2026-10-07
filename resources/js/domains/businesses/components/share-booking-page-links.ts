const FACEBOOK_SHARER_URL = 'https://www.facebook.com/sharer/sharer.php';

const WHATSAPP_SHARE_URL = 'https://wa.me/';

const MESSENGER_SHARE_URL = 'fb-messenger://share';

export function emailShareHref(subject: string, body: string): string {
    return `mailto:?subject=${encodeURIComponent(subject)}&body=${encodeURIComponent(body)}`;
}

export function facebookShareHref(url: string): string {
    return `${FACEBOOK_SHARER_URL}?u=${encodeURIComponent(url)}`;
}

export function whatsappShareHref(message: string): string {
    return `${WHATSAPP_SHARE_URL}?text=${encodeURIComponent(message)}`;
}

export function messengerShareHref(url: string): string {
    return `${MESSENGER_SHARE_URL}?link=${encodeURIComponent(url)}`;
}

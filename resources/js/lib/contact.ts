import { usePage } from '@inertiajs/react';

export type ContactInfo = {
    whatsapp: string;
    whatsappDisplay: string;
    email: string;
};

// Mirrors config/contact.php's defaults. Used only as a safety net for pages
// rendered outside the normal Inertia middleware pipeline (e.g. the 404 page,
// rendered directly from the exception handler in bootstrap/app.php), where
// HandleInertiaRequests::share() never runs and `contact` isn't shared.
const FALLBACK_CONTACT: ContactInfo = {
    whatsapp: '5551998931325',
    whatsappDisplay: '(51) 99893-1325',
    email: 'contato@abrasilsistemas.com.br',
};

/**
 * Reads the ABrasil contact info (WhatsApp number, e-mail) shared globally
 * via Inertia (see HandleInertiaRequests::share()) instead of hardcoding it
 * across every public page/component.
 */
export function useContact(): ContactInfo {
    return usePage<{ contact?: ContactInfo }>().props.contact ?? FALLBACK_CONTACT;
}

/**
 * Builds a wa.me link from the shared WhatsApp number and a page-specific
 * pre-filled message.
 */
export function buildWhatsappUrl(whatsappNumber: string, message: string): string {
    return `https://wa.me/${whatsappNumber}?text=${encodeURIComponent(message)}`;
}

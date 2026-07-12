import type { LucideIcon } from 'lucide-vue-next';

export interface Auth {
    user: User;
}

export interface BreadcrumbItem {
    title: string;
    href: string;
}

export interface NavItem {
    title: string;
    href: string;
    icon?: LucideIcon;
    isActive?: boolean;
    /**
     * Set for routes that don't return an Inertia response (e.g. reverse-proxied
     * to a non-Inertia backend like traffic-monitor-service). Inertia's <Link>
     * only forces a full-page reload for a 409 + X-Inertia-Location response;
     * an ordinary 200 HTML response instead surfaces as an "invalid response"
     * overlay and the URL never actually changes. A plain <a> sidesteps this
     * by letting the browser navigate normally, no XHR interception at all.
     */
    external?: boolean;
}

export interface SharedData {
    name: string;
    quote: { message: string; author: string };
    auth: Auth;
    ziggy: {
        location: string;
        url: string;
        port: null | number;
        defaults: Record<string, unknown>;
        routes: Record<string, string>;
    };
}

export interface User {
    id: number;
    name: string;
    email: string;
    avatar?: string;
    email_verified_at: string | null;
    created_at: string;
    updated_at: string;
}

export type BreadcrumbItemType = BreadcrumbItem;

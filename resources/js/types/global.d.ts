import type { NavCategory } from '@/components/storefront/CategoryStripe.vue';
import type { Auth } from '@/types/auth';

/** One configured social profile, shared on every storefront response. */
export interface SocialLink {
    icon: string;
    label: string;
    url: string;
}

// Extend ImportMeta interface for Vite...
declare module 'vite/client' {
    interface ImportMetaEnv {
        readonly VITE_APP_NAME: string;
        [key: string]: string | boolean | undefined;
    }

    interface ImportMeta {
        readonly env: ImportMetaEnv;
        readonly glob: <T>(pattern: string) => Record<string, () => Promise<T>>;
    }
}

declare module '@inertiajs/core' {
    export interface InertiaConfig {
        sharedPageProps: {
            name: string;
            auth: Auth;
            sidebarOpen: boolean;
            storefront: {
                navCategories: NavCategory[];
                /**
                 * The store's social profiles, from SocialSettings. Only the
                 * ones actually configured are sent, so the footer never
                 * renders a dead icon. `icon` keys the inline brand mark in
                 * StoreFooter — Lucide ships UI glyphs only, no brand marks.
                 */
                socialLinks: SocialLink[];
                /**
                 * Read out of the session by HandleInertiaRequests, so it is
                 * present on every storefront response. The id lists are what
                 * let a save / compare control pick `store` or `destroy`
                 * without a round trip.
                 */
                shopper: App.Data.ShopperStateData;
            };
            [key: string]: unknown;
        };
    }
}

declare module 'vue' {
    interface ComponentCustomProperties {
        $inertia: typeof Router;
        $page: Page;
        $headManager: ReturnType<typeof createHeadManager>;
    }
}

import { usePage } from '@inertiajs/vue3';
import { computed, type ComputedRef } from 'vue';

/**
 * The class a portalled overlay needs to keep the storefront's palette.
 *
 * Dialog, Sheet and friends teleport their content to `document.body`, which is
 * outside the `.storefront` wrapper StoreShell puts on the page. Inside that
 * wrapper the brand tokens are remapped and every `dark:` variant is
 * neutralised; outside it, neither applies — so a portalled dialog on a
 * storefront page renders in the raw shadcn palette, and goes fully dark for
 * any visitor whose OS prefers dark, on an otherwise-light page.
 *
 * Components that live on BOTH sides of the app cannot simply hardcode
 * `class="storefront"`: the staff side is legitimately dark-capable, and forcing
 * it light there would be the same bug in reverse. This resolves per request
 * from the shared `auth.isStaff` prop, so one component serves both.
 *
 * A component that only ever renders inside the storefront should state
 * `class="storefront ..."` directly instead — this exists for the shared ones.
 */
export function usePortalTheme(): ComputedRef<string | undefined> {
    const page = usePage();

    return computed(() =>
        page.props.auth?.isStaff ? undefined : 'storefront',
    );
}

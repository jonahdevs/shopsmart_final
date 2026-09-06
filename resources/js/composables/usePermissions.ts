import { usePage } from '@inertiajs/vue3';
import { computed } from 'vue';

/**
 * Reads the signed-in staff member's permissions off the shared page props.
 *
 * This is for rendering, never for protection. Every admin route carries its
 * own `can:` middleware, and that is what actually refuses a request — hiding a
 * link the server would reject is a courtesy to the staff member, not a
 * security boundary. A page that only hides its button is not protected.
 */
export function usePermissions() {
    const page = usePage();

    const permissions = computed<string[]>(() => page.props.auth?.permissions ?? []);

    /** Whether the user holds every one of the given permissions. */
    function can(...required: string[]): boolean {
        return required.every((permission) => permissions.value.includes(permission));
    }

    /** Whether the user holds at least one of the given permissions. */
    function canAny(...required: string[]): boolean {
        return required.some((permission) => permissions.value.includes(permission));
    }

    return { permissions, can, canAny };
}

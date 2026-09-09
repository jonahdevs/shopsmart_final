<script setup lang="ts">
import { usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import AccountLayout from '@/layouts/account/AccountLayout.vue';
import AdminLayout from '@/layouts/admin/AdminLayout.vue';
import SettingsLayout from '@/layouts/settings/Layout.vue';
import StorefrontLayout from '@/layouts/StorefrontLayout.vue';
import type { BreadcrumbItem } from '@/types';

/**
 * Which chrome the settings pages wear.
 *
 * `settings/Profile`, `settings/Security` and `settings/Appearance` are shared
 * by everyone with a login, but the two audiences arrive from opposite
 * directions: staff step sideways out of the admin sidebar, and a customer
 * clicks "Profile" in their own account nav. Dropping a shopper into the admin
 * shell to change their name is the same mistake as dropping a staff member
 * into the storefront to change their password.
 *
 * So the switch in app.ts resolves one component — this one — and the branch
 * happens here, on the `auth.isStaff` flag HandleInertiaRequests shares on every
 * response. The pages themselves are untouched and unaware.
 *
 * The staff branch is AdminLayout, the same shell the thirty-four admin screens
 * wear. It has to be: a staff member steps into settings from the admin rail and
 * expects to step back out of it, and a second sidebar that happens to hold the
 * same links is just a way for the two to drift apart.
 *
 * Appearance is deliberately absent from the customer nav: dark mode is a staff
 * affordance and the storefront is always light. A customer who reaches the page
 * by URL still gets a working page, just not an invitation to it.
 */
const { breadcrumbs = [] } = defineProps<{
    /**
     * Declared so the `defineOptions({ layout: { breadcrumbs } })` the settings
     * pages already set lands on a prop instead of falling through to `$attrs`
     * and being stamped onto the shell's root element.
     */
    breadcrumbs?: BreadcrumbItem[];
}>();

const page = usePage();

const isStaff = computed(() => page.props.auth.isStaff === true);

/**
 * The staff trail, restated in the storefront's shape.
 *
 * StoreBreadcrumbs reads a null slug as a root rung, and AccountLayout takes the
 * last rung as its heading — so the pages' own "Profile settings" title carries
 * across without either side knowing about the other.
 */
const storeBreadcrumbs = computed<App.Data.BreadcrumbData[]>(() => [
    { name: 'Home', slug: null },
    ...breadcrumbs.map((item) => ({ name: item.title, slug: null })),
]);
</script>

<template>
    <AdminLayout v-if="isStaff" :breadcrumbs="breadcrumbs">
        <SettingsLayout>
            <slot />
        </SettingsLayout>
    </AdminLayout>

    <StorefrontLayout v-else>
        <AccountLayout :breadcrumbs="storeBreadcrumbs">
            <!--
              A measure the staff side no longer needs: there is no sub-nav
              column out here to take the width, so a card left to fill the
              account page would carry an unreadable full-width text input.
            -->
            <div class="flex max-w-xl flex-col gap-6">
                <slot />
            </div>
        </AccountLayout>
    </StorefrontLayout>
</template>

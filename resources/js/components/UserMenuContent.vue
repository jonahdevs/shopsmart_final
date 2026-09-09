<script setup lang="ts">
import { Link, router, usePage } from '@inertiajs/vue3';
import { ExternalLink, LogOut, Settings } from '@lucide/vue';
import { computed } from 'vue';
import {
    DropdownMenuGroup,
    DropdownMenuItem,
    DropdownMenuLabel,
    DropdownMenuSeparator,
} from '@/components/ui/dropdown-menu';
import UserInfo from '@/components/UserInfo.vue';
import { home, logout } from '@/routes';
import { edit } from '@/routes/profile';
import type { User } from '@/types';

type Props = {
    user: User;
};

const page = usePage();

/**
 * "View storefront" is a staff errand. It moved here out of the admin header
 * bar, where it was one icon button in a row of controls, and it is gated
 * rather than shown to everyone because this menu is not admin-only: a
 * customer reading it is already on the storefront, and a link back to where
 * they are standing is noise.
 */
const isStaff = computed(() => page.props.auth.isStaff);

const handleLogout = () => {
    router.flushAll();
};

defineProps<Props>();
</script>

<template>
    <DropdownMenuLabel class="p-0 font-normal">
        <div class="flex items-center gap-2 px-1 py-1.5 text-left text-sm">
            <UserInfo :user="user" :show-email="true" />
        </div>
    </DropdownMenuLabel>
    <DropdownMenuSeparator />
    <DropdownMenuGroup>
        <DropdownMenuItem :as-child="true">
            <Link class="block w-full cursor-pointer" :href="edit()" prefetch>
                <Settings class="mr-2 h-4 w-4" />
                Settings
            </Link>
        </DropdownMenuItem>

        <DropdownMenuItem v-if="isStaff" :as-child="true">
            <a
                class="block w-full cursor-pointer"
                :href="home().url"
                target="_blank"
                rel="noopener"
            >
                <ExternalLink class="mr-2 h-4 w-4" />
                View storefront
                <span class="sr-only">(opens in a new tab)</span>
            </a>
        </DropdownMenuItem>
    </DropdownMenuGroup>
    <DropdownMenuSeparator />
    <DropdownMenuItem :as-child="true">
        <Link
            class="block w-full cursor-pointer"
            :href="logout()"
            @click="handleLogout"
            as="button"
            data-test="logout-button"
        >
            <LogOut class="mr-2 h-4 w-4" />
            Log out
        </Link>
    </DropdownMenuItem>
</template>

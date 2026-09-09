<script setup lang="ts">
import { usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import UserMenuContent from '@/components/UserMenuContent.vue';
import { useInitials } from '@/composables/useInitials';

/**
 * The signed-in staff member, at the right end of the header bar.
 *
 * It used to be a full-width row in the sidebar footer. Both reference builds
 * put it in the top bar instead, and they are right: the rail is for going
 * places, and "who am I signed in as" is not a place. Moving it also means the
 * account menu does not disappear when the rail is collapsed.
 *
 * The trigger is the avatar alone — the name is in the menu it opens, and a
 * header bar is the wrong place to spend horizontal room restating it.
 * {@see UserMenuContent} is shared with nothing else now, but stays a separate
 * component so the menu's contents are decided in one place if the storefront
 * ever grows the same control.
 */
const page = usePage();
const user = computed(() => page.props.auth.user);

const { getInitials } = useInitials();

const hasAvatar = computed(
    () => user.value.avatar !== null && user.value.avatar !== '',
);
</script>

<template>
    <DropdownMenu>
        <DropdownMenuTrigger
            class="focus-visible:outline-ring rounded-full focus-visible:outline-2 focus-visible:outline-offset-2"
            data-test="user-menu-button"
            :aria-label="`Account menu for ${user.name}`"
        >
            <Avatar class="size-8">
                <AvatarImage
                    v-if="hasAvatar"
                    :src="user.avatar!"
                    :alt="user.name"
                />
                <AvatarFallback class="text-xs font-semibold">
                    {{ getInitials(user.name) }}
                </AvatarFallback>
            </Avatar>
        </DropdownMenuTrigger>

        <DropdownMenuContent class="min-w-56 rounded-lg" align="end">
            <UserMenuContent :user="user" />
        </DropdownMenuContent>
    </DropdownMenu>
</template>

<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { House } from '@lucide/vue';
import {
    Breadcrumb,
    BreadcrumbItem,
    BreadcrumbLink,
    BreadcrumbList,
    BreadcrumbPage,
    BreadcrumbSeparator,
} from '@/components/ui/breadcrumb';
import type { BreadcrumbItem as BreadcrumbItemType } from '@/types';

/**
 * The admin trail, above the page title.
 *
 * The first rung is the dashboard on every screen — every page's
 * `defineOptions({ layout: { breadcrumbs } })` opens with it — so it carries a
 * house. That is decided here rather than passed per page: thirty-odd pages
 * each declaring the same icon is thirty chances for one of them to forget, and
 * the trail's root is a property of the trail, not of the page.
 *
 * The label stays beside it. An icon alone would be a second, smaller way of
 * saying what the rail's own Dashboard row already says, and a reader who has
 * not learnt the house yet would have nothing to read.
 */
type Props = {
    breadcrumbs: BreadcrumbItemType[];
};

defineProps<Props>();
</script>

<template>
    <Breadcrumb>
        <BreadcrumbList>
            <template v-for="(item, index) in breadcrumbs" :key="index">
                <BreadcrumbItem>
                    <template v-if="index === breadcrumbs.length - 1">
                        <BreadcrumbPage
                            class="inline-flex items-center gap-1.5"
                        >
                            <House
                                v-if="index === 0"
                                class="size-3.5 shrink-0"
                                aria-hidden="true"
                            />
                            {{ item.title }}
                        </BreadcrumbPage>
                    </template>
                    <template v-else>
                        <BreadcrumbLink as-child>
                            <Link
                                :href="item.href"
                                class="inline-flex items-center gap-1.5"
                            >
                                <House
                                    v-if="index === 0"
                                    class="size-3.5 shrink-0"
                                    aria-hidden="true"
                                />
                                {{ item.title }}
                            </Link>
                        </BreadcrumbLink>
                    </template>
                </BreadcrumbItem>
                <BreadcrumbSeparator v-if="index !== breadcrumbs.length - 1" />
            </template>
        </BreadcrumbList>
    </Breadcrumb>
</template>

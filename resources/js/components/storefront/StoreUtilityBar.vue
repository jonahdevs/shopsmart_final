<script setup lang="ts">
import { CircleQuestionMark, CreditCard, MapPin, RefreshCw } from '@lucide/vue';

/**
 * The trust strip that opens the page.
 *
 * Four standing promises about delivery, payment, returns and support. They are
 * statements, not destinations — none of them has a page in this build, so none
 * of them is a link. The strip is decoration for the narrow viewports that
 * cannot spare 32px, hence `hidden sm:block`.
 */
const promises = [
    { key: 'delivery', icon: MapPin, label: 'Delivering across Kenya' },
    { key: 'payment', icon: CreditCard, label: 'Pay with M-Pesa or card' },
    { key: 'returns', icon: RefreshCw, label: 'Easy returns' },
    { key: 'support', icon: CircleQuestionMark, label: 'Help & Support' },
] as const;
</script>

<template>
    <div class="bg-utility hidden sm:block">
        <ul class="container flex items-center justify-between gap-4 py-2">
            <li
                v-for="promise in promises"
                :key="promise.key"
                class="flex items-center gap-2 text-[11px] leading-4 whitespace-nowrap text-white/55"
            >
                <!--
                  Warm amber, deliberately NOT `--star`: that token is reserved
                  for ratings and spending it here would blunt the one signal a
                  shopper scans a listing for.
                -->
                <component
                    :is="promise.icon"
                    class="size-3.5 shrink-0 text-amber-400"
                    aria-hidden="true"
                />
                {{ promise.label }}
            </li>
        </ul>
    </div>
</template>

<script setup lang="ts">
import { computed } from 'vue';
import { cn } from '@/lib/utils';

/**
 * The storefront's signature element.
 *
 * Price is the most-scanned thing on a price-competitive marketplace, so it is
 * where the identity is spent: the currency is demoted to a small bold unit
 * sitting on the amount's baseline, and the amount is set in the heavy display
 * face with tabular figures, so columns of prices align down a grid. The sale
 * colour appears here and nowhere else in the interface.
 *
 * Amounts arrive preformatted from the server (`money()`), which joins the
 * symbol and the number with a single space — that is what lets this split the
 * unit off the amount without knowing anything about currency rules. The
 * currency's `symbol_position` setting decides which side the symbol lands on
 * (`KES 24,000` or `24,000 KES`), so which token is the unit is worked out from
 * the content rather than assumed to be the first.
 */
const {
    formatted,
    compareFormatted = null,
    discountPercent = null,
    size = 'md',
} = defineProps<{
    formatted: string | null;
    compareFormatted?: string | null;
    discountPercent?: number | null;
    size?: 'sm' | 'md' | 'lg';
}>();

const parts = computed(() => {
    if (!formatted) {
        return null;
    }

    const first = formatted.indexOf(' ');

    if (first === -1) {
        return { unit: null, amount: formatted, unitLeads: true };
    }

    /*
      A leading token carrying a digit is the amount, so the unit trails it.
      Splitting on the *last* space also keeps a space-grouped amount intact.
    */
    if (/\d/.test(formatted.slice(0, first))) {
        const last = formatted.lastIndexOf(' ');

        return {
            unit: formatted.slice(last + 1),
            amount: formatted.slice(0, last),
            unitLeads: false,
        };
    }

    return {
        unit: formatted.slice(0, first),
        amount: formatted.slice(first + 1),
        unitLeads: true,
    };
});

const amountSize = computed(
    () =>
        ({
            sm: 'text-lg',
            md: 'text-2xl',
            lg: 'text-4xl sm:text-5xl',
        })[size],
);

/**
 * Sized in `em` so the unit tracks whichever amount size it is sitting next to
 * rather than needing a scale of its own.
 */
const UNIT_CLASS =
    'text-[0.62em] font-bold tracking-[0.02em] uppercase opacity-80';
</script>

<template>
    <div v-if="parts" class="flex flex-wrap items-baseline gap-x-2 gap-y-1">
        <p
            :class="
                cn(
                    'font-display text-ink flex items-baseline gap-1 leading-none font-extrabold tracking-[-0.02em] tabular-nums',
                    amountSize,
                )
            "
        >
            <span v-if="parts.unit && parts.unitLeads" :class="UNIT_CLASS">
                {{ parts.unit }}
            </span>
            <span>{{ parts.amount }}</span>
            <span v-if="parts.unit && !parts.unitLeads" :class="UNIT_CLASS">
                {{ parts.unit }}
            </span>
        </p>

        <!--
          The struck original only appears where the caller has decided there is
          a real discount; two identical prices side by side would be noise.
        -->
        <span
            v-if="compareFormatted"
            class="text-muted-foreground text-sm tabular-nums line-through"
        >
            {{ compareFormatted }}
        </span>

        <span
            v-if="discountPercent"
            class="bg-sale font-display rounded-full px-2 py-0.5 text-[0.6875rem] leading-4 font-bold text-white tabular-nums"
        >
            &minus;{{ discountPercent }}%
        </span>
    </div>

    <!-- No price set: the product is sold on request rather than off the shelf. -->
    <p
        v-else
        class="font-display text-muted-foreground text-sm font-bold tracking-[-0.01em]"
    >
        Price on request
    </p>
</template>

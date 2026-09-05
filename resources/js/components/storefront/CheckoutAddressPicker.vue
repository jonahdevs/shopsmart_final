<script setup lang="ts">
import { Check, Plus } from '@lucide/vue';
import { useId } from 'vue';
import InputError from '@/components/InputError.vue';

/**
 * Where this order is going.
 *
 * The radios are grouped under a name of their own and the id that is actually
 * posted travels in a hidden field. That is deliberate: "use a new address" has
 * to sit in the same keyboard group as the saved ones to be reachable with the
 * arrow keys, but it is not an address id and must never be posted as one —
 * when it is chosen nothing is sent, and the server's own "choose where this
 * order should be delivered" rule is what answers.
 */
const { addresses, error = undefined } = defineProps<{
    addresses: App.Data.AddressData[];
    /** The enclosing form's `address_id` error, if it has one. */
    error?: string;
}>();

/** The chosen address id, or `new` while the shopper is typing a fresh one. */
const selection = defineModel<number | 'new'>({ required: true });

const id = useId();
</script>

<template>
    <div class="flex flex-col gap-4">
        <input
            v-if="typeof selection === 'number'"
            type="hidden"
            name="address_id"
            :value="selection"
        />

        <p v-if="addresses.length === 0" class="text-muted-foreground text-sm">
            You have no saved addresses yet. Fill in the one below and we will
            keep it for next time.
        </p>

        <ul v-else class="flex flex-col gap-3">
            <li
                v-for="address in addresses"
                :key="address.id ?? address.summary"
            >
                <label
                    :for="`${id}-address-${address.id}`"
                    class="flex cursor-pointer items-start gap-3 rounded-xs border p-4 transition-colors"
                    :class="
                        selection === address.id
                            ? 'border-ink bg-card'
                            : 'border-rule hover:border-ink'
                    "
                >
                    <input
                        :id="`${id}-address-${address.id}`"
                        v-model="selection"
                        type="radio"
                        name="address_choice"
                        :value="address.id"
                        class="accent-electric focus-visible:outline-electric mt-0.5 size-4 shrink-0 focus-visible:outline-2 focus-visible:outline-offset-2"
                    />

                    <span class="min-w-0 flex-1 text-sm">
                        <span
                            class="flex flex-wrap items-center gap-x-2 gap-y-1"
                        >
                            <span class="text-foreground font-medium">
                                {{ address.fullName }}
                            </span>
                            <span
                                v-if="address.label"
                                class="font-display text-muted-foreground text-[0.6875rem] font-bold tracking-[0.12em] uppercase"
                            >
                                {{ address.label }}
                            </span>
                            <span
                                v-if="address.isDefault"
                                class="text-electric flex items-center gap-1 text-xs"
                            >
                                <Check class="size-3.5" aria-hidden="true" />
                                Default
                            </span>
                        </span>
                        <span
                            class="text-muted-foreground mt-1 block leading-relaxed"
                        >
                            {{ address.summary }}
                        </span>
                        <span
                            v-if="address.phone"
                            class="text-muted-foreground mt-1 block tabular-nums"
                        >
                            {{ address.phone }}
                        </span>
                    </span>
                </label>
            </li>
        </ul>

        <label
            v-if="addresses.length > 0"
            :for="`${id}-address-new`"
            class="flex cursor-pointer items-center gap-3 rounded-xs border border-dashed p-4 text-sm transition-colors"
            :class="
                selection === 'new'
                    ? 'border-ink bg-card'
                    : 'border-rule hover:border-ink'
            "
        >
            <input
                :id="`${id}-address-new`"
                v-model="selection"
                type="radio"
                name="address_choice"
                value="new"
                class="accent-electric focus-visible:outline-electric size-4 shrink-0 focus-visible:outline-2 focus-visible:outline-offset-2"
            />
            <span class="text-foreground flex items-center gap-2">
                <Plus class="size-4" aria-hidden="true" />
                Use a new address
            </span>
        </label>

        <InputError :message="error" />
    </div>
</template>

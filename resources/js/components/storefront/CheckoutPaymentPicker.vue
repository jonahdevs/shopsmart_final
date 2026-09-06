<script setup lang="ts">
import { Banknote, CreditCard, Landmark } from '@lucide/vue';
import { useId } from 'vue';
import InputError from '@/components/InputError.vue';

/**
 * How this order gets paid for.
 *
 * A real choice, not a summary: the value travels with the order and decides
 * where the shopper lands afterwards — Paystack sends them to the payment page,
 * the offline methods leave instructions on the order itself.
 *
 * The radios sit outside the order form in the markup and rejoin it through the
 * HTML5 `form` attribute, the same way the order note does, so the checkout
 * column can keep its reading order without nesting forms. The list is the
 * server's — a method that is switched off is neither offered here nor accepted
 * by {@see \App\Http\Requests\Shop\PlaceOrderRequest}.
 */
defineProps<{
    methods: { value: string; label: string; description: string }[];
    /** The id of the form these controls belong to. */
    form: string;
    /** The enclosing form's `payment_method` error, if it has one. */
    error?: string;
}>();

const selection = defineModel<string>({ required: true });

const id = useId();

/** Decoration only — the label beside it is what names the method. */
const ICONS: Record<string, typeof CreditCard> = {
    paystack: CreditCard,
    bank_transfer: Landmark,
    cash_on_delivery: Banknote,
};
</script>

<template>
    <div class="flex flex-col gap-4">
        <p v-if="methods.length === 0" class="text-muted-foreground text-sm">
            No payment method is available right now. Please get in touch and we
            will take it from here.
        </p>

        <ul v-else class="flex flex-col gap-3">
            <li v-for="method in methods" :key="method.value">
                <label
                    :for="`${id}-${method.value}`"
                    class="shadow-card hover:shadow-card-hover focus-within:shadow-card-hover flex cursor-pointer items-start gap-3 rounded-lg border p-4 transition-shadow duration-200"
                    :class="
                        selection === method.value
                            ? 'border-electric bg-tint'
                            : 'border-rule bg-white'
                    "
                >
                    <input
                        :id="`${id}-${method.value}`"
                        v-model="selection"
                        type="radio"
                        name="payment_method"
                        :form="form"
                        :value="method.value"
                        class="accent-electric focus-visible:outline-electric mt-0.5 size-4 shrink-0 focus-visible:outline-2 focus-visible:outline-offset-2"
                    />

                    <span class="min-w-0 flex-1 text-sm">
                        <span
                            class="text-foreground flex items-center gap-2 font-medium"
                        >
                            <component
                                :is="ICONS[method.value]"
                                v-if="ICONS[method.value]"
                                class="text-muted-foreground size-4 shrink-0"
                                aria-hidden="true"
                            />
                            {{ method.label }}
                        </span>
                        <span
                            class="text-muted-foreground mt-1 block leading-relaxed"
                        >
                            {{ method.description }}
                        </span>
                    </span>
                </label>
            </li>
        </ul>

        <InputError :message="error" />
    </div>
</template>

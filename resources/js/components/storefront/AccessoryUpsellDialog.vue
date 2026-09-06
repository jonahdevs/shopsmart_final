<script setup lang="ts">
import { Form, Link } from '@inertiajs/vue3';
import { Check, Plus, SlidersHorizontal } from '@lucide/vue';
import Price from '@/components/storefront/Price.vue';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogClose,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { index as cartIndex, store as cartStore } from '@/routes/cart';
import { show as productShow } from '@/routes/product';

/**
 * The prompt that follows a successful add-to-cart: it confirms the line that
 * was just opened, then offers the accessories curated for that product.
 *
 * The caller decides whether it opens at all — an empty offer is worse than no
 * offer, so the page only flips `open` when the server sent accessories. Each
 * row adds itself with its own `<Form>` posting to the same `cart.store` every
 * other add in the storefront uses, with `preserveState` so the dialog and its
 * confirmation survive the redirect back onto this page.
 *
 * Everything but the price block is deliberately quiet: this interrupts a
 * shopper mid-purchase, so it reads as a short list, not a second storefront.
 */
const {
    accessories,
    addedName,
    addedOptionLabel = null,
    addedQuantity,
    returnFocusTo = null,
} = defineProps<{
    accessories: App.Data.ProductCardData[];
    /** The product the shopper just added, for the confirmation line. */
    addedName: string;
    addedOptionLabel?: string | null;
    addedQuantity: number;
    /**
     * Where the keyboard goes back to when this closes. Needed because the
     * dialog is opened in code rather than by a `DialogTrigger`: reka-ui parks
     * focus on the element that opened it, has nothing recorded for a
     * programmatic open, and suppresses its own fallback either way.
     */
    returnFocusTo?: HTMLElement | null;
}>();

const open = defineModel<boolean>('open', { required: true });

/**
 * reka-ui traps focus, closes on Escape and on an outside click, labels the
 * panel from the title and describes it from the description. The one thing it
 * cannot do without a trigger element is hand focus back, so this does.
 */
function restoreFocus(event: Event): void {
    if (returnFocusTo === null) {
        return;
    }

    event.preventDefault();
    returnFocusTo.focus();
}
</script>

<template>
    <Dialog v-model:open="open">
        <!--
          `storefront` is repeated here because the dialog is portalled to the
          body, outside StoreShell's wrapper: without it the panel would resolve
          the staff palette — and a staff dark mode — while the page behind it
          stayed light. Restating the class re-establishes the brand tokens and
          keeps every `dark:` variant in the primitives inert.
        -->
        <DialogContent
            class="storefront max-h-[85vh] gap-0 overflow-y-auto rounded-lg p-0 sm:max-w-lg"
            @close-auto-focus="restoreFocus"
        >
            <DialogHeader class="border-rule border-b p-6 text-left">
                <DialogTitle
                    class="font-display text-ink flex items-center gap-2 text-lg font-extrabold tracking-[-0.02em]"
                >
                    <span
                        class="bg-tint text-electric flex size-7 shrink-0 items-center justify-center rounded-full"
                        aria-hidden="true"
                    >
                        <Check class="size-4" />
                    </span>
                    Added to your cart
                </DialogTitle>
                <DialogDescription>
                    <span class="text-foreground">
                        {{ addedQuantity }} &times; {{ addedName }}
                    </span>
                    <span v-if="addedOptionLabel">
                        &mdash; {{ addedOptionLabel }}
                    </span>
                </DialogDescription>
            </DialogHeader>

            <div class="p-6">
                <h3
                    class="text-electric font-display text-[0.625rem] font-bold tracking-[0.18em] uppercase"
                >
                    Goes with it
                </h3>

                <ul class="divide-rule mt-4 divide-y">
                    <li
                        v-for="accessory in accessories"
                        :key="accessory.id"
                        class="flex items-center gap-4 py-4 first:pt-0 last:pb-0"
                    >
                        <!--
                          The title beside it is the same destination, so the
                          thumbnail is decoration rather than a second link.
                        -->
                        <div
                            class="border-rule shadow-card size-16 shrink-0 overflow-hidden rounded-lg border bg-white p-1.5"
                        >
                            <img
                                v-if="accessory.image"
                                :src="
                                    accessory.image.thumbUrl ??
                                    accessory.image.url
                                "
                                alt=""
                                loading="lazy"
                                decoding="async"
                                class="size-full object-contain"
                            />
                            <div v-else class="bg-muted size-full" />
                        </div>

                        <div class="min-w-0 flex-1">
                            <p
                                v-if="accessory.brandName"
                                class="text-muted-foreground truncate text-xs"
                            >
                                {{ accessory.brandName }}
                            </p>
                            <p
                                class="text-ink line-clamp-2 text-sm leading-5 font-medium"
                            >
                                <Link
                                    :href="productShow(accessory.slug)"
                                    class="hover:text-electric focus-visible:outline-electric rounded-sm transition-colors focus-visible:outline-2 focus-visible:outline-offset-4"
                                >
                                    {{ accessory.name }}
                                </Link>
                            </p>
                            <div class="mt-1.5">
                                <Price
                                    size="sm"
                                    :formatted="
                                        accessory.effectivePriceFormatted
                                    "
                                    :compare-formatted="
                                        accessory.isOnSale
                                            ? accessory.priceFormatted
                                            : null
                                    "
                                    :discount-percent="
                                        accessory.discountPercent
                                    "
                                />
                            </div>
                        </div>

                        <!--
                          A variable accessory prices through a variant nobody
                          has chosen yet, so it sends the shopper to its own
                          page instead of pretending it can be added from here.
                        -->
                        <Button
                            v-if="accessory.requiresOptions"
                            as-child
                            variant="outline"
                            size="sm"
                            class="border-rule hover:border-electric hover:text-electric shrink-0 rounded-lg bg-white font-semibold"
                        >
                            <Link :href="productShow(accessory.slug)">
                                <SlidersHorizontal
                                    class="size-3.5"
                                    aria-hidden="true"
                                />
                                Options
                            </Link>
                        </Button>

                        <Form
                            v-else
                            v-bind="cartStore.form()"
                            :options="{
                                preserveScroll: true,
                                preserveState: true,
                            }"
                            v-slot="{ processing, wasSuccessful }"
                            class="shrink-0"
                        >
                            <input
                                type="hidden"
                                name="product_id"
                                :value="accessory.id"
                            />

                            <Button
                                type="submit"
                                variant="outline"
                                size="sm"
                                class="border-rule hover:border-electric hover:text-electric rounded-lg bg-white font-semibold"
                                :disabled="processing"
                            >
                                <component
                                    :is="wasSuccessful ? Check : Plus"
                                    class="size-3.5"
                                    aria-hidden="true"
                                />
                                {{ wasSuccessful ? 'Added' : 'Add' }}
                            </Button>
                        </Form>
                    </li>
                </ul>
            </div>

            <DialogFooter class="border-rule border-t p-6">
                <DialogClose as-child>
                    <Button
                        variant="ghost"
                        class="text-muted-foreground hover:text-ink rounded-lg"
                    >
                        Keep shopping
                    </Button>
                </DialogClose>

                <Button as-child class="rounded-lg font-semibold">
                    <Link :href="cartIndex()">Go to cart</Link>
                </Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>

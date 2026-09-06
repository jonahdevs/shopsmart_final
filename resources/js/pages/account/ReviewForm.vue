<script setup lang="ts">
import { Form, Head, Link } from '@inertiajs/vue3';
import { ArrowLeft, BadgeCheck, Star } from '@lucide/vue';
import { computed, ref } from 'vue';
import AlertError from '@/components/AlertError.vue';
import InputError from '@/components/InputError.vue';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { reviews as reviewsRoute } from '@/routes/account';
import { store } from '@/routes/account/reviews';

/**
 * Writing about something you bought.
 *
 * The page only exists for a product the shopper has actually received and has
 * not already written about — the controller 404s otherwise — so there is
 * nothing here that asks whether they are allowed to be here.
 *
 * The body has a floor as well as a ceiling: a one-word review tells the next
 * shopper nothing, and the star is already carrying that signal. The counter
 * says so before the server has to.
 */
const { product } = defineProps<{
    product: App.Data.ProductCardData;
    breadcrumbs: App.Data.BreadcrumbData[];
}>();

/** The shortest useful review, matching the server's `min:20`. */
const MINIMUM_BODY = 20;

const MAXIMUM_BODY = 5000;

/**
 * The only controlled field on the page: five radios have to paint themselves
 * gold up to the chosen one, which needs the choice in script. Everything else
 * is read out of the DOM by `<Form>` at submit time.
 */
const rating = ref(0);

const body = ref('');

const remaining = computed(() =>
    Math.max(0, MINIMUM_BODY - body.value.trim().length),
);
</script>

<template>
    <Head :title="`Review ${product.name}`" />

    <div class="flex flex-col gap-8">
        <div
            class="border-rule shadow-card flex items-center gap-4 rounded-lg border bg-white p-4"
        >
            <div
                class="size-16 shrink-0 overflow-hidden rounded-lg bg-white"
                aria-hidden="true"
            >
                <img
                    v-if="product.image"
                    :src="product.image.thumbUrl ?? product.image.url"
                    :alt="product.image.alt"
                    decoding="async"
                    class="size-full object-contain"
                />
                <div v-else class="bg-muted size-full rounded-lg" />
            </div>

            <div class="min-w-0">
                <p
                    v-if="product.brandName"
                    class="font-display text-muted-foreground text-[0.6875rem] font-bold tracking-[0.14em] uppercase"
                >
                    {{ product.brandName }}
                </p>
                <p class="text-foreground text-sm leading-5 font-medium">
                    {{ product.name }}
                </p>
                <p
                    class="text-muted-foreground mt-1 flex items-center gap-1 text-xs"
                >
                    <BadgeCheck class="size-3.5" aria-hidden="true" />
                    Verified purchase
                </p>
            </div>
        </div>

        <!--
          `preserveState` so a refused submit comes back with the typed review
          still on screen: without it the adapter re-keys the page and the
          shopper retypes everything under the message telling them why.
        -->
        <Form
            v-bind="store.form(product.slug)"
            :options="{ preserveScroll: true, preserveState: true }"
            v-slot="{ errors, processing }"
            class="flex flex-col gap-8"
        >
            <AlertError
                v-if="Object.keys(errors).length > 0"
                title="This review was not saved."
                :errors="Object.values(errors)"
            />

            <fieldset>
                <legend
                    class="font-display text-foreground text-sm font-bold tracking-[0.02em] uppercase"
                >
                    Your rating
                </legend>
                <p class="text-muted-foreground mt-1 text-sm">
                    One star is "avoid it", five is "buy it again".
                </p>

                <div class="mt-3 flex items-center gap-1">
                    <label
                        v-for="star in 5"
                        :key="star"
                        :for="`rating-${star}`"
                        class="cursor-pointer p-0.5"
                    >
                        <input
                            :id="`rating-${star}`"
                            v-model.number="rating"
                            type="radio"
                            name="rating"
                            :value="star"
                            required
                            class="peer sr-only"
                        />
                        <Star
                            class="peer-focus-visible:outline-electric size-8 rounded-sm transition-colors peer-focus-visible:outline-2 peer-focus-visible:outline-offset-2"
                            :class="
                                star <= rating
                                    ? 'fill-star text-star'
                                    : 'text-rule fill-transparent'
                            "
                            :stroke-width="1.5"
                            aria-hidden="true"
                        />
                        <span class="sr-only">
                            {{ star }} {{ star === 1 ? 'star' : 'stars' }}
                        </span>
                    </label>
                </div>

                <InputError class="mt-2" :message="errors.rating" />
            </fieldset>

            <div class="grid max-w-xl gap-2">
                <Label for="review-title">
                    Headline
                    <span class="text-muted-foreground font-normal">
                        optional
                    </span>
                </Label>
                <Input
                    id="review-title"
                    name="title"
                    maxlength="255"
                    placeholder="Sum it up in a few words"
                />
                <InputError :message="errors.title" />
            </div>

            <div class="grid max-w-2xl gap-2">
                <Label for="review-body">Your review</Label>
                <Textarea
                    id="review-body"
                    v-model="body"
                    name="body"
                    rows="6"
                    :minlength="MINIMUM_BODY"
                    :maxlength="MAXIMUM_BODY"
                    required
                    placeholder="How did it work out? What would you tell someone thinking about it?"
                />
                <p
                    class="text-muted-foreground text-xs tabular-nums"
                    aria-live="polite"
                >
                    <template v-if="remaining > 0">
                        {{ remaining }} more
                        {{ remaining === 1 ? 'character' : 'characters' }} to
                        go.
                    </template>
                    <template v-else>
                        Long enough. Say as much as you like, up to
                        {{ MAXIMUM_BODY }} characters.
                    </template>
                </p>
                <InputError :message="errors.body" />
            </div>

            <div class="flex flex-wrap items-center gap-x-6 gap-y-3">
                <button
                    type="submit"
                    :disabled="processing"
                    class="bg-ink font-display focus-visible:outline-electric h-11 rounded-lg px-6 text-sm font-bold tracking-[0.08em] text-white uppercase transition-opacity hover:opacity-90 focus-visible:outline-2 focus-visible:outline-offset-2 disabled:cursor-not-allowed disabled:opacity-40"
                >
                    {{ processing ? 'Sending' : 'Submit review' }}
                </button>

                <Link
                    :href="reviewsRoute()"
                    class="font-display text-electric hover:border-electric focus-visible:outline-electric inline-flex items-center gap-1.5 border-b border-transparent pb-0.5 text-sm font-bold transition-colors focus-visible:outline-2 focus-visible:outline-offset-4"
                >
                    <ArrowLeft class="size-4" aria-hidden="true" />
                    Back to your reviews
                </Link>
            </div>

            <p class="text-muted-foreground max-w-2xl text-xs leading-relaxed">
                Reviews are read by our team before they go on the product page.
                Yours will show as pending until then.
            </p>
        </Form>
    </div>
</template>

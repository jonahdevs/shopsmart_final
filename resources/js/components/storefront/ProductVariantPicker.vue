<script setup lang="ts">
import { ChevronDown } from '@lucide/vue';
import { computed, ref, watch } from 'vue';

/**
 * The axes a variable product varies along, and the variant they resolve to.
 *
 * Selections are held as attribute id => attribute value id and matched against
 * `attributeValueIds` on each variant, which is exactly what the server sends
 * them for — no slug parsing on either side of the wire.
 *
 * A combination the catalogue does not stock is drawn as unavailable rather
 * than hidden or hard-disabled: picking one still works, and any other axis
 * that can no longer be honoured alongside it falls back to undecided. That is
 * what keeps a two-axis matrix escapable — mutually disabling dropdowns can
 * strand a shopper in a corner they cannot select their way out of.
 */
const selectedVariant = defineModel<App.Data.ProductVariantData | null>({
    required: true,
});

const {
    attributes,
    variants,
    defaultVariantId = null,
} = defineProps<{
    attributes: App.Data.VariationAttributeData[];
    variants: App.Data.ProductVariantData[];
    defaultVariantId?: number | null;
}>();

/** Attribute id => chosen value id, or null while that axis is undecided. */
type Selection = Record<number, number | null>;

function initialSelection(): Selection {
    const preset = variants.find((variant) => variant.id === defaultVariantId);
    const chosen: Selection = {};

    for (const attribute of attributes) {
        chosen[attribute.id] =
            attribute.values.find((value) =>
                preset?.attributeValueIds.includes(value.id),
            )?.id ?? null;
    }

    return chosen;
}

const selection = ref<Selection>(initialSelection());

function chosenIds(candidate: Selection): number[] {
    return Object.values(candidate).filter((id): id is number => id !== null);
}

/** Is there still a variant that honours every decision made so far? */
function isReachable(candidate: Selection): boolean {
    const wanted = chosenIds(candidate);

    return variants.some((variant) =>
        wanted.every((id) => variant.attributeValueIds.includes(id)),
    );
}

/** The one variant a fully decided selection resolves to, when it is stocked. */
function resolve(candidate: Selection): App.Data.ProductVariantData | null {
    const wanted = chosenIds(candidate);

    if (wanted.length !== attributes.length) {
        return null;
    }

    return (
        variants.find((variant) =>
            wanted.every((id) => variant.attributeValueIds.includes(id)),
        ) ?? null
    );
}

const unavailableValueIds = computed<Set<number>>(() => {
    const ids = new Set<number>();

    for (const attribute of attributes) {
        for (const value of attribute.values) {
            if (
                !isReachable({ ...selection.value, [attribute.id]: value.id })
            ) {
                ids.add(value.id);
            }
        }
    }

    return ids;
});

function select(attributeId: number, valueId: number): void {
    const next: Selection = { [attributeId]: valueId };

    for (const attribute of attributes) {
        if (attribute.id === attributeId) {
            continue;
        }

        const kept = selection.value[attribute.id] ?? null;

        next[attribute.id] =
            kept !== null && isReachable({ ...next, [attribute.id]: kept })
                ? kept
                : null;
    }

    selection.value = next;
}

function onSelectChange(attributeId: number, event: Event): void {
    const chosen = Number((event.target as HTMLSelectElement).value);

    if (Number.isInteger(chosen) && chosen > 0) {
        select(attributeId, chosen);
    }
}

function chosenLabel(
    attribute: App.Data.VariationAttributeData,
): string | null {
    const chosen = selection.value[attribute.id] ?? null;

    return attribute.values.find((value) => value.id === chosen)?.label ?? null;
}

watch(
    selection,
    () => {
        selectedVariant.value = resolve(selection.value);
    },
    { immediate: true },
);
</script>

<template>
    <div class="flex flex-col gap-5">
        <fieldset
            v-for="attribute in attributes"
            :key="attribute.id"
            class="flex flex-col gap-2.5"
        >
            <legend
                class="font-display text-foreground text-[0.6875rem] font-bold tracking-[0.14em] uppercase"
            >
                {{ attribute.name }}
                <span class="text-muted-foreground">
                    &middot;
                    {{ chosenLabel(attribute) ?? 'choose one' }}
                </span>
            </legend>

            <!--
              A dropdown axis keeps every option selectable and states the
              unavailable ones in the option text: disabling them would let two
              dropdowns deadlock one another.
            -->
            <div v-if="attribute.type === 'select'" class="relative max-w-xs">
                <select
                    :value="selection[attribute.id] ?? ''"
                    :aria-label="attribute.name"
                    class="border-rule focus:border-electric focus-visible:outline-electric w-full appearance-none rounded-xs border bg-white py-2 pr-9 pl-3 text-sm focus:outline-none focus-visible:outline-2 focus-visible:outline-offset-2"
                    @change="onSelectChange(attribute.id, $event)"
                >
                    <option value="" disabled>
                        Select {{ attribute.name.toLowerCase() }}
                    </option>
                    <option
                        v-for="value in attribute.values"
                        :key="value.id"
                        :value="value.id"
                    >
                        {{ value.label
                        }}{{
                            unavailableValueIds.has(value.id)
                                ? ' — unavailable'
                                : ''
                        }}
                    </option>
                </select>
                <ChevronDown
                    class="text-muted-foreground pointer-events-none absolute top-1/2 right-3 size-4 -translate-y-1/2"
                    aria-hidden="true"
                />
            </div>

            <div v-else class="flex flex-wrap gap-2">
                <button
                    v-for="value in attribute.values"
                    :key="value.id"
                    type="button"
                    :aria-pressed="selection[attribute.id] === value.id"
                    class="focus-visible:outline-electric inline-flex items-center gap-2 rounded-xs border px-3 py-2 text-sm transition-colors focus-visible:outline-2 focus-visible:outline-offset-2"
                    :class="[
                        selection[attribute.id] === value.id
                            ? 'border-electric bg-accent text-accent-foreground'
                            : 'border-rule hover:border-ink text-foreground',
                        unavailableValueIds.has(value.id)
                            ? 'border-dashed line-through opacity-55'
                            : '',
                    ]"
                    @click="select(attribute.id, value.id)"
                >
                    <span
                        v-if="attribute.type === 'color' && value.colorCode"
                        class="ring-rule size-4 rounded-full ring-1 ring-inset"
                        :style="{ backgroundColor: value.colorCode }"
                        aria-hidden="true"
                    />
                    <span>{{ value.label }}</span>
                    <span
                        v-if="unavailableValueIds.has(value.id)"
                        class="sr-only"
                    >
                        (unavailable with the options currently selected)
                    </span>
                </button>
            </div>
        </fieldset>
    </div>
</template>

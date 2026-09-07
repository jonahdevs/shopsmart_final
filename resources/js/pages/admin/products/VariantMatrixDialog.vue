<script setup lang="ts">
import { Grid2x2Plus } from '@lucide/vue';
import { computed, ref } from 'vue';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogClose,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import {
    MAX_GENERATED_COMBINATIONS,
    MAX_VARIANT_ROWS,
    planVariantMatrix,
} from '@/lib/variantMatrix';
import type { AttributeGroup } from './ProductVariantsSection.vue';

/**
 * The matrix builder behind "Generate variants".
 *
 * Three colours by four sizes is twelve rows, and building those one at a time
 * out of the repeater is the single most tedious thing in the product form.
 * This picks the values instead and hands the combinations back; the section
 * turns them into rows. Nothing here posts anywhere — the rows land in the
 * form the staff member is already filling in, and are saved with it.
 *
 * It reports rather than acts: how many combinations the ticks describe, how
 * many are already on the product, how many would actually be added. A staff
 * member should know what the button is about to do before they press it.
 */
const { attributeGroups, existingCombinations } = defineProps<{
    attributeGroups: AttributeGroup[];
    /**
     * One entry per variant row already on the form, in row order — the value
     * ids that row carries, empty array and all. Combinations that match one
     * are skipped, and the length is what the row cap is measured against.
     */
    existingCombinations: number[][];
}>();

const emit = defineEmits<{ generate: [combinations: number[][]] }>();

const open = ref(false);

/**
 * The ticked value ids, one array per attribute group, positional so it cannot
 * disagree with a group's label. Deliberately *not* reset when the dialog
 * closes: generating merges, so the useful second visit is "tick one more
 * colour and press it again", and that is much easier from the ticks you left.
 */
const ticked = ref<number[][]>(attributeGroups.map(() => []));

const plan = computed(() =>
    planVariantMatrix(ticked.value, existingCombinations),
);

const canGenerate = computed(
    () =>
        plan.value.withinLimit &&
        plan.value.withinRowLimit &&
        plan.value.additions.length > 0,
);

/**
 * The one line that says what pressing the button will do. Refusals read as
 * the count that caused them, because "too many" leaves the staff member
 * guessing how much to untick.
 */
const summary = computed<{ message: string; isRefusal: boolean }>(() => {
    const { total, duplicates, additions, withinLimit, withinRowLimit } =
        plan.value;

    if (total === 0) {
        return {
            message:
                'Nothing ticked yet. Choose the values this product varies on — one attribute is fine, and every attribute you add multiplies the rows.',
            isRefusal: false,
        };
    }

    if (!withinLimit) {
        return {
            message: `That is ${total} combinations, and this adds at most ${MAX_GENERATED_COMBINATIONS} at a time. Untick some values.`,
            isRefusal: true,
        };
    }

    if (!withinRowLimit) {
        return {
            message: `Adding ${additions.length} would take this product to ${existingCombinations.length + additions.length} variants, and the form accepts ${MAX_VARIANT_ROWS}.`,
            isRefusal: true,
        };
    }

    if (additions.length === 0) {
        return {
            message: `All ${total} of those combinations are already on this product. Nothing to add.`,
            isRefusal: false,
        };
    }

    return {
        message: `${total} combination${total === 1 ? '' : 's'}: ${additions.length} to add${duplicates === 0 ? '' : `, ${duplicates} already here`}.`,
        isRefusal: false,
    };
});

const generateLabel = computed(() => {
    const count = plan.value.additions.length;

    if (count === 0) {
        return 'Add variants';
    }

    return `Add ${count} variant${count === 1 ? '' : 's'}`;
});

function isWholeGroupTicked(groupIndex: number): boolean {
    const group = attributeGroups[groupIndex];

    return (
        group.options.length > 0 &&
        (ticked.value[groupIndex]?.length ?? 0) === group.options.length
    );
}

function toggleGroup(groupIndex: number): void {
    ticked.value[groupIndex] = isWholeGroupTicked(groupIndex)
        ? []
        : attributeGroups[groupIndex].options.map((option) => option.value);
}

function generate(): void {
    emit('generate', plan.value.additions);
    open.value = false;
}
</script>

<template>
    <Dialog v-model:open="open">
        <!--
          The button that opens this lives here rather than in the section's
          header because a `<DialogTrigger>` is the one thing reka-ui will hand
          focus back to on close; opened in code, the panel would drop the
          keyboard on the body and we would have to catch it ourselves.
        -->
        <DialogTrigger as-child>
            <Button type="button" variant="outline" size="sm">
                <Grid2x2Plus class="size-4" aria-hidden="true" />
                Generate variants
            </Button>
        </DialogTrigger>

        <!--
          No `storefront` class: staff tokens are the bare `:root` block, so a
          panel portalled to the body already resolves them.
        -->
        <DialogContent class="max-h-[85vh] overflow-y-auto sm:max-w-2xl">
            <DialogHeader>
                <DialogTitle>Generate variants</DialogTitle>
                <DialogDescription>
                    Every combination of the values you tick becomes a row. Rows
                    you already have are left exactly as they are, and nothing
                    is stored until you save the product.
                </DialogDescription>
            </DialogHeader>

            <div class="space-y-5">
                <fieldset
                    v-for="(group, groupIndex) in attributeGroups"
                    :key="group.label"
                >
                    <legend class="text-sm font-medium">
                        {{ group.label }}
                    </legend>

                    <div
                        class="mt-2 flex flex-wrap items-center gap-x-6 gap-y-2"
                    >
                        <label
                            v-for="option in group.options"
                            :key="option.value"
                            class="flex cursor-pointer items-center gap-2 text-sm"
                        >
                            <input
                                v-model="ticked[groupIndex]"
                                type="checkbox"
                                :value="option.value"
                                class="accent-primary size-4 rounded-sm"
                            />
                            {{ option.label }}
                        </label>

                        <Button
                            v-if="group.options.length > 1"
                            type="button"
                            variant="ghost"
                            size="sm"
                            class="h-7 px-2 text-xs"
                            @click="toggleGroup(groupIndex)"
                        >
                            {{
                                isWholeGroupTicked(groupIndex) ? 'None' : 'All'
                            }}
                        </Button>
                    </div>
                </fieldset>
            </div>

            <p
                class="rounded-md border p-3 text-sm"
                :class="
                    summary.isRefusal
                        ? 'text-destructive'
                        : 'bg-muted/40 text-muted-foreground'
                "
                aria-live="polite"
            >
                {{ summary.message }}
            </p>

            <DialogFooter>
                <DialogClose as-child>
                    <Button type="button" variant="outline">Cancel</Button>
                </DialogClose>
                <Button
                    type="button"
                    :disabled="!canGenerate"
                    @click="generate"
                >
                    {{ generateLabel }}
                </Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>

import { router } from '@inertiajs/vue3';
import type { ComputedRef } from 'vue';
import { computed, onUnmounted, ref } from 'vue';

export type UseRowSelectionReturn = {
    /** How many rows are ticked. Drives whether the bulk bar is on screen. */
    count: ComputedRef<number>;
    /** The ticked ids, in the order the table is printing them. */
    ids: ComputedRef<number[]>;
    isSelected: (id: number) => boolean;
    setSelected: (id: number, selected: boolean) => void;
    /** True when every row on this page is ticked — the header box's checked state. */
    allSelected: ComputedRef<boolean>;
    /** True when some but not all are — the header box's indeterminate state. */
    someSelected: ComputedRef<boolean>;
    /** Tick or untick every row on this page. */
    setAllSelected: (selected: boolean) => void;
    clear: () => void;
};

/**
 * Which rows of an admin table are ticked.
 *
 * **Selection is scoped to the page on screen and nothing else.** It is dropped
 * on every filter change, every sort, every page turn and after every action.
 * That is the whole design, and it is a decision rather than a shortcut.
 *
 * The alternative — a `Set` that survives visits — is what you get for free
 * here, and it is a trap. `useIndexTable` visits with `preserveState: true`, so
 * a naive set of ids outlives the visit: filter to drafts, tick twenty-five,
 * clear the filter, tick ten more, and the next click acts on thirty-five
 * products of which the screen can account for ten. Nobody would design that on
 * purpose; it is simply what happens if selection is a plain `ref` and nobody
 * thinks about the visit. Persisting it honestly is possible, but it costs a
 * "35 selected across your filters — view / clear" affordance, and a bulk bar
 * whose count disagrees with the ticks in front of you is a worse default than
 * one that visibly resets. So: what you can see is what you can act on.
 *
 * Two things enforce that, deliberately:
 *
 * - `useIndexTable`'s `onBeforeVisit` hook, for the debounced filter visit,
 *   which preserves state and would otherwise carry the selection over.
 * - The `success` listener below, which covers everything else — sort and
 *   pagination links, the bulk action's own redirect, and any future visit that
 *   swaps the rows out. Those links happen to remount the page today (no
 *   `preserveState`, so the adapter re-keys it), which resets the ref anyway.
 *   Relying on that would make this guarantee a side effect of a prop on a
 *   different component; a page that later gained `preserveState` on its
 *   pagination links would silently reintroduce the bug. So the guarantee is
 *   stated here instead of inferred.
 *
 * @param rowIds A getter for the ids currently rendered, e.g. `() => products.map(p => p.id)`.
 */
export function useRowSelection(rowIds: () => number[]): UseRowSelectionReturn {
    const selected = ref(new Set<number>());

    function clear(): void {
        // Replaced rather than emptied in place: `.clear()` on the same Set
        // works under Vue's collection proxy, but a new Set keeps this honest
        // if the ref is ever swapped for a `shallowRef`.
        selected.value = new Set<number>();
    }

    const stopListening = router.on('success', () => clear());

    onUnmounted(() => stopListening());

    // Derived from the rendered ids rather than from the Set, so the order is
    // the table's own and a row that has left the page cannot appear in it.
    const ids = computed(() => rowIds().filter((id) => selected.value.has(id)));

    const count = computed(() => ids.value.length);

    const allSelected = computed(() => {
        const page = rowIds();

        return page.length > 0 && page.every((id) => selected.value.has(id));
    });

    const someSelected = computed(() => count.value > 0 && !allSelected.value);

    function isSelected(id: number): boolean {
        return selected.value.has(id);
    }

    function setSelected(id: number, isNowSelected: boolean): void {
        if (isNowSelected) {
            selected.value.add(id);
        } else {
            selected.value.delete(id);
        }
    }

    function setAllSelected(isNowSelected: boolean): void {
        if (!isNowSelected) {
            clear();

            return;
        }

        selected.value = new Set(rowIds());
    }

    return {
        count,
        ids,
        isSelected,
        setSelected,
        allSelected,
        someSelected,
        setAllSelected,
        clear,
    };
}

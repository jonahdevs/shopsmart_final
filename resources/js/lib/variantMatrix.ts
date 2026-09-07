/**
 * The combination maths behind the product form's "Generate variants" action.
 *
 * Deliberately free of Vue and of the row shape the form uses: what a variant
 * row looks like is the component's business, while *which* combinations exist
 * and which of them are new is arithmetic. This project has no JavaScript test
 * runner, so keeping that arithmetic here — three plain functions over numbers,
 * readable end to end in one screen — is what stands in for a unit test.
 *
 * Everything below talks in attribute *value* ids: the same integers the
 * repeater's option select submits as `variants[i][attribute_value_ids][]`.
 */

/**
 * The most combinations one generation may produce.
 *
 * Four attributes with five values each is 625 rows — not a matrix anyone is
 * going to price by hand, but certainly a locked browser and a form nobody can
 * submit. Above this the dialog refuses and states the number, which is a far
 * better answer than a mess the staff member then deletes a row at a time.
 */
export const MAX_GENERATED_COMBINATIONS = 100;

/**
 * The most variant rows the form may carry at all. This mirrors
 * `'variants' => ['nullable', 'array', 'max:100']` in ProductRequest: generate
 * past it and the save is rejected wholesale, taking every other edit on the
 * screen down with it.
 */
export const MAX_VARIANT_ROWS = 100;

/** What {@see planVariantMatrix} makes of a set of ticked values. */
export type VariantMatrixPlan = {
    /** How many combinations the ticked values describe, cap or no cap. */
    total: number;
    /** How many of those are already on the product, and will not be repeated. */
    duplicates: number;
    /** The combinations to append, in axis order. Empty when a limit is hit. */
    additions: number[][];
    /** False when `total` is over {@see MAX_GENERATED_COMBINATIONS}. */
    withinLimit: boolean;
    /** False when appending would push the product past {@see MAX_VARIANT_ROWS}. */
    withinRowLimit: boolean;
};

/**
 * The identity of a combination, independent of the order its ids were picked.
 *
 * A saved row carries whatever order the database handed back, so comparing
 * the arrays as written would call [3, 7] and [7, 3] different variants and
 * happily create the second one. Sorting numerically is the whole fix.
 */
export function combinationKey(attributeValueIds: readonly number[]): string {
    return [...attributeValueIds].sort((a, b) => a - b).join('-');
}

/**
 * Every way of picking one value from each axis, in axis order.
 *
 * The last axis varies fastest, so colour × size comes out grouped by colour —
 * Red/S, Red/M, Blue/S, Blue/M — which is the order a staff member expects to
 * read the new rows in.
 *
 * An axis with nothing ticked would multiply the answer by zero, so callers
 * drop those first; {@see planVariantMatrix} does. With no axes at all this
 * returns `[[]]`, the one empty pick, which is why the plan counts before it
 * calls in here.
 */
export function cartesianCombinations(
    axes: readonly (readonly number[])[],
): number[][] {
    return axes.reduce<number[][]>(
        (combinations, axis) =>
            combinations.flatMap((combination) =>
                axis.map((valueId) => [...combination, valueId]),
            ),
        [[]],
    );
}

/**
 * What ticking `axes` would do to a product that already has `existing` rows.
 *
 * Generating must never destroy priced work, so this is a merge and not a
 * replace: a combination already on the product is counted and skipped, and
 * only genuinely new ones come back in `additions`. That is also what makes
 * generating twice safe — the second run adds the delta and nothing else.
 *
 * `existing` holds one entry per row already on the form, in row order,
 * including rows with no options ticked. Those never match anything, but they
 * still occupy one of the hundred rows the request accepts, so they have to be
 * in the count.
 *
 * Worked example — Colour [1, 2], Size [3, 4], with [1, 3] already a row:
 *
 *     total       4                            (2 × 2)
 *     duplicates  1                            ([1, 3])
 *     additions   [[1, 4], [2, 3], [2, 4]]
 */
export function planVariantMatrix(
    axes: readonly (readonly number[])[],
    existing: readonly (readonly number[])[],
): VariantMatrixPlan {
    const ticked = axes.filter((axis) => axis.length > 0);

    // An attribute nobody ticked is an attribute this product does not vary on,
    // not an instruction to produce nothing — so it is dropped rather than
    // multiplied in. Ticking two colours and no sizes means two variants.
    const total = ticked.reduce(
        (count, axis) => count * axis.length,
        ticked.length === 0 ? 0 : 1,
    );

    if (total === 0 || total > MAX_GENERATED_COMBINATIONS) {
        return {
            total,
            duplicates: 0,
            additions: [],
            withinLimit: total <= MAX_GENERATED_COMBINATIONS,
            withinRowLimit: true,
        };
    }

    const taken = new Set(existing.map(combinationKey));
    const additions: number[][] = [];
    let duplicates = 0;

    for (const combination of cartesianCombinations(ticked)) {
        if (taken.has(combinationKey(combination))) {
            duplicates += 1;

            continue;
        }

        additions.push(combination);
    }

    return {
        total,
        duplicates,
        additions,
        withinLimit: true,
        withinRowLimit: existing.length + additions.length <= MAX_VARIANT_ROWS,
    };
}

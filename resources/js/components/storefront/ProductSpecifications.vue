<script setup lang="ts">
/**
 * The specification table.
 *
 * A spec row can carry several values, so they are joined here rather than on
 * the server. The table scrolls inside its own box: on a 320px screen a two
 * column table of arbitrary attribute names would otherwise push the whole
 * document sideways.
 */
defineProps<{
    specifications: App.Data.SpecificationData[];
    /** Free-text sheet the merchandiser typed, shown under the table. */
    technicalSpecification?: string | null;
}>();
</script>

<template>
    <div class="flex flex-col gap-6">
        <div v-if="specifications.length" class="overflow-x-auto">
            <table class="w-full min-w-sm border-collapse text-sm">
                <caption class="sr-only">
                    Product specifications
                </caption>
                <tbody>
                    <tr
                        v-for="specification in specifications"
                        :key="specification.name"
                        class="border-rule border-b align-top last:border-b-0"
                    >
                        <th
                            scope="row"
                            class="text-muted-foreground w-2/5 py-3 pr-6 text-left font-medium"
                        >
                            {{ specification.name }}
                        </th>
                        <td class="text-foreground py-3">
                            {{ specification.values.join(', ') }}
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <p
            v-if="technicalSpecification"
            class="text-foreground max-w-3xl text-sm leading-7 whitespace-pre-line"
        >
            {{ technicalSpecification }}
        </p>
    </div>
</template>

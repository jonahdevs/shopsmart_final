<script setup lang="ts">
import { useId } from 'vue';
import InputError from '@/components/InputError.vue';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';

/**
 * The address book entry, as a fieldset.
 *
 * Uncontrolled `name`d inputs with `:default-value`, so the enclosing
 * `<Form>` reads them straight out of the DOM at submit time — this component
 * holds no state of its own and never needs to be told what was typed.
 *
 * Ids are generated because the fieldset can appear more than once on a page,
 * and a `for`/`id` pair that collides silently points a label at the wrong box.
 */
const { errors, countryCode = 'KE' } = defineProps<{
    /** The enclosing form's errors, keyed by field name. */
    errors: Record<string, string>;
    /** Two-letter default, so a Kenyan shopper never has to fill it in. */
    countryCode?: string;
}>();

const id = useId();
</script>

<template>
    <fieldset class="grid gap-5 sm:grid-cols-2">
        <legend class="sr-only">New delivery address</legend>

        <div class="grid gap-2">
            <Label :for="`${id}-first-name`">First name</Label>
            <Input
                :id="`${id}-first-name`"
                name="first_name"
                autocomplete="given-name"
                required
            />
            <InputError :message="errors.first_name" />
        </div>

        <div class="grid gap-2">
            <Label :for="`${id}-last-name`">Last name</Label>
            <Input
                :id="`${id}-last-name`"
                name="last_name"
                autocomplete="family-name"
                required
            />
            <InputError :message="errors.last_name" />
        </div>

        <div class="grid gap-2">
            <Label :for="`${id}-phone`">Phone</Label>
            <Input
                :id="`${id}-phone`"
                name="phone"
                type="tel"
                inputmode="tel"
                autocomplete="tel"
                placeholder="07xx xxx xxx"
                required
            />
            <InputError :message="errors.phone" />
        </div>

        <div class="grid gap-2">
            <Label :for="`${id}-label`">
                Label
                <span class="text-muted-foreground font-normal">optional</span>
            </Label>
            <Input
                :id="`${id}-label`"
                name="label"
                placeholder="Home, office…"
            />
            <InputError :message="errors.label" />
        </div>

        <div class="grid gap-2 sm:col-span-2">
            <Label :for="`${id}-line1`">Street address</Label>
            <Input
                :id="`${id}-line1`"
                name="line1"
                autocomplete="address-line1"
                required
            />
            <InputError :message="errors.line1" />
        </div>

        <div class="grid gap-2 sm:col-span-2">
            <Label :for="`${id}-line2`">
                Building, floor or estate
                <span class="text-muted-foreground font-normal">optional</span>
            </Label>
            <Input
                :id="`${id}-line2`"
                name="line2"
                autocomplete="address-line2"
            />
            <InputError :message="errors.line2" />
        </div>

        <div class="grid gap-2">
            <Label :for="`${id}-city`">Town or city</Label>
            <Input
                :id="`${id}-city`"
                name="city"
                autocomplete="address-level2"
                required
            />
            <InputError :message="errors.city" />
        </div>

        <div class="grid gap-2">
            <Label :for="`${id}-county`">
                County
                <span class="text-muted-foreground font-normal">optional</span>
            </Label>
            <Input
                :id="`${id}-county`"
                name="county"
                autocomplete="address-level1"
            />
            <InputError :message="errors.county" />
        </div>

        <div class="grid gap-2">
            <Label :for="`${id}-postal-code`">
                Postal code
                <span class="text-muted-foreground font-normal">optional</span>
            </Label>
            <Input
                :id="`${id}-postal-code`"
                name="postal_code"
                autocomplete="postal-code"
            />
            <InputError :message="errors.postal_code" />
        </div>

        <div class="grid gap-2">
            <Label :for="`${id}-country-code`">Country code</Label>
            <Input
                :id="`${id}-country-code`"
                name="country_code"
                :default-value="countryCode"
                autocomplete="country"
                maxlength="2"
                required
                class="uppercase"
            />
            <InputError :message="errors.country_code" />
        </div>

        <div class="grid gap-2 sm:col-span-2">
            <Label :for="`${id}-delivery-notes`">
                Delivery notes
                <span class="text-muted-foreground font-normal">optional</span>
            </Label>
            <Textarea
                :id="`${id}-delivery-notes`"
                name="delivery_notes"
                maxlength="500"
                placeholder="Gate colour, landmark, who to call on arrival…"
            />
            <InputError :message="errors.delivery_notes" />
        </div>

        <div class="sm:col-span-2">
            <!--
              A plain checkbox with an explicit "1", because the server validates
              this as a boolean and the browser's default "on" is not one.
            -->
            <label
                :for="`${id}-is-default`"
                class="text-foreground flex cursor-pointer items-center gap-2.5 text-sm"
            >
                <input
                    :id="`${id}-is-default`"
                    type="checkbox"
                    name="is_default"
                    value="1"
                    class="accent-electric focus-visible:outline-electric size-4 rounded-xs focus-visible:outline-2 focus-visible:outline-offset-2"
                />
                Use this as my default address
            </label>
            <InputError :message="errors.is_default" />
        </div>
    </fieldset>
</template>

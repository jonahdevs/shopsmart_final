<script setup lang="ts">
import { ref } from 'vue';
import InputError from '@/components/InputError.vue';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { NativeSelect } from '@/components/ui/native-select';
import { Textarea } from '@/components/ui/textarea';

/**
 * The fields shared by the create and edit coupon forms.
 *
 * Not a page — it lives beside the two pages that use it because
 * `resources/js/components/admin/` belongs to another part of the build. It
 * renders inputs only: the surrounding `<Form>` reads them straight out of the
 * DOM, so nothing here holds the submitted values.
 *
 * Every money field is labelled and typed in whole KES. The server converts to
 * integer cents once, in StoreCouponRequest — no arithmetic happens here,
 * which is the point.
 */
const { coupon, errors } = defineProps<{
    /** Null when creating; the coupon being edited otherwise. */
    coupon: App.Data.AdminCouponRowData | null;
    typeOptions: { value: string; label: string }[];
    errors: Record<string, string>;
}>();

/**
 * The only controlled input on the form. A coupon is either a fixed amount or
 * a percentage and never both, so the type has to steer which money field is
 * on screen — showing both invites staff to fill in the one the coupon will
 * silently ignore.
 */
const type = ref<string>(coupon?.type ?? 'fixed');
</script>

<template>
    <div class="grid gap-6">
        <div class="grid gap-4 sm:grid-cols-2">
            <div class="space-y-1.5">
                <Label for="coupon-code">Code</Label>
                <Input
                    id="coupon-code"
                    name="code"
                    :default-value="coupon?.code"
                    required
                    maxlength="64"
                    autocapitalize="characters"
                    placeholder="WELCOME10"
                />
                <p class="text-muted-foreground text-xs">
                    Letters, numbers, hyphens and underscores. Stored uppercase.
                </p>
                <InputError :message="errors.code" />
            </div>

            <div class="space-y-1.5">
                <Label for="coupon-type">Discount type</Label>
                <NativeSelect id="coupon-type" v-model="type" name="type">
                    <option
                        v-for="option in typeOptions"
                        :key="option.value"
                        :value="option.value"
                    >
                        {{ option.label }}
                    </option>
                </NativeSelect>
                <InputError :message="errors.type" />
            </div>

            <div v-if="type === 'fixed'" class="space-y-1.5">
                <Label for="coupon-amount">Amount off (KES)</Label>
                <Input
                    id="coupon-amount"
                    name="amount"
                    type="number"
                    step="0.01"
                    min="0.01"
                    :default-value="coupon?.amountMajor ?? undefined"
                    placeholder="500"
                />
                <InputError :message="errors.amount" />
            </div>

            <div v-else class="space-y-1.5">
                <Label for="coupon-percent">Percentage off</Label>
                <Input
                    id="coupon-percent"
                    name="percent"
                    type="number"
                    step="0.01"
                    min="0.01"
                    max="100"
                    :default-value="coupon?.percent ?? undefined"
                    placeholder="10"
                />
                <InputError :message="errors.percent" />
            </div>

            <div class="space-y-1.5">
                <Label for="coupon-min-subtotal">Minimum spend (KES)</Label>
                <Input
                    id="coupon-min-subtotal"
                    name="min_subtotal"
                    type="number"
                    step="0.01"
                    min="0"
                    :default-value="coupon?.minSubtotalMajor ?? undefined"
                    placeholder="0"
                />
                <p class="text-muted-foreground text-xs">
                    Leave empty for no minimum.
                </p>
                <InputError :message="errors.min_subtotal" />
            </div>

            <div v-if="type === 'percent'" class="space-y-1.5">
                <Label for="coupon-max-discount">Maximum discount (KES)</Label>
                <Input
                    id="coupon-max-discount"
                    name="max_discount"
                    type="number"
                    step="0.01"
                    min="0.01"
                    :default-value="coupon?.maxDiscountMajor ?? undefined"
                    placeholder="Uncapped"
                />
                <p class="text-muted-foreground text-xs">
                    A ceiling on what a percentage can take off a large order.
                </p>
                <InputError :message="errors.max_discount" />
            </div>
        </div>

        <div class="grid gap-4 sm:grid-cols-2">
            <div class="space-y-1.5">
                <Label for="coupon-usage-limit">Total redemptions allowed</Label>
                <Input
                    id="coupon-usage-limit"
                    name="usage_limit"
                    type="number"
                    step="1"
                    min="1"
                    :default-value="coupon?.usageLimit ?? undefined"
                    placeholder="Unlimited"
                />
                <InputError :message="errors.usage_limit" />
            </div>

            <div class="space-y-1.5">
                <Label for="coupon-per-user">Redemptions per customer</Label>
                <Input
                    id="coupon-per-user"
                    name="usage_limit_per_user"
                    type="number"
                    step="1"
                    min="1"
                    :default-value="coupon?.usageLimitPerUser ?? undefined"
                    placeholder="Unlimited"
                />
                <InputError :message="errors.usage_limit_per_user" />
            </div>

            <div class="space-y-1.5">
                <Label for="coupon-starts-at">Valid from</Label>
                <Input
                    id="coupon-starts-at"
                    name="starts_at"
                    type="date"
                    :default-value="coupon?.startsAt?.slice(0, 10)"
                />
                <InputError :message="errors.starts_at" />
            </div>

            <div class="space-y-1.5">
                <Label for="coupon-expires-at">Valid until</Label>
                <Input
                    id="coupon-expires-at"
                    name="expires_at"
                    type="date"
                    :default-value="coupon?.expiresAt?.slice(0, 10)"
                />
                <InputError :message="errors.expires_at" />
            </div>
        </div>

        <div class="space-y-1.5">
            <Label for="coupon-description">Internal note</Label>
            <Textarea
                id="coupon-description"
                name="description"
                rows="3"
                maxlength="500"
                :default-value="coupon?.description ?? undefined"
                placeholder="What this code is for. Never shown to shoppers."
            />
            <InputError :message="errors.description" />
        </div>

        <div class="flex items-start gap-3">
            <Checkbox
                id="coupon-active"
                name="is_active"
                :default-value="coupon ? coupon.isActive : true"
            />
            <div class="space-y-1">
                <Label for="coupon-active">Active</Label>
                <p class="text-muted-foreground text-xs">
                    An inactive code is refused at checkout whatever its dates
                    say. The redemption count it has already taken is untouched.
                </p>
                <InputError :message="errors.is_active" />
            </div>
        </div>
    </div>
</template>

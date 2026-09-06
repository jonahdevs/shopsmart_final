<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import CheckoutSettingsController from '@/actions/App/Http/Controllers/Admin/Settings/CheckoutSettingsController';
import SettingsField from '@/components/admin/settings/SettingsField.vue';
import SettingsForm from '@/components/admin/settings/SettingsForm.vue';
import SettingsSection from '@/components/admin/settings/SettingsSection.vue';
import SettingsToggle from '@/components/admin/settings/SettingsToggle.vue';
import { Input } from '@/components/ui/input';
import { Textarea } from '@/components/ui/textarea';
import { checkout as checkoutRoute } from '@/routes/admin/settings';

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Checkout', href: checkoutRoute() }],
    },
});

defineProps<{
    checkout: {
        min_order_value: number;
        order_prefix: string;
        guest_checkout_enabled: boolean;
    };
    payments: {
        paystack_enabled: boolean;
        bank_transfer_enabled: boolean;
        bank_details: string;
        cash_on_delivery_enabled: boolean;
    };
    paymentApi: {
        paystack_public_key: string | null;
        paystack_secret_key_set: boolean;
    };
    currencySymbol: string;
}>();
</script>

<template>
    <div>
        <Head title="Checkout settings" />

        <SettingsForm
            :action="CheckoutSettingsController.update.form()"
            title="Checkout"
            description="The rules an order has to clear, and the ways money can reach the store."
            v-slot="{ errors }"
        >
            <SettingsSection
                title="Order rules"
                description="Enforced when an order is placed, not when a basket is filled."
            >
                <div class="grid gap-6 sm:grid-cols-2">
                    <SettingsField
                        name="min_order_value"
                        label="Minimum order value"
                        :hint="`In whole ${currencySymbol}. Zero removes the floor.`"
                        :error="errors.min_order_value"
                        v-slot="{ id }"
                    >
                        <Input
                            :id="id"
                            type="number"
                            name="min_order_value"
                            :default-value="checkout.min_order_value"
                            min="0"
                            step="0.01"
                            required
                        />
                    </SettingsField>

                    <SettingsField
                        name="order_prefix"
                        label="Order number prefix"
                        hint="Prepended to every order number, e.g. SS-."
                        :error="errors.order_prefix"
                        v-slot="{ id }"
                    >
                        <Input
                            :id="id"
                            name="order_prefix"
                            :default-value="checkout.order_prefix"
                        />
                    </SettingsField>
                </div>

                <SettingsToggle
                    name="guest_checkout_enabled"
                    label="Allow guest checkout"
                    description="Lets a shopper order without creating an account."
                    :checked="checkout.guest_checkout_enabled"
                    :error="errors.guest_checkout_enabled"
                />
            </SettingsSection>

            <SettingsSection
                title="Payment methods"
                description="Paystack is the only online gateway; the rest are arrangements you settle off-site."
            >
                <SettingsToggle
                    name="paystack_enabled"
                    label="Paystack"
                    description="Cards, M-Pesa, Airtel Money and bank transfer in one integration."
                    :checked="payments.paystack_enabled"
                    :error="errors.paystack_enabled"
                />

                <SettingsToggle
                    name="cash_on_delivery_enabled"
                    label="Cash on delivery"
                    :checked="payments.cash_on_delivery_enabled"
                    :error="errors.cash_on_delivery_enabled"
                />

                <SettingsToggle
                    name="bank_transfer_enabled"
                    label="Direct bank transfer"
                    :checked="payments.bank_transfer_enabled"
                    :error="errors.bank_transfer_enabled"
                />

                <SettingsField
                    name="bank_details"
                    label="Bank details"
                    hint="Shown to the customer when they choose bank transfer. Stored encrypted."
                    :error="errors.bank_details"
                    v-slot="{ id }"
                >
                    <Textarea
                        :id="id"
                        name="bank_details"
                        :default-value="payments.bank_details"
                        rows="4"
                    />
                </SettingsField>
            </SettingsSection>

            <SettingsSection
                title="Paystack credentials"
                description="The secret key is never sent back to this page. Leave it blank to keep the key already stored."
            >
                <SettingsField
                    name="paystack_public_key"
                    label="Public key"
                    :error="errors.paystack_public_key"
                    v-slot="{ id }"
                >
                    <Input
                        :id="id"
                        name="paystack_public_key"
                        :default-value="paymentApi.paystack_public_key ?? ''"
                        autocomplete="off"
                    />
                </SettingsField>

                <SettingsField
                    name="paystack_secret_key"
                    label="Secret key"
                    :hint="
                        paymentApi.paystack_secret_key_set
                            ? 'A secret key is stored. Type a new one to replace it.'
                            : 'No secret key is stored yet.'
                    "
                    :error="errors.paystack_secret_key"
                    v-slot="{ id }"
                >
                    <Input
                        :id="id"
                        type="password"
                        name="paystack_secret_key"
                        autocomplete="new-password"
                        placeholder="sk_..."
                    />
                </SettingsField>
            </SettingsSection>
        </SettingsForm>
    </div>
</template>

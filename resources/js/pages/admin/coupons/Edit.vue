<script setup lang="ts">
import { Form, Head, Link } from '@inertiajs/vue3';
import CouponController from '@/actions/App/Http/Controllers/Admin/CouponController';
import AdminPageHeader from '@/components/admin/AdminPageHeader.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { index as adminCoupons, show as adminCoupon } from '@/routes/admin/coupons';
import CouponFields from './CouponFields.vue';

const { coupon } = defineProps<{
    coupon: App.Data.AdminCouponRowData;
    typeOptions: { value: string; label: string }[];
}>();

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Dashboard', href: '/admin' },
            { title: 'Coupons', href: '/admin/coupons' },
        ],
    },
});
</script>

<template>
    <div class="flex flex-col gap-6 p-4">
        <Head :title="`Edit ${coupon.code}`" />

        <AdminPageHeader
            :title="`Edit ${coupon.code}`"
            :description="`Redeemed ${coupon.redemptionCount} time${coupon.redemptionCount === 1 ? '' : 's'}. Editing the terms does not change what past orders were charged.`"
        >
            <template #actions>
                <Button variant="outline" size="sm" as-child>
                    <Link :href="adminCoupon(coupon.id)">Back to coupon</Link>
                </Button>
            </template>
        </AdminPageHeader>

        <Card>
            <CardContent class="pt-6">
                <Form
                    v-bind="CouponController.update.form(coupon.id)"
                    class="space-y-6"
                    v-slot="{ errors, processing }"
                >
                    <CouponFields
                        :coupon="coupon"
                        :type-options="typeOptions"
                        :errors="errors"
                    />

                    <div class="flex items-center gap-3 border-t pt-4">
                        <Button type="submit" :disabled="processing">
                            Save changes
                        </Button>
                        <Button variant="ghost" as-child>
                            <Link :href="adminCoupons()">Cancel</Link>
                        </Button>
                    </div>
                </Form>
            </CardContent>
        </Card>
    </div>
</template>

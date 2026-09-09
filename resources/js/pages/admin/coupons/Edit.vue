<script setup lang="ts">
import { Form, Head, Link } from '@inertiajs/vue3';
import { ArrowLeft } from '@lucide/vue';
import CouponController from '@/actions/App/Http/Controllers/Admin/CouponController';
import AdminCard from '@/components/admin/AdminCard.vue';
import AdminCardHeader from '@/components/admin/AdminCardHeader.vue';
import AdminPageHeader from '@/components/admin/AdminPageHeader.vue';
import { Button } from '@/components/ui/button';
import { dashboard as adminDashboard } from '@/routes/admin';
import {
    index as adminCoupons,
    show as adminCoupon,
} from '@/routes/admin/coupons';
import CouponFields from './CouponFields.vue';

const { coupon } = defineProps<{
    coupon: App.Data.AdminCouponRowData;
    typeOptions: { value: string; label: string }[];
}>();

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Dashboard', href: adminDashboard().url },
            { title: 'Coupons', href: adminCoupons().url },
        ],
    },
});
</script>

<template>
    <div class="flex flex-col gap-6">
        <Head :title="`Edit ${coupon.code}`" />

        <AdminPageHeader
            :title="`Edit ${coupon.code}`"
            :description="`Redeemed ${coupon.redemptionCount} time${coupon.redemptionCount === 1 ? '' : 's'}. Editing the terms does not change what past orders were charged.`"
        >
            <template #actions>
                <Button variant="outline" size="sm" as-child>
                    <Link :href="adminCoupon(coupon.id)">
                        <ArrowLeft class="size-4" aria-hidden="true" />
                        Back to coupon
                    </Link>
                </Button>
            </template>
        </AdminPageHeader>

        <AdminCard>
            <AdminCardHeader title="Coupon details" />

            <Form
                v-bind="CouponController.update.form(coupon.id)"
                v-slot="{ errors, processing }"
            >
                <div class="px-5 py-5">
                    <CouponFields
                        :coupon="coupon"
                        :type-options="typeOptions"
                        :errors="errors"
                    />
                </div>

                <div class="flex items-center gap-3 border-t px-5 py-3">
                    <Button type="submit" :disabled="processing">
                        Save changes
                    </Button>
                    <Button variant="ghost" as-child>
                        <Link :href="adminCoupons()">Cancel</Link>
                    </Button>
                </div>
            </Form>
        </AdminCard>
    </div>
</template>

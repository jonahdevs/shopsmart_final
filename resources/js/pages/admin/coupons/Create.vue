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
    create as adminCouponCreate,
    index as adminCoupons,
} from '@/routes/admin/coupons';
import CouponFields from './CouponFields.vue';

defineProps<{
    typeOptions: { value: string; label: string }[];
}>();

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Dashboard', href: adminDashboard().url },
            { title: 'Coupons', href: adminCoupons().url },
            { title: 'New coupon', href: adminCouponCreate().url },
        ],
    },
});
</script>

<template>
    <div class="flex flex-col gap-6">
        <Head title="New coupon" />

        <AdminPageHeader
            title="New coupon"
            description="Amounts are typed in whole KES; the store converts them."
        >
            <template #actions>
                <Button variant="outline" size="sm" as-child>
                    <Link :href="adminCoupons()">
                        <ArrowLeft class="size-4" aria-hidden="true" />
                        Back to coupons
                    </Link>
                </Button>
            </template>
        </AdminPageHeader>

        <!--
          The submit row is a bordered strip rather than another card: it is the
          foot of this one form, not a second thing on the page.
        -->
        <AdminCard>
            <AdminCardHeader title="Coupon details" />

            <Form
                v-bind="CouponController.store.form()"
                v-slot="{ errors, processing }"
            >
                <div class="px-5 py-5">
                    <CouponFields
                        :coupon="null"
                        :type-options="typeOptions"
                        :errors="errors"
                    />
                </div>

                <div class="flex items-center gap-3 border-t px-5 py-3">
                    <Button type="submit" :disabled="processing">
                        Create coupon
                    </Button>
                    <Button variant="ghost" as-child>
                        <Link :href="adminCoupons()">Cancel</Link>
                    </Button>
                </div>
            </Form>
        </AdminCard>
    </div>
</template>

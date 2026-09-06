<script setup lang="ts">
import { Form, Head, Link } from '@inertiajs/vue3';
import CouponController from '@/actions/App/Http/Controllers/Admin/CouponController';
import AdminPageHeader from '@/components/admin/AdminPageHeader.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { index as adminCoupons } from '@/routes/admin/coupons';
import CouponFields from './CouponFields.vue';

defineProps<{
    typeOptions: { value: string; label: string }[];
}>();

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Dashboard', href: '/admin' },
            { title: 'Coupons', href: '/admin/coupons' },
            { title: 'New coupon', href: '/admin/coupons/create' },
        ],
    },
});
</script>

<template>
    <div class="flex flex-col gap-6 p-4">
        <Head title="New coupon" />

        <AdminPageHeader
            title="New coupon"
            description="Amounts are typed in whole KES; the store converts them."
        >
            <template #actions>
                <Button variant="outline" size="sm" as-child>
                    <Link :href="adminCoupons()">Back to coupons</Link>
                </Button>
            </template>
        </AdminPageHeader>

        <Card>
            <CardContent class="pt-6">
                <Form
                    v-bind="CouponController.store.form()"
                    class="space-y-6"
                    v-slot="{ errors, processing }"
                >
                    <CouponFields
                        :coupon="null"
                        :type-options="typeOptions"
                        :errors="errors"
                    />

                    <div class="flex items-center gap-3 border-t pt-4">
                        <Button type="submit" :disabled="processing">
                            Create coupon
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

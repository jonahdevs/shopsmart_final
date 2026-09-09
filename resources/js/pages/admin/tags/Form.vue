<script setup lang="ts">
import { Form, Head, Link } from '@inertiajs/vue3';
import { Info, Tags, Trash2 } from '@lucide/vue';
import { computed } from 'vue';
import TagController from '@/actions/App/Http/Controllers/Admin/TagController';
import AdminCard from '@/components/admin/AdminCard.vue';
import AdminCardHeader from '@/components/admin/AdminCardHeader.vue';
import AdminPageHeader from '@/components/admin/AdminPageHeader.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { dashboard as adminDashboard } from '@/routes/admin';
import { index as adminTags } from '@/routes/admin/tags';
import { index as adminTagProducts } from '@/routes/admin/tags/products';

const props = defineProps<{
    tag: App.Data.AdminTagFormData;
    /** How many products would lose the tag if it were deleted. */
    productCount: number;
}>();

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Dashboard', href: adminDashboard().url },
            { title: 'Tags', href: adminTags().url },
        ],
    },
});

const isNew = computed(() => props.tag.id === null);

/** Zero while the tag does not exist yet — nothing renders it then. */
const tagId = computed(() => props.tag.id ?? 0);

const submitTarget = computed(() =>
    props.tag.id === null
        ? TagController.store.form()
        : TagController.update.form(props.tag.id),
);
</script>

<template>
    <div class="flex flex-col gap-6">
        <Head :title="isNew ? 'New tag' : tag.name" />

        <!--
          The page header sits inside the form so Save stays an ordinary submit
          button instead of a detached one wired back by `form=`. The delete
          form below is a sibling, not a child — forms cannot nest.
        -->
        <Form
            v-bind="submitTarget"
            class="flex flex-col gap-6"
            v-slot="{ errors, processing }"
        >
            <AdminPageHeader
                :title="isNew ? 'New tag' : tag.name"
                description="A label products are merchandised by. The slug is made from the name."
            >
                <template #actions>
                    <Button variant="outline" size="sm" as-child>
                        <Link :href="adminTags()">Cancel</Link>
                    </Button>
                    <Button type="submit" size="sm" :disabled="processing">
                        {{ isNew ? 'Create tag' : 'Save tag' }}
                    </Button>
                </template>
            </AdminPageHeader>

            <div class="grid gap-6 lg:grid-cols-3">
                <div class="space-y-6 lg:col-span-2">
                    <AdminCard>
                        <AdminCardHeader title="Details" :icon="Tags" />

                        <div class="space-y-4 p-5">
                            <div class="space-y-1.5">
                                <Label for="name">Name</Label>
                                <Input
                                    id="name"
                                    name="name"
                                    :default-value="tag.name"
                                    required
                                    maxlength="255"
                                    placeholder="e.g. Featured"
                                />
                                <InputError :message="errors.name" />
                                <p class="text-muted-foreground text-xs">
                                    This is the name the storefront matches on,
                                    so renaming a tag changes which list it
                                    drives.
                                </p>
                            </div>

                            <div v-if="!isNew" class="space-y-1.5">
                                <Label for="slug">Slug</Label>
                                <!--
                                  Read-only rather than absent. The package
                                  writes it from the name and nothing looks a
                                  tag up by it, so there is nothing here to
                                  edit — but a merchandiser comparing this
                                  screen with a URL still wants to see it.
                                -->
                                <Input
                                    id="slug"
                                    :model-value="tag.slug ?? ''"
                                    readonly
                                    disabled
                                />
                            </div>
                        </div>
                    </AdminCard>
                </div>

                <!--
                  What the tag *does*, beside what it is called. A tag looks
                  inert on this screen and is not: two of them are wired
                  straight into the shop front, and the only way to see what one
                  carries is the membership screen this panel points at.
                -->
                <AdminCard class="h-fit">
                    <AdminCardHeader title="What a tag does" :icon="Info" />

                    <div class="text-muted-foreground space-y-3 p-5 text-sm">
                        <p>
                            Tags drive storefront lists by name. "Featured"
                            fills the home page rail, "New Arrival" puts the
                            badge on a card, and every tag can be browsed at
                            <span class="font-mono text-xs">/shop?tag=</span>.
                        </p>

                        <template v-if="!isNew">
                            <p>
                                {{ productCount }}
                                product{{ productCount === 1 ? '' : 's' }}
                                currently carry this tag.
                            </p>

                            <Button variant="outline" size="sm" as-child>
                                <Link :href="adminTagProducts(tagId)">
                                    Manage products
                                </Link>
                            </Button>
                        </template>
                    </div>
                </AdminCard>
            </div>
        </Form>

        <AdminCard v-if="!isNew">
            <AdminCardHeader title="Delete this tag" :icon="Trash2" />

            <div class="space-y-4 p-5">
                <!--
                  Not refused when products carry it, unlike a category with
                  children — nothing is orphaned by the delete. The cost is real
                  all the same, so it is counted here rather than discovered
                  when a storefront rail turns up empty.
                -->
                <p class="text-muted-foreground text-sm">
                    <template v-if="productCount === 0">
                        No products carry this tag, so removing it changes
                        nothing on the storefront.
                    </template>
                    <template v-else>
                        {{ productCount }}
                        product{{ productCount === 1 ? '' : 's' }} will lose this
                        tag. The products themselves are not deleted, but any
                        storefront list built on the tag loses them.
                    </template>
                </p>

                <Form
                    v-bind="TagController.destroy.form(tagId)"
                    v-slot="{ processing: deleting }"
                >
                    <Button
                        type="submit"
                        variant="destructive"
                        :disabled="deleting"
                    >
                        <Trash2 class="size-4" aria-hidden="true" />
                        Delete tag
                    </Button>
                </Form>
            </div>
        </AdminCard>
    </div>
</template>

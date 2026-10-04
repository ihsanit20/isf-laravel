<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { Tags, UserCog } from 'lucide-vue-next';
import PageHeader from '@/components/shared/PageHeader.vue';

defineOptions({
    layout: {
        breadcrumbs: [
            {
                title: 'Settings',
                href: '/admin/settings',
            },
        ],
    },
});

const items = [
    {
        title: 'Charge categories',
        description:
            'Fee types such as the registration fee and their default amounts.',
        href: '/admin/charge-categories',
        icon: Tags,
    },
    {
        title: 'Users',
        description: 'Accounts and their roles.',
        href: '/admin/users',
        icon: UserCog,
    },
];
</script>

<template>
    <Head title="Settings" />

    <div class="flex flex-1 flex-col gap-6 p-4 md:p-6">
        <PageHeader
            title="Settings"
            description="Administrative configuration."
        />

        <div class="grid gap-4 sm:grid-cols-2">
            <Link
                v-for="item in items"
                :key="item.href"
                :href="item.href"
                class="flex items-start gap-3 rounded-xl border bg-card p-4 shadow-xs transition-colors hover:border-foreground/20"
            >
                <component
                    :is="item.icon"
                    class="mt-0.5 size-5 text-muted-foreground"
                />
                <div>
                    <p class="font-medium">{{ item.title }}</p>
                    <p class="text-sm text-muted-foreground">
                        {{ item.description }}
                    </p>
                </div>
            </Link>
        </div>
    </div>
</template>

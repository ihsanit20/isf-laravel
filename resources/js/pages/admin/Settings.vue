<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { BookMarked, SquarePen } from 'lucide-vue-next';
import { Button } from '@/components/ui/button';

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
</script>

<template>
    <Head title="Settings" />

    <div class="flex h-full flex-1 flex-col gap-4 rounded-xl p-4">
        <section
            class="rounded-xl border border-sidebar-border/70 bg-background p-6 shadow-sm dark:border-sidebar-border"
        >
            <div class="space-y-2">
                <h1 class="text-2xl font-semibold tracking-tight">Settings</h1>
                <p class="text-sm text-muted-foreground">
                    Administrative configuration options. More settings buttons
                    will be added here in future.
                </p>
            </div>
        </section>

        <section
            class="rounded-xl border border-sidebar-border/70 bg-background p-6 shadow-sm dark:border-sidebar-border"
        >
            <div class="grid gap-3 sm:grid-cols-2">
                <Button as-child variant="outline" class="h-12 justify-start">
                    <Link href="/admin/charge-categories">
                        <BookMarked class="size-4" />
                        Charge Categories
                    </Link>
                </Button>

                <Button as-child variant="outline" class="h-12 justify-start">
                    <Link href="/admin/users">
                        <SquarePen class="size-4" />
                        User List
                    </Link>
                </Button>
            </div>
        </section>
    </div>
</template>

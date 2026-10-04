<script setup lang="ts">
type Props = {
    title: string;
    description?: string;
};

defineProps<Props>();
</script>

<template>
    <header
        class="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between"
    >
        <div class="min-w-0 space-y-1">
            <h1 class="text-2xl font-semibold tracking-tight">{{ title }}</h1>
            <p
                v-if="description"
                class="max-w-2xl text-sm text-muted-foreground"
            >
                {{ description }}
            </p>
        </div>
        <div v-if="$slots.actions" class="flex shrink-0 flex-wrap gap-2">
            <slot name="actions" />
        </div>
    </header>
</template>

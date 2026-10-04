<script setup lang="ts">
import type { Component } from 'vue';

type Props = {
    title: string;
    description?: string;
    icon?: Component;
};

defineProps<Props>();
</script>

<template>
    <div
        class="flex flex-col items-center justify-center gap-2 rounded-xl border border-dashed px-6 py-10 text-center"
    >
        <component
            :is="icon"
            v-if="icon"
            class="size-8 text-muted-foreground/60"
        />
        <p class="font-medium">{{ title }}</p>
        <p v-if="description" class="max-w-md text-sm text-muted-foreground">
            {{ description }}
        </p>
        <div v-if="$slots.default" class="mt-2">
            <slot />
        </div>
    </div>
</template>

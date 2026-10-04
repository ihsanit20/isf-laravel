<script setup lang="ts">
import type { Component } from 'vue';
import { cn } from '@/lib/utils';

type Props = {
    label: string;
    value: string | number;
    hint?: string;
    icon?: Component;
    valueClass?: string;
};

defineProps<Props>();
</script>

<template>
    <div class="rounded-xl border bg-card p-4 text-card-foreground shadow-xs">
        <div class="flex items-center justify-between gap-2">
            <p class="text-sm text-muted-foreground">{{ label }}</p>
            <component
                :is="icon"
                v-if="icon"
                class="size-4 text-muted-foreground"
            />
        </div>
        <p
            :class="
                cn('mt-2 text-2xl font-semibold tracking-tight', valueClass)
            "
        >
            {{ value }}
        </p>
        <p v-if="hint" class="mt-1 text-xs text-muted-foreground">
            {{ hint }}
        </p>
    </div>
</template>

<script setup lang="ts">
import { cn } from '@/lib/utils';

export type ActivityTone = 'success' | 'warning' | 'danger';

export type ActivityItem = {
    id: string;
    title: string;
    description: string;
    timestamp: string | null;
    tone: ActivityTone;
};

defineProps<{
    items: ActivityItem[];
}>();

const toneDot: Record<ActivityTone, string> = {
    success: 'bg-emerald-500',
    warning: 'bg-amber-500',
    danger: 'bg-rose-500',
};
</script>

<template>
    <p v-if="items.length === 0" class="text-sm text-muted-foreground">
        Nothing yet.
    </p>
    <ul v-else class="grid gap-3">
        <li v-for="item in items" :key="item.id" class="flex gap-3">
            <span
                :class="
                    cn(
                        'mt-1.5 size-2 shrink-0 rounded-full',
                        toneDot[item.tone],
                    )
                "
            />
            <div class="min-w-0 flex-1">
                <p class="text-sm">
                    <span class="font-medium">{{ item.title }}</span>
                    <span class="text-muted-foreground">
                        · {{ item.description }}
                    </span>
                </p>
                <p class="text-xs text-muted-foreground">
                    {{ item.timestamp }}
                </p>
            </div>
        </li>
    </ul>
</template>

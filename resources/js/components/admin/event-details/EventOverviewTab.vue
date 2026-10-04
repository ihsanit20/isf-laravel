<script setup lang="ts">
import { ChevronDown } from 'lucide-vue-next';
import { computed, ref } from 'vue';
import type { EventDetails } from '@/components/admin/event-details/types';
import StatusBadge from '@/components/shared/StatusBadge.vue';
import { Button } from '@/components/ui/button';
import { formatDate, formatDateTime } from '@/lib/format';

const props = defineProps<{
    event: EventDetails;
}>();

const isDescriptionExpanded = ref(false);

const description = computed(
    () => props.event.description ?? 'No event description provided yet.',
);
const isLongDescription = computed(() => description.value.length > 420);
const displayedDescription = computed(() => {
    if (isDescriptionExpanded.value || !isLongDescription.value) {
        return description.value;
    }

    return `${description.value.slice(0, 420).trimEnd()}...`;
});

const facts = computed(() => [
    { label: 'Orders open', value: formatDateTime(props.event.order_open_at) },
    {
        label: 'Orders close',
        value: formatDateTime(props.event.order_close_at),
    },
    {
        label: 'Expected delivery',
        value: formatDate(props.event.expected_delivery_date),
    },
    { label: 'Created', value: props.event.created_at || '—' },
    { label: 'Last updated', value: props.event.updated_at || '—' },
    { label: 'Slug', value: props.event.slug },
]);
</script>

<template>
    <div class="grid gap-6 lg:grid-cols-[1.5fr_1fr]">
        <div>
            <h3 class="text-sm font-medium">Description</h3>
            <p
                class="mt-2 text-sm leading-7 whitespace-pre-line text-foreground/90"
            >
                {{ displayedDescription }}
            </p>
            <Button
                v-if="isLongDescription"
                variant="ghost"
                size="sm"
                class="mt-2 h-8 px-2 text-xs"
                @click="isDescriptionExpanded = !isDescriptionExpanded"
            >
                {{
                    isDescriptionExpanded
                        ? 'Show less'
                        : 'Read full description'
                }}
                <ChevronDown
                    class="size-3.5 transition-transform"
                    :class="{ 'rotate-180': isDescriptionExpanded }"
                />
            </Button>
        </div>

        <div class="grid h-fit gap-4">
            <dl class="grid gap-2 rounded-xl border p-4 text-sm">
                <div
                    v-for="fact in facts"
                    :key="fact.label"
                    class="flex justify-between gap-3"
                >
                    <dt class="text-muted-foreground">{{ fact.label }}</dt>
                    <dd class="text-right font-medium break-all">
                        {{ fact.value }}
                    </dd>
                </div>
            </dl>

            <dl class="grid gap-2 rounded-xl border p-4 text-sm">
                <div class="flex justify-between gap-3">
                    <dt class="text-muted-foreground">Fund cycle</dt>
                    <dd class="font-medium">
                        {{ props.event.fund_cycle.name || '—' }}
                    </dd>
                </div>
                <div class="flex justify-between gap-3">
                    <dt class="text-muted-foreground">Cycle status</dt>
                    <dd>
                        <StatusBadge
                            :status="props.event.fund_cycle.status"
                            :label="props.event.fund_cycle.status_label"
                        />
                    </dd>
                </div>
            </dl>
        </div>
    </div>
</template>

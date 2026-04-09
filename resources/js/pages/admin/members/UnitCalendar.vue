<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import UnitCalendarView from '@/components/members/UnitCalendarView.vue';

defineOptions({
    layout: {
        breadcrumbs: [
            {
                title: 'Member List',
                href: '/admin/members',
            },
            {
                title: 'Unit Calendar',
                href: '#',
            },
        ],
    },
});

defineProps<{
    member: {
        id: number;
        full_name: string;
        relationship_to_user: string;
        phone: string | null;
        units: number;
        status: 'pending' | 'approved' | 'rejected' | 'exited';
        manager: {
            name: string | null;
            email: string | null;
        };
    };
    selectedYear: number;
    availableYears: number[];
    yearlySummary: {
        total_units: number;
        total_amount: number;
        paid_months: number;
    };
    months: Array<{
        month_number: number;
        month_key: string;
        month_label: string;
        is_paid: boolean;
        total_units: number;
        total_amount: number;
        entries: Array<{
            id: number;
            units: number;
            amount: number;
            confirmed_at: string | null;
        }>;
    }>;
    backUrl: string;
    backLabel: string;
    calendarBaseUrl: string;
}>();
</script>

<template>
    <Head title="Unit Calendar" />
    <UnitCalendarView v-bind="$props" />
</template>

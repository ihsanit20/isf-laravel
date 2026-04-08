<script setup>
import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout.vue";
import { Head, Link } from "@inertiajs/vue3";

defineProps({
    roleLabel: {
        type: String,
        required: true,
    },
    stats: {
        type: Array,
        required: true,
    },
    linkedMembers: {
        type: Array,
        default: () => [],
    },
    recentMembers: {
        type: Array,
        default: () => [],
    },
});
</script>

<template>
    <Head title="Dashboard" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center justify-between gap-4">
                <div>
                    <h2
                        class="text-xl font-semibold leading-tight text-gray-800"
                    >
                        Dashboard
                    </h2>
                    <p class="mt-1 text-sm text-gray-500">
                        Role: {{ roleLabel }}
                    </p>
                </div>

                <Link
                    v-if="$page.props.auth.can.manageMembers"
                    :href="route('admin.members.index')"
                    class="inline-flex items-center rounded-md bg-emerald-600 px-4 py-2 text-sm font-semibold text-white transition hover:bg-emerald-500"
                >
                    Manage members
                </Link>
            </div>
        </template>

        <div class="py-12">
            <div class="mx-auto max-w-7xl space-y-6 sm:px-6 lg:px-8">
                <section class="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
                    <article
                        v-for="item in stats"
                        :key="item.label"
                        class="overflow-hidden rounded-lg bg-white p-6 shadow-sm"
                    >
                        <p class="text-sm font-medium text-gray-500">
                            {{ item.label }}
                        </p>
                        <p class="mt-3 text-3xl font-semibold text-gray-900">
                            {{ item.value }}
                        </p>
                        <p class="mt-2 text-sm text-gray-600">
                            {{ item.detail }}
                        </p>
                    </article>
                </section>

                <section
                    v-if="$page.props.auth.can.manageMembers"
                    class="overflow-hidden rounded-lg bg-white shadow-sm"
                >
                    <div class="border-b border-gray-200 px-6 py-4">
                        <h3 class="text-lg font-semibold text-gray-900">
                            Recent member profiles
                        </h3>
                    </div>

                    <div
                        v-if="recentMembers.length"
                        class="divide-y divide-gray-200"
                    >
                        <div
                            v-for="member in recentMembers"
                            :key="member.id"
                            class="flex flex-col gap-3 px-6 py-4 sm:flex-row sm:items-center sm:justify-between"
                        >
                            <div>
                                <p class="font-medium text-gray-900">
                                    {{ member.name }}
                                </p>
                                <p class="text-sm text-gray-500">
                                    {{ member.member_code }}
                                    <span v-if="member.user_name">
                                        · Linked to {{ member.user_name }}</span
                                    >
                                </p>
                            </div>
                            <span
                                class="inline-flex w-fit rounded-full bg-emerald-50 px-3 py-1 text-xs font-semibold uppercase tracking-wide text-emerald-700"
                            >
                                {{ member.status }}
                            </span>
                        </div>
                    </div>

                    <div v-else class="px-6 py-8 text-sm text-gray-500">
                        No member profiles yet.
                    </div>
                </section>

                <section
                    v-else
                    class="overflow-hidden rounded-lg bg-white shadow-sm"
                >
                    <div class="border-b border-gray-200 px-6 py-4">
                        <h3 class="text-lg font-semibold text-gray-900">
                            Linked memberships
                        </h3>
                    </div>

                    <div
                        v-if="linkedMembers.length"
                        class="divide-y divide-gray-200"
                    >
                        <div
                            v-for="member in linkedMembers"
                            :key="member.id"
                            class="px-6 py-4"
                        >
                            <div
                                class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between"
                            >
                                <div>
                                    <p class="font-medium text-gray-900">
                                        {{ member.name }}
                                    </p>
                                    <p class="text-sm text-gray-500">
                                        {{ member.member_code }}
                                    </p>
                                </div>
                                <span
                                    class="inline-flex w-fit rounded-full bg-slate-100 px-3 py-1 text-xs font-semibold uppercase tracking-wide text-slate-700"
                                >
                                    {{ member.status }}
                                </span>
                            </div>
                            <p class="mt-2 text-sm text-gray-600">
                                Joined {{ member.joined_at }}
                            </p>
                        </div>
                    </div>

                    <div v-else class="px-6 py-8 text-sm text-gray-500">
                        No member profile is linked to this account yet. An
                        admin can create one and assign it to your login.
                    </div>
                </section>
            </div>
        </div>
    </AuthenticatedLayout>
</template>

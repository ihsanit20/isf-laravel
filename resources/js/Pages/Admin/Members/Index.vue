<script setup>
import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout.vue";
import InputError from "@/Components/InputError.vue";
import InputLabel from "@/Components/InputLabel.vue";
import PrimaryButton from "@/Components/PrimaryButton.vue";
import TextInput from "@/Components/TextInput.vue";
import { Head, useForm, usePage } from "@inertiajs/vue3";

const props = defineProps({
    members: {
        type: Array,
        required: true,
    },
    eligibleUsers: {
        type: Array,
        required: true,
    },
    memberStatuses: {
        type: Array,
        required: true,
    },
});

const page = usePage();

const createForm = useForm({
    user_id: "",
    name: "",
    phone: "",
    status: props.memberStatuses[0] ?? "active",
    joined_at: new Date().toISOString().slice(0, 10),
});

const statusForms = Object.fromEntries(
    props.members.map((member) => [
        member.id,
        useForm({
            user_id: member.user_id ?? "",
            status: member.status,
        }),
    ]),
);

const submitCreate = () => {
    createForm
        .transform((data) => ({
            ...data,
            user_id: data.user_id || null,
        }))
        .post(route("admin.members.store"), {
            preserveScroll: true,
            onSuccess: () => {
                createForm.reset("user_id", "name", "phone");
                createForm.joined_at = new Date().toISOString().slice(0, 10);
                createForm.status = props.memberStatuses[0] ?? "active";
            },
        });
};

const updateMember = (memberId) => {
    statusForms[memberId]
        .transform((data) => ({
            ...data,
            user_id: data.user_id || null,
        }))
        .patch(route("admin.members.update", memberId), {
            preserveScroll: true,
        });
};
</script>

<template>
    <Head title="Members" />

    <AuthenticatedLayout>
        <template #header>
            <div>
                <h2 class="text-xl font-semibold leading-tight text-gray-800">
                    Members
                </h2>
                <p class="mt-1 text-sm text-gray-500">
                    Create member profiles and assign them to login accounts.
                </p>
            </div>
        </template>

        <div class="py-12">
            <div class="mx-auto max-w-7xl space-y-6 sm:px-6 lg:px-8">
                <div
                    v-if="page.props.flash?.success"
                    class="rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-700"
                >
                    {{ page.props.flash.success }}
                </div>

                <section class="rounded-lg bg-white p-6 shadow-sm">
                    <h3 class="text-lg font-semibold text-gray-900">
                        Create member profile
                    </h3>

                    <form
                        class="mt-6 grid gap-4 md:grid-cols-2"
                        @submit.prevent="submitCreate"
                    >
                        <div class="md:col-span-2">
                            <InputLabel for="name" value="Member name" />
                            <TextInput
                                id="name"
                                v-model="createForm.name"
                                type="text"
                                class="mt-1 block w-full"
                                required
                            />
                            <InputError
                                class="mt-2"
                                :message="createForm.errors.name"
                            />
                        </div>

                        <div>
                            <InputLabel for="phone" value="Phone" />
                            <TextInput
                                id="phone"
                                v-model="createForm.phone"
                                type="text"
                                class="mt-1 block w-full"
                            />
                            <InputError
                                class="mt-2"
                                :message="createForm.errors.phone"
                            />
                        </div>

                        <div>
                            <InputLabel for="joined_at" value="Joined date" />
                            <TextInput
                                id="joined_at"
                                v-model="createForm.joined_at"
                                type="date"
                                class="mt-1 block w-full"
                                required
                            />
                            <InputError
                                class="mt-2"
                                :message="createForm.errors.joined_at"
                            />
                        </div>

                        <div>
                            <InputLabel
                                for="user_id"
                                value="Linked login account"
                            />
                            <select
                                id="user_id"
                                v-model="createForm.user_id"
                                class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                            >
                                <option value="">Not linked yet</option>
                                <option
                                    v-for="user in eligibleUsers"
                                    :key="user.id"
                                    :value="user.id"
                                >
                                    {{ user.name }} ({{ user.email }})
                                </option>
                            </select>
                            <InputError
                                class="mt-2"
                                :message="createForm.errors.user_id"
                            />
                        </div>

                        <div>
                            <InputLabel for="status" value="Status" />
                            <select
                                id="status"
                                v-model="createForm.status"
                                class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                            >
                                <option
                                    v-for="status in memberStatuses"
                                    :key="status"
                                    :value="status"
                                >
                                    {{ status }}
                                </option>
                            </select>
                            <InputError
                                class="mt-2"
                                :message="createForm.errors.status"
                            />
                        </div>

                        <div class="md:col-span-2">
                            <PrimaryButton
                                :disabled="createForm.processing"
                                :class="{ 'opacity-25': createForm.processing }"
                            >
                                Create member
                            </PrimaryButton>
                        </div>
                    </form>
                </section>

                <section class="overflow-hidden rounded-lg bg-white shadow-sm">
                    <div class="border-b border-gray-200 px-6 py-4">
                        <h3 class="text-lg font-semibold text-gray-900">
                            Member directory
                        </h3>
                    </div>

                    <div v-if="members.length" class="divide-y divide-gray-200">
                        <article
                            v-for="member in members"
                            :key="member.id"
                            class="px-6 py-5"
                        >
                            <div
                                class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between"
                            >
                                <div>
                                    <div
                                        class="flex flex-wrap items-center gap-3"
                                    >
                                        <h4
                                            class="text-base font-semibold text-gray-900"
                                        >
                                            {{ member.name }}
                                        </h4>
                                        <span
                                            class="inline-flex rounded-full bg-slate-100 px-3 py-1 text-xs font-semibold uppercase tracking-wide text-slate-700"
                                        >
                                            {{ member.status }}
                                        </span>
                                    </div>
                                    <p class="mt-2 text-sm text-gray-500">
                                        {{ member.member_code }}
                                    </p>
                                    <p class="mt-1 text-sm text-gray-500">
                                        Joined {{ member.joined_at }}
                                        <span v-if="member.phone">
                                            · {{ member.phone }}</span
                                        >
                                    </p>
                                    <p class="mt-1 text-sm text-gray-500">
                                        Linked account:
                                        <span v-if="member.user_name"
                                            >{{ member.user_name }} ({{
                                                member.user_email
                                            }})</span
                                        >
                                        <span v-else>Not linked</span>
                                    </p>
                                </div>

                                <form
                                    class="grid gap-3 sm:min-w-80"
                                    @submit.prevent="updateMember(member.id)"
                                >
                                    <div>
                                        <InputLabel
                                            :for="`linked-user-${member.id}`"
                                            value="Linked login account"
                                        />
                                        <select
                                            :id="`linked-user-${member.id}`"
                                            v-model="
                                                statusForms[member.id].user_id
                                            "
                                            class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                        >
                                            <option value="">Not linked</option>
                                            <option
                                                v-for="user in eligibleUsers"
                                                :key="user.id"
                                                :value="user.id"
                                            >
                                                {{ user.name }} ({{
                                                    user.email
                                                }})
                                            </option>
                                        </select>
                                        <InputError
                                            class="mt-2"
                                            :message="
                                                statusForms[member.id].errors
                                                    .user_id
                                            "
                                        />
                                    </div>

                                    <div>
                                        <InputLabel
                                            :for="`status-${member.id}`"
                                            value="Status"
                                        />
                                        <select
                                            :id="`status-${member.id}`"
                                            v-model="
                                                statusForms[member.id].status
                                            "
                                            class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                        >
                                            <option
                                                v-for="status in memberStatuses"
                                                :key="status"
                                                :value="status"
                                            >
                                                {{ status }}
                                            </option>
                                        </select>
                                        <InputError
                                            class="mt-2"
                                            :message="
                                                statusForms[member.id].errors
                                                    .status
                                            "
                                        />
                                    </div>

                                    <div>
                                        <PrimaryButton
                                            :disabled="
                                                statusForms[member.id]
                                                    .processing
                                            "
                                            :class="{
                                                'opacity-25':
                                                    statusForms[member.id]
                                                        .processing,
                                            }"
                                        >
                                            Save changes
                                        </PrimaryButton>
                                    </div>
                                </form>
                            </div>
                        </article>
                    </div>

                    <div v-else class="px-6 py-8 text-sm text-gray-500">
                        No members yet. Create the first member profile above.
                    </div>
                </section>
            </div>
        </div>
    </AuthenticatedLayout>
</template>

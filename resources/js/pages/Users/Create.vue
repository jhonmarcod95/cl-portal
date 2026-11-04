<script setup lang="ts">
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import MultiSelect from '@/components/ui/multi-select/MultiSelect.vue';
import { Building, Factory, Save } from 'lucide-vue-next';
import ConfirmationDialog from '@/components/ConfirmationDialog.vue';
import { ref } from 'vue';

const props = defineProps<{
    roles: Array<{ id: number; name: string; slug: string }>;
    companies: Array<{ id: number; description: string }>;
    plants: Array<{id:number, plant_code:string, name:string}>;
}>();

const isConfirmDialogOpen = ref(false)
const formElement = ref<HTMLFormElement | null>(null)
const hasAttemptedSubmit = ref(false)

const validateAndConfirm = () => {
    hasAttemptedSubmit.value = true
    if (!formElement.value?.reportValidity()) return

    form.clearErrors('companies', 'companies.0')
    if (form.companies.length === 0) {
        form.setError('companies', 'Select at least one company.')
        return
    }

    isConfirmDialogOpen.value = true
}

const form = useForm({
    name: '',
    email: '',
    password: '',
    role: '' as number | '',
    companies: [] as number[],
    plants: [] as string[],
});

function submit() {
    form.post('/users/store', {
        onSuccess: () => {
            form.reset()
            hasAttemptedSubmit.value = false
            isConfirmDialogOpen.value = false
            router.visit('/users', { replace: true })
        },
        onError: () => {
            isConfirmDialogOpen.value = false
        },
    });
}


</script>

<template>
    <Head title="Create user" />

    <main class="min-h-full bg-[#f2f6fa] pb-10 text-[#1f2e4d]">

        <section class="m-4 overflow-hidden rounded-xl border border-[#d9e3ed] bg-white shadow-sm sm:mx-8">
            <header class="flex flex-wrap items-center justify-between gap-3 border-b border-[#e1e9f1] px-5 py-4 sm:px-7">
                <h1 class="text-lg font-semibold">Create User</h1>

                <Link
                    href="/users"
                    class="inline-flex items-center gap-2 rounded-md bg-slate-700 px-4 py-2 text-sm font-medium text-white transition hover:bg-slate-800"
                >
                    Back to list
                </Link>
            </header>

            <form
                ref="formElement"
                class="space-y-8 bg-[#f8fbfe] px-5 py-6 sm:px-7"
                @invalid.capture="hasAttemptedSubmit = true"
                @submit.prevent="validateAndConfirm"
            >
                <section aria-labelledby="user-information-heading">
                    <h2 id="user-information-heading" class="mb-4 text-xs font-semibold uppercase tracking-wide text-[#71839d]">
                        User Information
                    </h2>

                    <div class="grid gap-5 md:grid-cols-2">
                        <label class="block text-sm font-medium text-[#51627d]">
                            Name <span class="text-rose-500">*</span>
                            <input
                                type="text"
                                v-model="form.name"
                                autocomplete="name"
                                class="mt-1.5 block w-full rounded-md border border-[#d5e0eb] bg-white px-3 py-2.5 text-sm text-[#263858] shadow-sm outline-none transition placeholder:text-[#9aa9bc] focus:border-[#4e70e9] focus:ring-2 focus:ring-[#4e70e9]/15"
                                placeholder="Enter name"
                                @input="form.clearErrors('name')"
                            >
                            <p v-if="form.errors.name || (hasAttemptedSubmit && !form.name.trim())" class="mt-1 text-xs text-rose-600">
                                {{ form.errors.name || 'The name field is required.' }}
                            </p>
                        </label>

                        <label class="block text-sm font-medium text-[#51627d]">
                            Email <span class="text-rose-500">*</span>
                            <input
                                type="email"
                                v-model="form.email"
                                autocomplete="email"
                                class="mt-1.5 block w-full rounded-md border border-[#d5e0eb] bg-white px-3 py-2.5 text-sm text-[#263858] shadow-sm outline-none transition placeholder:text-[#9aa9bc] focus:border-[#4e70e9] focus:ring-2 focus:ring-[#4e70e9]/15"
                                placeholder="Enter email address"
                                @input="form.clearErrors('email')"
                            >
                            <p v-if="form.errors.email || (hasAttemptedSubmit && !form.email.trim())" class="mt-1 text-xs text-rose-600">
                                {{ form.errors.email || 'The email field is required.' }}
                            </p>
                        </label>

                        <label class="block text-sm font-medium text-[#51627d]">
                            Password <span class="text-rose-500">*</span>
                            <input
                                type="password"
                                v-model="form.password"
                                minlength="8"
                                autocomplete="new-password"
                                class="mt-1.5 block w-full rounded-md border border-[#d5e0eb] bg-white px-3 py-2.5 text-sm text-[#263858] shadow-sm outline-none transition placeholder:text-[#9aa9bc] focus:border-[#4e70e9] focus:ring-2 focus:ring-[#4e70e9]/15"
                                placeholder="Enter Password"
                                @input="form.clearErrors('password')"
                            >
                            <p
                                v-if="form.errors.password || (hasAttemptedSubmit && form.password.length < 8)"
                                class="mt-1 text-xs text-rose-600"
                            >
                                {{ form.errors.password || (form.password.length === 0 ? 'The password field is required.' : 'The password must be at least 8 characters.') }}
                            </p>
                        </label>

                        <label class="block text-sm font-medium text-[#51627d]">
                            Role <span class="text-rose-500">*</span>
                            <select
                                v-model="form.role"
                                class="mt-1.5 block w-full rounded-md border border-[#d5e0eb] bg-white px-3 py-2.5 text-sm text-[#263858] shadow-sm outline-none transition focus:border-[#4e70e9] focus:ring-2 focus:ring-[#4e70e9]/15"
                                @change="form.clearErrors('role')"
                            >
                                <option value="">Select a role</option>
                                <option v-for="role in roles" :key="role.id" :value="role.id">
                                    {{ role.name }}
                                </option>
                            </select>
                            <p v-if="form.errors.role || (hasAttemptedSubmit && !form.role)" class="mt-1 text-xs text-rose-600">
                                {{ form.errors.role || 'Select a role.' }}
                            </p>
                        </label>
                    </div>
                </section>

                <div class="grid gap-6 md:grid-cols-2">
                    <section aria-labelledby="company-assignment-heading">
                        <div class="mb-4 flex items-center gap-3">
                            <Building class="size-5 shrink-0" />
                            <h2 id="company-assignment-heading" class="shrink-0 text-xs font-semibold uppercase tracking-wide text-[#71839d]">
                                Company Assignment
                            </h2>
                            <div class="h-px flex-1 bg-[#dce6ef]"></div>
                        </div>

                        <div class="block text-sm font-medium text-[#51627d]">
                            Companies <span class="text-rose-500">*</span>
                            <MultiSelect
                                v-model="form.companies"
                                :options="companies.map((company) => ({ label: company.description, value: company.id }))"
                                name="companies[]"
                                label="Select companies"
                                placeholder="Select one or more companies"
                                searchable
                                aria-required="true"
                                :aria-invalid="Boolean(form.errors.companies || form.errors['companies.0'])"
                                @update:model-value="form.clearErrors('companies', 'companies.0')"
                                class="mt-1.5"
                            />
                            <p
                                v-if="form.errors.companies || form.errors['companies.0'] || (hasAttemptedSubmit && form.companies.length === 0)"
                                class="mt-1 text-xs text-rose-600"
                            >
                                {{ form.errors.companies || form.errors['companies.0'] || 'Select at least one company.' }}
                            </p>
                        </div>
                        <p id="companies-help" class="mt-1.5 text-xs text-[#8a9ab0]">
                            Select one or more companies this user will be assigned to.
                        </p>
                    </section>

                    <section aria-labelledby="plant-assignment-heading">
                        <div class="mb-4 flex items-center gap-3">
                            <Factory class="size-5 shrink-0" />
                            <h2 id="plant-assignment-heading" class="shrink-0 text-xs font-semibold uppercase tracking-wide text-[#71839d]">
                                Plant Assignment
                            </h2>
                            <div class="h-px flex-1 bg-[#dce6ef]"></div>
                        </div>

                        <div class="block text-sm font-medium text-[#51627d]">
                            Plants
                            <MultiSelect
                                v-model="form.plants"
                                :options="plants.map((plant) => ({ label: `${plant.plant_code} - ${plant.name}`, value: plant.plant_code }))"
                                name="plants[]"
                                label="Select plants"
                                placeholder="Select one or more plants"
                                searchable
                                class="mt-1.5"
                            />
                            <p v-if="form.errors.plants || form.errors['plants.0']" class="mt-1 text-xs text-rose-600">
                                {{ form.errors.plants || form.errors['plants.0'] }}
                            </p>
                        </div>
                        <p id="plants-help" class="mt-1.5 text-xs text-[#8a9ab0]">
                            Select one or more plants this user will be assigned to.
                        </p>
                    </section>
                </div>

                <div class="flex justify-end pt-1">
                    <button
                        type="submit"
                        :disabled="form.processing"
                        class="inline-flex items-center gap-2 rounded-md bg-slate-900 px-7 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-slate-800 focus:outline-none focus:ring-2 focus:ring-slate-700/30 focus:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-60"
                    >
                        <Save v-if="!form.processing" :size="20" />
                        {{ form.processing ? 'Saving...' : 'Save' }}
                    </button>
                </div>
            </form>
        </section>

        <ConfirmationDialog
            v-model:open="isConfirmDialogOpen"
            title="Save user?"
            description="Are you sure you want to create this new user?"
            confirm-text="Save user"
            loading-text="Saving..."
            confirm-class="bg-primary text-primary-foreground hover:bg-primary/90"
            :loading="form.processing"
            @confirm="submit"
        />
    </main>
</template>
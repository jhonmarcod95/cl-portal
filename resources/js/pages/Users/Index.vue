<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { computed, onBeforeUnmount, ref, watch } from 'vue';
import ConfirmationDialog from '@/components/ConfirmationDialog.vue';
import { MoreHorizontal } from '@lucide/vue';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';

interface Paginate {
    data: PurchaseRequest[]
    links: PaginationLink[]
    current_page: number
    last_page: number
    from: number | null
    to: number | null
    total: number
}

interface PurchaseRequest {
    id: number
    name: string
    email: string
    created_at: string
}

interface PaginationLink {
    url: string | null
    label: string
    active: boolean
}


const props = defineProps<{
    users: Paginate
    search: string
}>()

const paginationItems = computed(() => {
    const currentPage = props.users.current_page
    const lastPage = props.users.last_page

    if (lastPage <= 6) {
        return Array.from({ length: lastPage }, (_, index) => index + 1)
    }

    if (currentPage <= 4) {
        return [1, 2, 3, 4, 5, 'ellipsis', lastPage] as const
    }

    if (currentPage >= lastPage - 3) {
        return [1, 'ellipsis', ...Array.from({ length: 5 }, (_, index) => lastPage - 4 + index)] as const
    }

    return [1, 'ellipsis', currentPage - 1, currentPage, currentPage + 1, 'ellipsis', lastPage] as const
})

const search = ref(props.search)
let searchTimeout: ReturnType<typeof setTimeout> | undefined

watch(search, (value) => {
    if (searchTimeout) clearTimeout(searchTimeout)

    searchTimeout = setTimeout(() => {
        router.get('/users', {
            search: value.trim() || undefined,
        }, {
            preserveState: true,
            preserveScroll: true,
            replace: true,
        })
    }, 2000)
})

onBeforeUnmount(() => {
    if (searchTimeout) clearTimeout(searchTimeout)
})

const changePage = (page: number) => {
    router.get('/users', {
        search: search.value.trim() || undefined,
        page,
    }, {
        preserveState: true,
        preserveScroll: true,
        replace: true,
    })
}

const isConfirmDialogOpen = ref(false)
const isConfirmLoading = ref(false)
const userToDelete = ref<number | null>(null)

const openDeleteConfirmation = (userId: number) => {
    userToDelete.value = userId
    isConfirmDialogOpen.value = true
}

const handleDeleteConfirmation = () => {
    if (userToDelete.value === null) return

    router.delete(`/users/${userToDelete.value}`, {
        preserveState: true,
        preserveScroll: true,
        replace: true,
        onStart: () => {
            isConfirmLoading.value = true
        },
        onSuccess: () => {
            isConfirmDialogOpen.value = false
            userToDelete.value = null
        },
        onFinish: () => {
            isConfirmLoading.value = false
        },
    })
}

const formatDate = (value: string, fullDate?: boolean) => {
    const date = new Date(value)

    if (Number.isNaN(date.getTime())) {
        return value
    }

    return new Intl.DateTimeFormat('en-US', {
        day: 'numeric',
        month: fullDate ? 'long' : 'short',
        year: 'numeric',
    }).format(date)
}

</script>

<template>
    <Head title="Users" />

    <main class="min-h-full bg-[#f5f7fa] px-4 py-4 text-[#1f2e4d]">
        <div class="mx-auto">
            <div class="mb-4 flex items-center justify-between gap-4">
                <h1 class="text-[1.05rem] font-semibold text-[#1f2e4d]">Users</h1>
                <div class="flex flex-row gap-2">
                    <input
                        v-model="search"
                        type="text"
                        aria-label="Search users by name or email"
                        placeholder="Search users..."
                        class="rounded-md border border-[#bfd0e3] bg-white px-4 py-2 text-sm text-[#1f2e4d] placeholder:text-[#1f2e4d]/50 focus:border-[#17345f] focus:ring-1 focus:ring-[#17345f]"
                    />
                    <Link
                        href="/users/create"
                        class="rounded-md bg-slate-700 px-4 py-2 text-sm font-semibold text-white hover:bg-[#17345f]"
                    >
                        Create user
                    </Link>
                </div>
            </div>

            <div class="overflow-hidden rounded-xl border border-[#bfd0e3] shadow-sm">
                <div class="overflow-x-auto">
                    <table aria-label="My purchase requests" class="min-w-full border-separate border-spacing-0 text-left text-[0.96rem]">
                        <thead>
                            <tr class="bg-black text-white">
                                <th class="px-3 py-3 font-semibold">ID</th>
                                <th class="px-3 py-3 font-semibold">Name</th>
                                <th class="px-3 py-3 font-semibold">Email</th>
                                <th class="px-3 py-3 font-semibold">Created at </th>
                                <th class="px-3 py-3 font-semibold">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr
                                v-for="item in users.data"
                                :key="item.id"
                                class="border-t border-slate-200 even:bg-blue-50"
                            >
                                <td class="px-3 py-3 align-top">{{ item.id }}</td>
                                <td class="px-3 py-3 align-top">{{ item.name }}</td>
                                <td class="px-3 py-3 align-top">{{ item.email }}</td>
                                <td class="px-3 py-3 align-top">{{ formatDate(item.created_at, true) }}</td>
                                <td class="px-3 py-3 align-top">
                                    <DropdownMenu>
                                        <DropdownMenuTrigger as-child>
                                            <button
                                                type="button"
                                                :aria-label="`Actions for ${item.name}`"
                                                class="inline-flex items-center gap-2 rounded-md bg-slate-900 px-7 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-slate-800 disabled:cursor-not-allowed disabled:opacity-60"
                                            >
                                                Action
                                            </button>
                                        </DropdownMenuTrigger>
                                        <DropdownMenuContent align="start">
                                            <DropdownMenuItem as-child>
                                                <Link :href="`/users/${item.id}/edit`">Edit</Link>
                                            </DropdownMenuItem>
                                            <DropdownMenuItem
                                                variant="destructive"
                                                @select="openDeleteConfirmation(item.id)"
                                            >
                                                Delete
                                            </DropdownMenuItem>
                                        </DropdownMenuContent>
                                    </DropdownMenu>
                                </td>
                            </tr>
                            <tr v-if="users.data.length === 0">
                                <td colspan="5" class="px-3 py-10 text-center text-sm text-slate-600">
                                    No users found.
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                <div class="flex flex-col justify-between gap-3 border-t border-slate-200 bg-white px-3 py-3 text-sm text-slate-600 sm:flex-row sm:items-center">
                    <span>
                        Showing {{ users.from ?? 0 }}–{{ users.to ?? 0 }}
                        of {{ users.total }} users
                    </span>
                    <nav v-if="users.last_page > 1" aria-label="Pagination" class="flex items-center gap-1">
                        <button
                            type="button"
                            :disabled="users.current_page === 1"
                            class="rounded-md border border-slate-300 px-3 py-1.5 hover:bg-slate-50 disabled:cursor-not-allowed disabled:opacity-50"
                            @click="changePage(users.current_page - 1)"
                        >
                            Previous
                        </button>
                        <template v-for="(item, index) in paginationItems" :key="`${item}-${index}`">
                            <span v-if="item === 'ellipsis'" class="px-1 text-slate-500" aria-hidden="true">...</span>
                            <button
                                v-else
                                type="button"
                                :aria-current="item === users.current_page ? 'page' : undefined"
                                class="min-w-9 rounded-md border px-3 py-1.5"
                                :class="item === users.current_page
                                    ? 'border-slate-900 bg-slate-900 text-white'
                                    : 'border-slate-300 hover:bg-slate-50'"
                                @click="changePage(item)"
                            >
                                {{ item }}
                            </button>
                        </template>
                        <button
                            type="button"
                            :disabled="users.current_page === users.last_page"
                            class="rounded-md border border-slate-300 px-3 py-1.5 hover:bg-slate-50 disabled:cursor-not-allowed disabled:opacity-50"
                            @click="changePage(users.current_page + 1)"
                        >
                            Next
                        </button>
                    </nav>
                </div>
            </div>
        </div>

        <ConfirmationDialog
            v-model:open="isConfirmDialogOpen"
            title="Delete user?"
            description="Are you sure you want to delete this user? This action cannot be undone."
            confirm-text="Delete user"
            loading-text="Deleting..."
            confirm-class="bg-red-600 hover:bg-red-700"
            :loading="isConfirmLoading"
            @confirm="handleDeleteConfirmation"
        />
    </main>
</template>
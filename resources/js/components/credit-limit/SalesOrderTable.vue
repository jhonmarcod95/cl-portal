
<script setup lang="ts">
import { Check, FileText, MessageSquare, Pencil, Search, X } from 'lucide-vue-next';
import { computed, ref } from 'vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import {
    formatDateTime,
    massGroups,
    type MassGroupKey,
    type RemarkWarning,
    type SalesOrder,
} from '@/types/credit-limit';

const props = withDefaults(
    defineProps<{
        title: string;
        rows: SalesOrder[];
        /** pending = approve/reject/remarks, rejected = remarks only, approved = view only */
        mode?: 'pending' | 'approved' | 'rejected';
        isAdmin?: boolean;
        /** Which rows get a checkbox (admin only). Omit for no checkboxes. */
        selectable?: (so: SalesOrder) => boolean;
        /** Which mass-action buttons to show for the checked rows. */
        groups?: MassGroupKey[];
        reviewCount: (customerCode: string) => number;
        warnings: (soNumber: string) => RemarkWarning[];
    }>(),
    { mode: 'approved', isAdmin: false, groups: () => [] },
);

const emit = defineEmits<{
    approve: [so: SalesOrder];
    reject: [so: SalesOrder];
    remarks: [so: SalesOrder];
    viewRemarks: [so: SalesOrder];
    pdf: [so: SalesOrder];
    mass: [action: 'approve' | 'reject', orders: SalesOrder[]];
}>();

const checked = defineModel<SalesOrder[]>('checked', { default: () => [] });

// Search
const search = ref('');

const filteredRows = computed(() => {
    const query = search.value.trim().toLowerCase();

    if (!query) return props.rows;

    return props.rows.filter((so) =>
        [
            so.so_number,
            so.customer_name,
            so.customer_code,
            so.company,
            so.sap_user,
            so.type,
            so.payment_terms,
        ].some((value) =>
            String(value ?? '').toLowerCase().includes(query),
        ),
    );
});

const showCheckbox = (so: SalesOrder) =>
    props.isAdmin && props.selectable?.(so);

const hasCheckboxColumn = computed(
    () => props.isAdmin && !!props.selectable,
);

const massButtons = computed(() =>
    props.groups
        .map((key) => ({
            key,
            label: massGroups[key].label,
            orders: checked.value.filter(massGroups[key].match),
        }))
        .filter((g) => g.orders.length),
);

function toggle(so: SalesOrder, value: boolean | 'indeterminate') {
    checked.value = value
        ? [...checked.value, so]
        : checked.value.filter((item) => item !== so);
}

function clearSearch() {
    search.value = '';
}
</script>

<template>
    <Card>
        <CardHeader
            class="flex flex-row flex-wrap items-center justify-between gap-3"
        >
            <CardTitle>{{ title }}</CardTitle>

            <div class="flex w-full flex-wrap items-center justify-between gap-3">
                <!-- Search -->
                <div class="relative w-full sm:w-80">
                    <Search
                        class="absolute left-3 top-1/2 size-4 -translate-y-1/2 text-muted-foreground"
                    />

                    <input
                        v-model="search"
                        type="search"
                        placeholder="Search SO number, customer, company..."
                        class="h-9 w-full rounded-md border border-input bg-background pl-9 pr-9 text-sm shadow-sm outline-none placeholder:text-muted-foreground focus-visible:ring-2 focus-visible:ring-ring"
                    />

                    <button
                        v-if="search"
                        type="button"
                        aria-label="Clear search"
                        class="absolute right-3 top-1/2 -translate-y-1/2 text-muted-foreground hover:text-foreground"
                        @click="clearSearch"
                    >
                        <X class="size-4" />
                    </button>
                </div>

                <!-- Mass Actions -->
                <div
                    v-if="isAdmin && massButtons.length"
                    class="flex flex-wrap gap-2"
                >
                    <template v-for="g in massButtons" :key="g.key">
                        <Button
                            size="sm"
                            class="bg-emerald-600 text-white hover:bg-emerald-700"
                            @click="emit('mass', 'approve', g.orders)"
                        >
                            Mass approve ({{ g.label }})
                        </Button>

                        <Button
                            size="sm"
                            variant="destructive"
                            @click="emit('mass', 'reject', g.orders)"
                        >
                            Mass reject ({{ g.label }})
                        </Button>
                    </template>
                </div>
            </div>

            <!-- Search Results Count -->
            <div
                v-if="search.trim()"
                class="w-full text-xs text-muted-foreground"
            >
                Showing {{ filteredRows.length }} of {{ rows.length }} sales
                orders
            </div>
        </CardHeader>

        <CardContent class="overflow-x-auto p-0">
            <table class="w-full text-sm">
                <thead class="bg-muted/50 text-left text-muted-foreground">
                    <tr>
                        <th
                            v-if="hasCheckboxColumn"
                            class="w-10 px-4 py-2"
                        />

                        <th class="px-4 py-2 font-medium">
                            SO Number
                        </th>

                        <th class="px-4 py-2 font-medium">
                            Customer
                        </th>

                        <th class="px-4 py-2 font-medium">
                            Details
                        </th>

                        <th
                            v-if="mode !== 'approved'"
                            class="px-4 py-2 font-medium"
                        >
                            Action
                        </th>

                        <th class="px-4 py-2 font-medium">
                            View
                        </th>
                    </tr>
                </thead>

                <tbody class="divide-y">
                    <!-- Empty State -->
                    <tr v-if="!filteredRows.length">
                        <td
                            :colspan="hasCheckboxColumn
                                ? (mode !== 'approved' ? 6 : 5)
                                : (mode !== 'approved' ? 5 : 4)"
                            class="px-4 py-10 text-center text-muted-foreground"
                        >
                            <div class="flex flex-col items-center gap-2">
                                <Search class="size-8 opacity-40" />

                                <span class="font-medium">
                                    No sales orders found.
                                </span>

                                <span class="text-xs">
                                    {{
                                        search
                                            ? 'Try a different search keyword.'
                                            : 'There are no sales orders available.'
                                    }}
                                </span>

                                <Button
                                    v-if="search"
                                    size="sm"
                                    variant="outline"
                                    @click="clearSearch"
                                >
                                    Clear search
                                </Button>
                            </div>
                        </td>
                    </tr>

                    <!-- Sales Order Rows -->
                    <tr
                        v-for="so in filteredRows"
                        :key="so.id"
                        class="align-top"
                    >
                        <!-- Checkbox -->
                        <td
                            v-if="hasCheckboxColumn"
                            class="px-4 py-3"
                        >
                            <Checkbox
                                v-if="showCheckbox(so)"
                                :model-value="checked.includes(so)"
                                @update:model-value="toggle(so, $event)"
                            />
                        </td>

                        <!-- SO Number -->
                        <td class="px-4 py-3">
                            <div class="font-medium">
                                {{ so.so_number }}
                            </div>

                            <div class="text-xs text-muted-foreground">
                                <div v-if="so.type === 'ECOM'">
                                    E-commerce
                                </div>

                                <div v-if="so.payment_terms === 'ZCBD'">
                                    ZCBD
                                </div>

                                <div v-if="so.is_samples">
                                    Samples
                                </div>
                            </div>
                        </td>

                        <!-- Customer -->
                        <td class="px-4 py-3">
                            <div>{{ so.customer_name }}</div>

                            <div class="text-xs text-muted-foreground">
                                Company: {{ so.company }}
                            </div>
                        </td>

                        <!-- Details -->
                        <td class="px-4 py-3 text-xs">
                            <div>
                                <span class="text-muted-foreground">
                                    Date issue:
                                </span>
                                {{ so.date_issue }}
                            </div>

                            <div>
                                <span class="text-muted-foreground">
                                    Request date:
                                </span>
                                {{ so.request_date }}
                            </div>

                            <div>
                                <span class="text-muted-foreground">
                                    Pushed at:
                                </span>
                                {{ formatDateTime(so.pushed_at) }}
                            </div>

                            <div>
                                <span class="text-muted-foreground">
                                    Pushed by:
                                </span>
                                {{ so.sap_user }}
                            </div>

                            <div v-if="reviewCount(so.customer_code) > 0">
                                <span class="text-muted-foreground">
                                    Review CAF:
                                </span>
                                {{ reviewCount(so.customer_code) }}
                            </div>

                            <div
                                v-for="(w, i) in warnings(so.so_number)"
                                :key="i"
                                class="text-red-600 dark:text-red-400"
                            >
                                {{ w.description }}: {{ w.remarks_count }}
                            </div>
                        </td>

                        <!-- Actions -->
                        <td
                            v-if="mode !== 'approved'"
                            class="px-4 py-3"
                        >
                            <div class="flex gap-1">
                                <template
                                    v-if="mode === 'pending' && isAdmin"
                                >
                                    <Button
                                        size="icon"
                                        class="size-8 bg-emerald-600 text-white hover:bg-emerald-700"
                                        aria-label="Approve"
                                        @click="emit('approve', so)"
                                    >
                                        <Check class="size-4" />
                                    </Button>

                                    <Button
                                        size="icon"
                                        variant="destructive"
                                        class="size-8"
                                        aria-label="Reject"
                                        @click="emit('reject', so)"
                                    >
                                        <X class="size-4" />
                                    </Button>
                                </template>

                                <Button
                                    size="icon"
                                    variant="secondary"
                                    class="size-8"
                                    aria-label="Add remarks"
                                    @click="emit('remarks', so)"
                                >
                                    <Pencil class="size-4" />
                                </Button>
                            </div>
                        </td>

                        <!-- View -->
                        <td class="px-4 py-3">
                            <div class="flex gap-1">
                                <Button
                                    size="icon"
                                    variant="outline"
                                    class="size-8"
                                    aria-label="View PDF"
                                    @click="emit('pdf', so)"
                                >
                                    <FileText class="size-4" />
                                </Button>

                                <Button
                                    size="icon"
                                    variant="outline"
                                    class="size-8"
                                    aria-label="View remarks"
                                    @click="emit('viewRemarks', so)"
                                >
                                    <MessageSquare class="size-4" />
                                </Button>
                            </div>
                        </td>
                    </tr>
                </tbody>
            </table>
        </CardContent>
    </Card>
</template>

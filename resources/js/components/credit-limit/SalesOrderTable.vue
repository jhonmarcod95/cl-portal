<script setup lang="ts">
import { Check, FileText, MessageSquare, Pencil, X } from 'lucide-vue-next';
import { computed } from 'vue';
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
</script>

<template>
    <Card>
        <CardHeader
            class="flex flex-row flex-wrap items-center justify-between gap-2"
        >
            <CardTitle>{{ title }}</CardTitle>

            <div v-if="isAdmin && massButtons.length" class="flex flex-wrap gap-2">
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
        </CardHeader>

        <CardContent class="overflow-x-auto p-0">
            <table class="w-full text-sm">
                <thead class="bg-muted/50 text-left text-muted-foreground">
                    <tr>
                        <th v-if="hasCheckboxColumn" class="w-10 px-4 py-2" />
                        <th class="px-4 py-2 font-medium">SO Number</th>
                        <th class="px-4 py-2 font-medium">Customer</th>
                        <th class="px-4 py-2 font-medium">Details</th>
                        <th
                            v-if="mode !== 'approved'"
                            class="px-4 py-2 font-medium"
                        >
                            Action
                        </th>
                        <th class="px-4 py-2 font-medium">View</th>
                    </tr>
                </thead>
                <tbody class="divide-y">
                    <tr v-for="so in rows" :key="so.id" class="align-top">
                        <td v-if="hasCheckboxColumn" class="px-4 py-3">
                            <Checkbox
                                v-if="showCheckbox(so)"
                                :model-value="checked.includes(so)"
                                @update:model-value="toggle(so, $event)"
                            />
                        </td>

                        <td class="px-4 py-3">
                            <div class="font-medium">{{ so.so_number }}</div>
                            <div class="text-xs text-muted-foreground">
                                <div v-if="so.type === 'ECOM'">E-commerce</div>
                                <div v-if="so.payment_terms === 'ZCBD'">
                                    ZCBD
                                </div>
                                <div v-if="so.is_samples">Samples</div>
                            </div>
                        </td>

                        <td class="px-4 py-3">
                            <div>{{ so.customer_name }}</div>
                            <div class="text-xs text-muted-foreground">
                                Company: {{ so.company }}
                            </div>
                        </td>

                        <td class="px-4 py-3 text-xs">
                            <div>
                                <span class="text-muted-foreground">Date issue:</span>
                                {{ so.date_issue }}
                            </div>
                            <div>
                                <span class="text-muted-foreground">Request date:</span>
                                {{ so.request_date }}
                            </div>
                            <div>
                                <span class="text-muted-foreground">Pushed at:</span>
                                {{ formatDateTime(so.pushed_at) }}
                            </div>
                            <div>
                                <span class="text-muted-foreground">Pushed by:</span>
                                {{ so.sap_user }}
                            </div>
                            <div v-if="reviewCount(so.customer_code) > 0">
                                <span class="text-muted-foreground">Review CAF:</span>
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

                        <td v-if="mode !== 'approved'" class="px-4 py-3">
                            <div class="flex gap-1">
                                <template v-if="mode === 'pending' && isAdmin">
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
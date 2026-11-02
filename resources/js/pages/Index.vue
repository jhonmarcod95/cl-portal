<script setup lang="ts">
import { Head, usePage } from '@inertiajs/vue3';
import axios from 'axios';
import {
    Clipboard,
    ClipboardCheck,
    ClipboardList,
    Eraser,
    Loader2,
} from 'lucide-vue-next';
import { computed, onMounted, reactive, ref } from 'vue';
import { toast } from 'vue-sonner';
import SalesOrderTable from '@/components/credit-limit/SalesOrderTable.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import {
    Dialog,
    DialogContent,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { dashboard } from '@/routes';
import {
    formatDateTime,
    isBulkEligible,
    soPdfUrl,
    today,
    type CreditLimitData,
    type RemarkHistory,
    type RemarkOption,
    type SalesOrder,
} from '@/types/credit-limit';

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Dashboard', href: dashboard() },
            { title: 'Credit Limit', href: '/credit-limit' },
        ],
    },
});

/* ---------------------------------------------------------------- state */

const page = usePage();
// Adjust to however you share roles (e.g. HandleInertiaRequests::share)
const isAdmin = computed(() =>
    ((page.props.auth as any)?.user?.roles ?? []).some(
        (role: any) => role.slug == 'admin'
    ),
);

const dateFrom = ref(today());
const dateTo = ref(today());
const loading = ref(false);
const processing = ref(false);

const creditLimit = reactive<CreditLimitData>({
    approves: [],
    forApprovals: [],
    forApprovalSamples: [],
    pastForApprovals: [],
    rejects: [],
    remarksCount: [],
    remarksFilters: { headers: [], items: [] },
    reviewCafs: [],
});

const checkedCurrent = ref<SalesOrder[]>([]); // main + samples tables
const checkedPast = ref<SalesOrder[]>([]);

const approveRemarks = ref<RemarkOption[]>([]);
const rejectRemarks = ref<RemarkOption[]>([]);

const pendingCount = computed(
    () => creditLimit.forApprovals.length + creditLimit.forApprovalSamples.length,
);
const totalCount = computed(
    () => pendingCount.value + creditLimit.approves.length + creditLimit.rejects.length,
);

const statCards = computed(() => [
    {
        title: 'For approval',
        value: pendingCount.value,
        icon: ClipboardList,
        color: 'bg-sky-500',
        href: '#a-past-for-approval',
        link: `${creditLimit.pastForApprovals.length} for past seven days`,
    },
    {
        title: 'Approved SO',
        value: creditLimit.approves.length,
        icon: ClipboardCheck,
        color: 'bg-emerald-500',
        href: '#a-approved',
        link: 'Click here to show',
    },
    {
        title: 'Rejected SO',
        value: creditLimit.rejects.length,
        icon: Eraser,
        color: 'bg-red-500',
        href: '#a-rejected',
        link: 'Click here to show',
    },
    {
        title: 'Credit limit count',
        value: totalCount.value,
        icon: Clipboard,
        color: 'bg-amber-500',
        href: '#a-date-filter',
        link: 'Go to date filter',
    },
]);

/* ----------------------------------------------------------------- data */

async function fetchCreditLimit() {
    loading.value = true;
    try {
        const { data } = await axios.get(
            `/cl_data/${dateFrom.value}/${dateTo.value}`,
        );

        const forApproval: SalesOrder[] = data.for_approval;
        creditLimit.approves = data.approved;
        creditLimit.forApprovals = forApproval.filter((i) => !i.is_samples);
        creditLimit.forApprovalSamples = forApproval.filter((i) => i.is_samples);
        creditLimit.pastForApprovals = data.past_for_approval;
        creditLimit.rejects = data.rejected;
        creditLimit.remarksCount = data.remarks_count;
        creditLimit.remarksFilters = data.remarks_filter;
        creditLimit.reviewCafs = data.review_cafs;

        // default checked
        checkedCurrent.value = [
            ...creditLimit.forApprovals.filter(isBulkEligible),
            ...creditLimit.forApprovalSamples,
        ];
        checkedPast.value = creditLimit.pastForApprovals.filter(isBulkEligible);
    } finally {
        loading.value = false;
    }
}

async function fetchRemarkOptions() {
    const [approve, reject] = await Promise.all([
        axios.get('/fetch-approve-remarks'),
        axios.get('/fetch-reject-remarks'),
    ]);
    approveRemarks.value = approve.data;
    rejectRemarks.value = reject.data;
}

const countReviewCaf = (code: string) =>
    creditLimit.reviewCafs.filter((i) => i.customer_code === code).length;

const remarksWarnings = (soNumber: string) =>
    creditLimit.remarksCount.filter((i) => i.so_number === soNumber);

const remarksFilterItems = (header: string) =>
    creditLimit.remarksFilters.items.filter(
        (i) => i.remarks_description === header,
    );

function handleError(error: unknown) {
    if (!axios.isAxiosError(error) || !error.response) {
        toast.error('Something went wrong');
        return;
    }
    const { status, data } = error.response;
    if (status === 500) {
        axios.post('/log-api-error', {
            filename: 'errors.log',
            message: data.message,
        });
        toast.error(data.message);
    } else if (status === 422) {
        const first = Object.values<string[]>(data.errors ?? {})[0]?.[0];
        toast.error(first ?? 'Validation error');
    }
}

/* ------------------------------------------------- approve / reject flow */

const decision = reactive({
    open: false,
    action: 'approve' as 'approve' | 'reject',
    targets: [] as SalesOrder[],
    checkedRemarks: [] as number[],
    text: '',
});

const isMass = computed(() => decision.targets.length > 1 || massMode.value);
const massMode = ref(false);

// Remark id 2 ("review CAF") is hidden when any target customer has CAFs to review
const decisionOptions = computed(() => {
    const options =
        decision.action === 'approve' ? approveRemarks.value : rejectRemarks.value;
    const hasReviewCaf = decision.targets.some(
        (so) => countReviewCaf(so.customer_code) > 0,
    );
    return hasReviewCaf ? options.filter((o) => o.id !== 2) : options;
});

function openDecision(
    action: 'approve' | 'reject',
    targets: SalesOrder[],
    mass = false,
) {
    decision.action = action;
    decision.targets = targets;
    decision.checkedRemarks = [];
    decision.text = '';
    massMode.value = mass;
    decision.open = true;
}

function toggleDecisionRemark(id: number) {
    decision.checkedRemarks = decision.checkedRemarks.includes(id)
        ? decision.checkedRemarks.filter((i) => i !== id)
        : [...decision.checkedRemarks, id];
}

async function submitDecision() {
    processing.value = true;
    const isApprove = decision.action === 'approve';

    try {
        if (!massMode.value) {
            const so = decision.targets[0];
            const { data } = await axios.post(
                isApprove ? '/cl_approve' : '/cl_reject',
                {
                    so_id: so.id,
                    so_number: so.so_number,
                    company: so.company,
                    customer_code: so.customer_code,
                    checkedRemarks: decision.checkedRemarks,
                    remarks: decision.text,
                    sap_server: so.sap_server,
                },
            );

            if (isApprove && data.return !== 'S') {
                toast.error(`Unable to approve: ${data.description}`);
                return;
            }
            toast.success(isApprove ? 'Successfully approved' : 'Successfully rejected', {
                description: data.description,
            });
        } else {
            const { data } = await axios.post(
                isApprove ? '/cl_mass_approve' : '/cl_mass_reject',
                {
                    ecoms: decision.targets,
                    checkedRemarks: decision.checkedRemarks,
                    remarks: decision.text,
                },
            );

            if (isApprove) {
                for (const result of data as { return: string; description: string }[]) {
                    result.return === 'S'
                        ? toast.success('Successfully approved', { description: result.description })
                        : toast.error(result.description);
                }
            } else {
                toast.success('Successfully rejected', { description: data.description });
            }
        }

        decision.open = false;
        await fetchCreditLimit();
    } catch (error) {
        handleError(error);
    } finally {
        processing.value = false;
    }
}

/* -------------------------------------------------------------- remarks */

const addRemarks = reactive({
    open: false,
    so: null as SalesOrder | null,
    text: '',
    file: null as File | null,
});

function openAddRemarks(so: SalesOrder) {
    addRemarks.so = so;
    addRemarks.text = '';
    addRemarks.file = null;
    addRemarks.open = true;
}

async function submitRemarks() {
    if (!addRemarks.so) return;
    processing.value = true;

    const form = new FormData();
    form.append('so_number', addRemarks.so.so_number);
    form.append('status', addRemarks.so.status ?? '');
    form.append('remarks', addRemarks.text);
    if (addRemarks.file) form.append('file_upload', addRemarks.file);

    try {
        await axios.post('/cl_remarks', form);
        addRemarks.open = false;
        toast.success('Remarks added');
    } catch (error) {
        handleError(error);
    } finally {
        processing.value = false;
    }
}

const viewRemarks = reactive({
    open: false,
    so: null as SalesOrder | null,
    list: [] as RemarkHistory[],
});

async function openViewRemarks(so: SalesOrder) {
    viewRemarks.so = so;
    viewRemarks.list = [];
    viewRemarks.open = true;
    const { data } = await axios.get(`/cl_remarks_data/${so.so_number}`);
    viewRemarks.list = data;
}

async function openSoVersion(remark: RemarkHistory) {
    if (!viewRemarks.so) return;
    const { data } = await axios.get(
        `/get-so-version/${remark.so_number}/${remark.created_at}`,
    );
    window.open(
        soPdfUrl(
            { sap_server: viewRemarks.so.sap_server, so_number: remark.so_number },
            data[0].version,
        ),
        '_blank',
    );
}

const openPdf = (so: SalesOrder) =>
    window.open(soPdfUrl(so, so.version), '_blank');

/* --------------------------------------------------------------- shared */

// Props every table needs, spread with v-bind
const tableShared = computed(() => ({
    isAdmin: isAdmin.value,
    reviewCount: countReviewCaf,
    warnings: remarksWarnings,
}));

onMounted(() => {
    fetchCreditLimit();
    fetchRemarkOptions();
});
</script>

<template>
    <Head title="Credit Limit" />

    <div class="flex h-full flex-1 flex-col gap-6 overflow-x-auto rounded-xl p-4">
        <!-- Stats -->
        <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
            <Card v-for="stat in statCards" :key="stat.title" class="gap-3 py-5">
                <CardContent class="flex flex-col gap-3">
                    <div class="flex items-start justify-between">
                        <div>
                            <p class="text-sm text-muted-foreground">
                                {{ stat.title }}
                            </p>
                            <p class="text-2xl font-semibold">{{ stat.value }}</p>
                        </div>
                        <div
                            :class="[stat.color, 'flex size-10 items-center justify-center rounded-full text-white']"
                        >
                            <component :is="stat.icon" class="size-5" />
                        </div>
                    </div>
                    <a
                        :href="stat.href"
                        class="text-sm text-muted-foreground hover:text-foreground"
                    >
                        {{ stat.link }}
                    </a>
                </CardContent>
            </Card>
        </div>

        <!-- For approval -->
        <SalesOrderTable
            v-if="creditLimit.forApprovals.length"
            v-model:checked="checkedCurrent"
            v-bind="tableShared"
            title="Credit limit for approval"
            mode="pending"
            :rows="creditLimit.forApprovals"
            :selectable="isBulkEligible"
            :groups="['ecom', 'zcbd', 'alc']"
            @approve="openDecision('approve', [$event])"
            @reject="openDecision('reject', [$event])"
            @mass="(action, orders) => openDecision(action, orders, true)"
            @remarks="openAddRemarks"
            @view-remarks="openViewRemarks"
            @pdf="openPdf"
        />

        <!-- Samples -->
        <SalesOrderTable
            v-if="creditLimit.forApprovalSamples.length"
            v-model:checked="checkedCurrent"
            v-bind="tableShared"
            title="Credit limit for approval – Samples"
            mode="pending"
            :rows="creditLimit.forApprovalSamples"
            :selectable="(so) => !!so.is_samples"
            :groups="['zsam']"
            @approve="openDecision('approve', [$event])"
            @reject="openDecision('reject', [$event])"
            @mass="(action, orders) => openDecision(action, orders, true)"
            @remarks="openAddRemarks"
            @view-remarks="openViewRemarks"
            @pdf="openPdf"
        />

        <!-- Approved + date filter -->
        <div class="grid gap-6 xl:grid-cols-4">
            <section
                id="a-approved"
                class="scroll-mt-4 xl:col-span-3"
                v-if="creditLimit.approves.length"
            >
                <SalesOrderTable
                    v-bind="tableShared"
                    title="Approved SO"
                    mode="approved"
                    :rows="creditLimit.approves"
                    @view-remarks="openViewRemarks"
                    @pdf="openPdf"
                />
            </section>

            <section
                id="a-date-filter"
                class="scroll-mt-4 xl:col-start-4"
            >
                <Card>
                    <CardHeader>
                        <CardTitle>Date filter</CardTitle>
                    </CardHeader>
                    <CardContent class="flex flex-col gap-4">
                        <div class="grid gap-2">
                            <Label for="date-from">Date from</Label>
                            <Input id="date-from" v-model="dateFrom" type="date" />
                        </div>
                        <div class="grid gap-2">
                            <Label for="date-to">Date to</Label>
                            <Input id="date-to" v-model="dateTo" type="date" />
                        </div>
                        <Button :disabled="loading" @click="fetchCreditLimit">
                            Apply
                        </Button>
                    </CardContent>
                </Card>
            </section>
        </div>

        <!-- Rejected -->
        <section
            id="a-rejected"
            class="scroll-mt-4"
            v-if="creditLimit.rejects.length"
        >
            <SalesOrderTable
                v-bind="tableShared"
                title="Rejected SO"
                mode="rejected"
                :rows="creditLimit.rejects"
                @remarks="openAddRemarks"
                @view-remarks="openViewRemarks"
                @pdf="openPdf"
            />
        </section>

        <!-- Approved, grouped by remarks -->
        <div
            v-if="creditLimit.remarksFilters.headers?.length"
            class="grid gap-6 xl:grid-cols-2"
        >
            <SalesOrderTable
                v-for="header in creditLimit.remarksFilters.headers"
                :key="header"
                v-bind="tableShared"
                :title="`Approved / ${header}`"
                mode="approved"
                :rows="remarksFilterItems(header)"
                @view-remarks="openViewRemarks"
                @pdf="openPdf"
            />
        </div>

        <!-- Past 7 days -->
        <section
            id="a-past-for-approval"
            class="scroll-mt-4"
            v-if="creditLimit.pastForApprovals.length"
        >
            <SalesOrderTable
                v-model:checked="checkedPast"
                v-bind="tableShared"
                title="For approval past 7 days"
                mode="pending"
                :rows="creditLimit.pastForApprovals"
                :selectable="isBulkEligible"
                :groups="['ecom', 'zcbd', 'alc']"
                @approve="openDecision('approve', [$event])"
                @reject="openDecision('reject', [$event])"
                @mass="(action, orders) => openDecision(action, orders, true)"
                @remarks="openAddRemarks"
                @view-remarks="openViewRemarks"
                @pdf="openPdf"
            />
        </section>
    </div>

    <!-- Loading overlay -->
    <div
        v-if="loading"
        class="fixed inset-0 z-50 flex items-center justify-center bg-background/60"
    >
        <Loader2 class="size-8 animate-spin" />
    </div>

    <!-- Approve / reject (single + mass) -->
    <Dialog v-model:open="decision.open">
        <DialogContent class="max-h-[90vh] overflow-y-auto">
            <DialogHeader>
                <DialogTitle>
                    <template v-if="massMode">
                        Mass {{ decision.action }} ({{ decision.targets.length }} sales orders)
                    </template>
                    <template v-else>
                        {{ decision.action === 'approve' ? 'Approve' : 'Reject' }}
                        SO number: {{ decision.targets[0]?.so_number }}
                    </template>
                </DialogTitle>
            </DialogHeader>

            <details v-if="massMode" class="rounded-md border text-sm">
                <summary class="cursor-pointer px-3 py-2">
                    View all sales orders ({{ decision.targets.length }})
                </summary>
                <table class="w-full">
                    <thead class="text-left text-muted-foreground">
                        <tr>
                            <th class="px-3 py-1">SO Number</th>
                            <th class="px-3 py-1">Customer</th>
                            <th class="px-3 py-1">Company</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="so in decision.targets" :key="so.id" class="border-t">
                            <td class="px-3 py-1">{{ so.so_number }}</td>
                            <td class="px-3 py-1">{{ so.customer_name }}</td>
                            <td class="px-3 py-1">{{ so.company }}</td>
                        </tr>
                    </tbody>
                </table>
            </details>

            <div class="grid gap-3">
                <Label>
                    {{ decision.action === 'approve' ? 'Approved remarks' : 'Reason for rejection' }}
                </Label>
                <div
                    v-for="option in decisionOptions"
                    :key="option.id"
                    class="flex items-center gap-2"
                >
                    <Checkbox
                        :id="`remark-${option.id}`"
                        :model-value="decision.checkedRemarks.includes(option.id)"
                        @update:model-value="toggleDecisionRemark(option.id)"
                    />
                    <Label :for="`remark-${option.id}`" class="font-normal">
                        {{ option.description }}
                    </Label>
                </div>
            </div>

            <div class="grid gap-2">
                <Label for="decision-others">Others</Label>
                <textarea
                    id="decision-others"
                    v-model="decision.text"
                    rows="2"
                    maxlength="200"
                    placeholder="My remarks"
                    class="rounded-md border bg-transparent px-3 py-2 text-sm"
                />
            </div>

            <DialogFooter>
                <Button variant="ghost" @click="decision.open = false">Close</Button>
                <Button
                    :disabled="processing"
                    :variant="decision.action === 'approve' ? 'default' : 'destructive'"
                    @click="submitDecision"
                >
                    <Loader2 v-if="processing" class="size-4 animate-spin" />
                    {{ decision.action === 'approve' ? 'Confirm approval' : 'Confirm reject' }}
                </Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>

    <!-- Add remarks -->
    <Dialog v-model:open="addRemarks.open">
        <DialogContent>
            <DialogHeader>
                <DialogTitle>Add remarks: {{ addRemarks.so?.so_number }}</DialogTitle>
            </DialogHeader>

            <div class="grid gap-2">
                <Label for="add-remarks-text">Remarks</Label>
                <textarea
                    id="add-remarks-text"
                    v-model="addRemarks.text"
                    rows="2"
                    maxlength="200"
                    placeholder="My remarks"
                    class="rounded-md border bg-transparent px-3 py-2 text-sm"
                />
            </div>
            <div class="grid gap-2">
                <Label for="add-remarks-file">Upload file</Label>
                <Input
                    id="add-remarks-file"
                    type="file"
                    @change="(e: Event) => (addRemarks.file = (e.target as HTMLInputElement).files?.[0] ?? null)"
                />
            </div>

            <DialogFooter>
                <Button variant="ghost" @click="addRemarks.open = false">Close</Button>
                <Button :disabled="processing" @click="submitRemarks">
                    <Loader2 v-if="processing" class="size-4 animate-spin" />
                    Confirm
                </Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>

    <!-- Remarks history -->
    <Dialog v-model:open="viewRemarks.open">
        <DialogContent class="max-h-[90vh] overflow-y-auto sm:max-w-2xl">
            <DialogHeader>
                <DialogTitle>Remarks history: {{ viewRemarks.so?.so_number }}</DialogTitle>
            </DialogHeader>

            <p v-if="!viewRemarks.list.length" class="text-sm text-muted-foreground">
                No remarks yet.
            </p>

            <div
                v-for="(remark, i) in viewRemarks.list"
                :key="i"
                class="rounded-md border bg-muted/40 p-3 text-sm whitespace-pre-line"
            >
                <p>{{ remark.description }}</p>
                <div
                    class="mt-2 flex flex-wrap items-center justify-between gap-2 text-xs text-muted-foreground"
                >
                    <button
                        type="button"
                        class="hover:text-foreground"
                        @click="openSoVersion(remark)"
                    >
                        {{ remark.updated_by }} | {{ formatDateTime(remark.created_at) }} | View PDF
                    </button>
                    <span class="font-semibold">
                        {{ remark.status_description }}
                        <a
                            v-if="remark.attachment"
                            :href="`/storage/${remark.attachment}`"
                            target="_blank"
                            class="hover:text-foreground"
                        >
                            | View attachment
                        </a>
                    </span>
                </div>
            </div>

            <DialogFooter>
                <Button variant="ghost" @click="viewRemarks.open = false">Close</Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
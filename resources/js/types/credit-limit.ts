export interface SalesOrder {
    id: number;
    so_number: string;
    customer_name: string;
    customer_code: string;
    company: string;
    type?: string;
    payment_terms?: string;
    is_samples?: boolean;
    date_issue: string;
    request_date: string;
    pushed_at: string;
    sap_user: string;
    sap_server: string;
    version: number;
    status?: string;
    remarks_description?: string;
}

export interface RemarkOption {
    id: number;
    description: string;
}

export interface RemarkWarning {
    so_number: string;
    description: string;
    remarks_count: number;
}

export interface RemarkHistory {
    so_number: string;
    description: string;
    updated_by: string;
    created_at: string;
    status_description: string;
    attachment?: string | null;
}

export interface CreditLimitData {
    approves: SalesOrder[];
    forApprovals: SalesOrder[];
    forApprovalSamples: SalesOrder[];
    pastForApprovals: SalesOrder[];
    rejects: SalesOrder[];
    remarksCount: RemarkWarning[];
    remarksFilters: { headers: string[]; items: SalesOrder[] };
    reviewCafs: { customer_code: string }[];
}

export type MassGroupKey = 'ecom' | 'zcbd' | 'alc' | 'zsam';

/** Each mass-action group: button label + which sales orders belong to it. */
export const massGroups: Record<
    MassGroupKey,
    { label: string; match: (so: SalesOrder) => boolean }
> = {
    ecom: {
        label: 'E-Commerce',
        match: (so) => so.type === 'ECOM' && !so.is_samples,
    },
    zcbd: {
        label: 'ZCBD',
        match: (so) => so.payment_terms === 'ZCBD' && !so.is_samples,
    },
    alc: { label: 'ALC', match: (so) => so.company === 'ALC' },
    zsam: { label: 'Samples', match: (so) => !!so.is_samples },
};

/** Rows that get a checkbox (and are pre-checked) in the main / past tables. */
export const isBulkEligible = (so: SalesOrder) =>
    so.type === 'ECOM' || so.payment_terms === 'ZCBD' || so.company === 'ALC';

export const formatDateTime = (value: string) =>
    new Date(value)
        .toLocaleString('en-CA', {
            year: 'numeric',
            month: '2-digit',
            day: '2-digit',
            hour: '2-digit',
            minute: '2-digit',
            hour12: true,
        })
        .replace(',', '')
        .replace(/\s?([ap])\.m\./i, ' $1m');

export const soPdfUrl = (so: Pick<SalesOrder, 'sap_server' | 'so_number'>, version: number) =>
    `/storage/cl_approve/${so.sap_server}/${so.so_number}_${String(version).padStart(3, '0')}.pdf`;

export const today = () => new Date().toLocaleDateString('en-CA'); // YYYY-MM-DD
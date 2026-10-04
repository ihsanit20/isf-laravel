export type Option = {
    value: string;
    label: string;
};

export type EventPackage = {
    id: number;
    name: string;
    description: string | null;
    unit_type: string;
    unit_type_label: string;
    unit_size: string;
    unit_label: string;
    package_price: string;
    advance_percent: string;
    min_qty_per_order: number;
    max_qty_per_order: number | null;
    stock_qty: number | null;
    sold_qty: number;
    remaining_qty: number | null;
    sort_order: number;
    status: string;
    status_label: string;
};

export type EventPickupPoint = {
    id: number;
    name: string;
    area: string | null;
    address: string | null;
    contact_person: string | null;
    phone: string | null;
    sort_order: number;
    is_active: boolean;
};

export type EventExpense = {
    id: number;
    expense_date: string;
    category: string;
    category_label: string;
    amount: number;
    description: string | null;
    receipt_path: string | null;
    receipt_url: string | null;
    created_by_name: string | null;
    created_at: string | null;
};

export type EventExpenseSummary = {
    total_amount: number;
    entry_count: number;
};

export type EventBankWithdrawal = {
    id: number;
    withdrawal_date: string;
    amount: number;
    description: string | null;
    reference_no: string | null;
    created_by_name: string | null;
    created_at: string | null;
};

export type EventBankDeposit = {
    id: number;
    deposit_date: string;
    amount: number;
    source: 'cash' | 'bkash';
    description: string | null;
    reference_no: string | null;
    created_by_name: string | null;
    created_at: string | null;
};

export type BankDepositSummary = {
    total_amount: number;
    entry_count: number;
};

export type BankDepositReconciliation = {
    verified_customer_payments: number;
    deposited_to_bank: number;
    not_yet_deposited: number;
    cash_in_hand: number;
    bkash_wallet: number;
};

export type EventIncome = {
    id: number;
    income_date: string;
    category: string;
    category_label: string;
    received_via: 'cash' | 'bkash' | 'bank';
    amount: number;
    description: string | null;
    created_by_name: string | null;
};

export type WithdrawalSummary = {
    total_amount: number;
    entry_count: number;
};

export type FloatSummary = {
    withdrawn_from_bank: number;
    cash_received: number;
    logged_expenses: number;
    cash_refunded: number;
    deposited_to_bank: number;
    other_movements: number;
    remaining_float: number;
    is_over_logged: boolean;
};

export type CycleWithdrawalBudget = {
    allocated_amount: number;
    withdrawn_amount: number;
    remaining_amount: number;
};

export type EventPaymentLog = {
    id: number;
    order_id: number;
    order_number: string | null;
    customer_name: string | null;
    amount: number;
    payment_type: string | null;
    payment_type_label: string;
    payment_method: string | null;
    payment_status: string;
    payment_status_label: string;
    transaction_reference: string | null;
    note: string | null;
    paid_at: string | null;
    verified_at: string | null;
    verified_by_name: string | null;
};

export type EventPaymentSummary = {
    entry_count: number;
    verified_amount: number;
    verified_count: number;
    pending_count: number;
    failed_count: number;
};

export type StatusCounts = Record<string, number>;

export type OrderSummaryPickupPoint = {
    id: number;
    name: string;
    order_count: number;
    by_status: StatusCounts;
    packages: Array<{
        id: number;
        name: string;
        quantity: number;
        unit_label: string;
        pack_line_label: string;
    }>;
    total_due_amount: string;
};

export type OrderSummaryPackage = {
    id: number;
    name: string;
    sold_qty: number;
    stock_qty: number | null;
    remaining_qty: number | null;
    order_count: number;
    by_status: StatusCounts;
    pack_count: number;
    physical_label: string | null;
    pack_line_label: string | null;
    is_low_stock: boolean;
};

export type OrderSummary = {
    pickup_points: OrderSummaryPickupPoint[];
    packages: OrderSummaryPackage[];
};

export type EventDetails = {
    id: number;
    title: string;
    slug: string;
    status: string;
    status_label: string;
    is_finalized: boolean;
    description: string | null;
    banner_image_url: string | null;
    order_open_at: string | null;
    order_close_at: string | null;
    expected_delivery_date: string | null;
    created_at: string | null;
    updated_at: string | null;
    packages: EventPackage[];
    pickup_points: EventPickupPoint[];
    expenses: EventExpense[];
    incomes: EventIncome[];
    expense_summary: EventExpenseSummary;
    bank_withdrawals: EventBankWithdrawal[];
    withdrawal_summary: WithdrawalSummary;
    float_summary: FloatSummary;
    cycle_withdrawal_budget: CycleWithdrawalBudget;
    payments: EventPaymentLog[];
    payment_summary: EventPaymentSummary;
    bank_deposits: EventBankDeposit[];
    bank_deposit_summary: BankDepositSummary;
    bank_deposit_reconciliation: BankDepositReconciliation;
    fund_cycle: {
        id: number;
        name: string | null;
        status: string | null;
        status_label: string | null;
        start_date: string | null;
        lock_date: string | null;
        maturity_date: string | null;
        settlement_date: string | null;
    };
};

export const statusCount = (counts: StatusCounts, status: string): number =>
    counts[status] ?? 0;

export const isEventLocked = (event: EventDetails): boolean =>
    event.is_finalized || event.status === 'cancelled';

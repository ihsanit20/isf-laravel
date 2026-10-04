export function formatMoney(amount: number | null | undefined): string {
    return `${(amount ?? 0).toLocaleString(undefined, { maximumFractionDigits: 2 })} BDT`;
}

export function formatSignedMoney(amount: number | null | undefined): string {
    const value = amount ?? 0;

    if (value > 0) {
        return `+${formatMoney(value)}`;
    }

    if (value < 0) {
        return `−${formatMoney(Math.abs(value))}`;
    }

    return formatMoney(0);
}

export function amountToneClass(amount: number | null | undefined): string {
    const value = amount ?? 0;

    if (value > 0) {
        return 'text-emerald-600 dark:text-emerald-400';
    }

    if (value < 0) {
        return 'text-rose-600 dark:text-rose-400';
    }

    return 'text-muted-foreground';
}

export function titleCase(value: string | null | undefined): string {
    if (!value) {
        return '';
    }

    return value
        .replace(/_/g, ' ')
        .replace(/\b\w/g, (char) => char.toUpperCase());
}

function parseDate(value: string | null | undefined): Date | null {
    if (!value) {
        return null;
    }

    const parsed = new Date(value);

    return Number.isNaN(parsed.getTime()) ? null : parsed;
}

export function formatDate(value: string | null | undefined): string {
    const parsed = parseDate(value);

    if (!parsed) {
        return '—';
    }

    return new Intl.DateTimeFormat('en-GB', {
        day: '2-digit',
        month: 'short',
        year: 'numeric',
    }).format(parsed);
}

export function formatDateTime(value: string | null | undefined): string {
    const parsed = parseDate(value);

    if (!parsed) {
        return '—';
    }

    return new Intl.DateTimeFormat('en-GB', {
        day: '2-digit',
        month: 'short',
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
        hour12: true,
    }).format(parsed);
}

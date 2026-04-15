const bonusPaidAtFormatter = new Intl.DateTimeFormat('sl-SI', {
    timeZone: 'Europe/Ljubljana',
});
const bonusPaidAtInputFormatter = new Intl.DateTimeFormat('en-CA', {
    timeZone: 'Europe/Ljubljana',
    year: 'numeric',
    month: '2-digit',
    day: '2-digit',
});

export function filterBonuses<T extends { type: string }>(
    bonuses: T[],
    selectedBonusType: string,
): T[] {
    if (selectedBonusType === 'all') {
        return bonuses;
    }

    return bonuses.filter((bonus) => bonus.type === selectedBonusType);
}

export function formatBonusPaidAt(value: string | null): string {
    if (value === null) {
        return '–';
    }

    const parsedDate = new Date(normalizeBonusPaidAt(value));

    if (Number.isNaN(parsedDate.getTime())) {
        return '–';
    }

    return bonusPaidAtFormatter.format(parsedDate);
}

export function formatBonusPaidAtComment(value: string | null): string | null {
    if (value === null) {
        return null;
    }

    return `Izplačano: ${formatBonusPaidAt(value)}`;
}

export function normalizeBonusPaidAtForInput(value: string | null): string {
    if (value === null) {
        return '';
    }

    if (!value.includes('T')) {
        return value;
    }

    const parsedDate = new Date(normalizeBonusPaidAt(value));

    if (Number.isNaN(parsedDate.getTime())) {
        return '';
    }

    const parts = bonusPaidAtInputFormatter.formatToParts(parsedDate);
    const year = parts.find((part) => part.type === 'year')?.value;
    const month = parts.find((part) => part.type === 'month')?.value;
    const day = parts.find((part) => part.type === 'day')?.value;

    if (!year || !month || !day) {
        return '';
    }

    return `${year}-${month}-${day}`;
}

function normalizeBonusPaidAt(value: string): string {
    if (!value.includes('T')) {
        return value;
    }

    return value.replace(
        /\.(\d{3})\d+Z$/,
        (_match, milliseconds: string) => `.${milliseconds}Z`,
    );
}

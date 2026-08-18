import type { InertiaLinkProps } from '@inertiajs/vue3';
import { clsx } from 'clsx';
import type { ClassValue } from 'clsx';
import { twMerge } from 'tailwind-merge';

export function cn(...inputs: ClassValue[]) {
    return twMerge(clsx(inputs));
}

const slovenianNumberFormatter = new Intl.NumberFormat('sl-SI', {
    minimumFractionDigits: 2,
    maximumFractionDigits: 2,
    useGrouping: 'always',
});

export function formatSlovenianNumber(value: string | number): string {
    return slovenianNumberFormatter.format(Number(value));
}

const slovenianIntegerFormatter = new Intl.NumberFormat('sl-SI', {
    maximumFractionDigits: 0,
    useGrouping: 'always',
});

export function formatSlovenianInteger(value: string | number): string {
    return slovenianIntegerFormatter.format(Number(value));
}

const slovenianUnitPriceFormatter = new Intl.NumberFormat('sl-SI', {
    minimumFractionDigits: 2,
    maximumFractionDigits: 3,
    useGrouping: 'always',
});

export function formatUnitPrice(value: string | number): string {
    return slovenianUnitPriceFormatter.format(Number(value));
}

export function toUrl(href: NonNullable<InertiaLinkProps['href']>) {
    return typeof href === 'string' ? href : href?.url;
}

const NON_BREAKING_SPACE = ' ';

/**
 * Slovenian money format. The space before the unit is non-breaking so the
 * amount and the € never end up on separate lines.
 */
export function formatEuro(value: string | number): string {
    return `${formatSlovenianNumber(value)}${NON_BREAKING_SPACE}€`;
}

/** Slovenian percentage format, with the same non-breaking space rule. */
export function formatSlovenianPercent(value: string | number): string {
    return `${formatSlovenianNumber(value)}${NON_BREAKING_SPACE}%`;
}

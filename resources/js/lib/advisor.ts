import { formatSlovenianInteger } from '@/lib/utils';

export type AdvisorModelOption = { value: string; label: string };

export type AdvisorModelGroup = {
    provider: string;
    models: AdvisorModelOption[];
};

export type TokenUsage = {
    prompt_tokens?: number;
    completion_tokens?: number;
    cache_write_input_tokens?: number;
    cache_read_input_tokens?: number;
    reasoning_tokens?: number;
};

/**
 * Format token usage as a Slovenian breakdown: vhodni · izhodni · skupaj.
 * Returns null when no meaningful usage is available.
 */
export function formatTokenUsage(
    usage: TokenUsage | null | undefined,
): string | null {
    if (!usage) {
        return null;
    }

    const input = usage.prompt_tokens ?? 0;
    const output = usage.completion_tokens ?? 0;
    const total = input + output;

    if (total === 0) {
        return null;
    }

    return `Žetoni: ${formatSlovenianInteger(input)} vhodnih · ${formatSlovenianInteger(output)} izhodnih · ${formatSlovenianInteger(total)} skupaj`;
}

/**
 * Resolve a model's display label from the grouped options.
 */
export function modelLabel(
    models: AdvisorModelGroup[],
    value: string,
): string | undefined {
    for (const group of models) {
        const found = group.models.find((model) => model.value === value);

        if (found) {
            return found.label;
        }
    }

    return undefined;
}

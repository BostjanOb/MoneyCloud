import { formatSlovenianInteger } from '@/lib/utils';

export type AdvisorModelOption = { value: string; label: string };

export type AdvisorModelGroup = {
    provider: string;
    models: AdvisorModelOption[];
};

/**
 * Token usage as serialized by the AI SDK. Rows stored before Laravel AI 1.0
 * use `prompt_tokens` / `completion_tokens` instead of `input_tokens` /
 * `output_tokens`.
 */
export type TokenUsage = {
    input_tokens?: number;
    output_tokens?: number;
    prompt_tokens?: number;
    completion_tokens?: number;
    cache_write_input_tokens?: number | null;
    cache_read_input_tokens?: number | null;
    reasoning_tokens?: number | null;
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

    const input = usage.input_tokens ?? usage.prompt_tokens ?? 0;
    const output = usage.output_tokens ?? usage.completion_tokens ?? 0;
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

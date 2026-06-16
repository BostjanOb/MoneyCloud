<?php

namespace App\Enums;

use Laravel\Ai\Enums\Lab;

enum AdvisorProvider: string
{
    case Anthropic = 'anthropic';
    case OpenAI = 'openai';

    public function label(): string
    {
        return match ($this) {
            self::Anthropic => 'Claude',
            self::OpenAI => 'OpenAI',
        };
    }

    /**
     * The `provider` argument passed to an agent prompt.
     *
     * Each provider is pinned to a specific model (keyed by Lab value) so the
     * report uses a fast, capable model rather than the provider's slow
     * top-tier reasoning default.
     *
     * @return array<string, string>
     */
    public function promptTarget(): array
    {
        return match ($this) {
            self::Anthropic => [Lab::Anthropic->value => 'claude-sonnet-4-6'],
            self::OpenAI => [Lab::OpenAI->value => 'gpt-5.4'],
        };
    }
}

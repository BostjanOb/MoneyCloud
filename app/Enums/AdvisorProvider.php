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
     * Anthropic keeps the agent's `#[UseSmartestModel]` default, while OpenAI
     * is pinned to a specific model (keyed by Lab value) so it does not fall
     * back to the slow `gpt-5.4-pro` reasoning model.
     *
     * @return Lab|array<string, string>
     */
    public function promptTarget(): Lab|array
    {
        return match ($this) {
            self::Anthropic => Lab::Anthropic,
            self::OpenAI => [Lab::OpenAI->value => 'gpt-5.5'],
        };
    }
}

<?php

namespace App\Enums;

use Laravel\Ai\Enums\Lab;

/**
 * The single source of truth for AI provider/model selection used by the
 * financial analyst report and the advisor chat. The backing value is the
 * provider's model identifier passed to the AI SDK.
 */
enum AdvisorModel: string
{
    case ClaudeSonnet46 = 'claude-sonnet-4-6';
    case ClaudeOpus48 = 'claude-opus-4-8';
    case Gpt54 = 'gpt-5.4';
    case Gpt55 = 'gpt-5.5';

    /**
     * The AI provider (lab) this model belongs to.
     */
    public function lab(): Lab
    {
        return match ($this) {
            self::ClaudeSonnet46, self::ClaudeOpus48 => Lab::Anthropic,
            self::Gpt54, self::Gpt55 => Lab::OpenAI,
        };
    }

    /**
     * Human-friendly model name shown in the UI.
     */
    public function label(): string
    {
        return match ($this) {
            self::ClaudeSonnet46 => 'Claude Sonnet 4.6',
            self::ClaudeOpus48 => 'Claude Opus 4.8',
            self::Gpt54 => 'GPT-5.4',
            self::Gpt55 => 'GPT-5.5',
        };
    }

    /**
     * Provider group label used to group models in the picker.
     */
    public function providerLabel(): string
    {
        return match ($this->lab()) {
            Lab::Anthropic => 'Anthropic',
            Lab::OpenAI => 'OpenAI',
            default => $this->lab()->name,
        };
    }

    /**
     * The `provider` argument passed to an agent prompt/stream. Pins the
     * provider to this specific model (keyed by Lab value).
     *
     * @return array<string, string>
     */
    public function promptTarget(): array
    {
        return [$this->lab()->value => $this->value];
    }

    /**
     * The models grouped by provider for the frontend picker.
     *
     * @return array<int, array{provider: string, models: array<int, array{value: string, label: string}>}>
     */
    public static function options(): array
    {
        $grouped = [];

        foreach (self::cases() as $model) {
            $grouped[$model->providerLabel()][] = [
                'value' => $model->value,
                'label' => $model->label(),
            ];
        }

        return array_map(
            fn (string $provider, array $models): array => [
                'provider' => $provider,
                'models' => $models,
            ],
            array_keys($grouped),
            array_values($grouped),
        );
    }
}

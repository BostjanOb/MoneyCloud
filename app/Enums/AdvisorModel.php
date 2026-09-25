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
    case ClaudeSonnet5 = 'claude-sonnet-5';
    case ClaudeOpus5 = 'claude-opus-5';
    case ClaudeOpus55 = 'claude-opus-5-5';
    case Gpt56Terra = 'gpt-5.6-terra';
    case Gpt56Sol = 'gpt-5.6-sol';
    case Gpt6Sol = 'gpt-6-sol';
    case Gpt6Luna = 'gpt-6-luna';

    /**
     * The AI provider (lab) this model belongs to.
     */
    public function lab(): Lab
    {
        return match ($this) {
            self::ClaudeSonnet5, self::ClaudeOpus5, self::ClaudeOpus55 => Lab::Anthropic,
            self::Gpt56Terra, self::Gpt56Sol, self::Gpt6Sol, self::Gpt6Luna => Lab::OpenAI,
        };
    }

    /**
     * Human-friendly model name shown in the UI.
     */
    public function label(): string
    {
        return match ($this) {
            self::ClaudeSonnet5 => 'Claude Sonnet 5',
            self::ClaudeOpus5 => 'Claude Opus 5',
            self::ClaudeOpus55 => 'Claude Opus 5.5',
            self::Gpt56Terra => 'GPT-5.6 Terra',
            self::Gpt56Sol => 'GPT-5.6 Sol',
            self::Gpt6Sol => 'GPT-6 Sol',
            self::Gpt6Luna => 'GPT-6 Luna',
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

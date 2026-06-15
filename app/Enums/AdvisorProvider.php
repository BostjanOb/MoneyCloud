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

    public function toLab(): Lab
    {
        return match ($this) {
            self::Anthropic => Lab::Anthropic,
            self::OpenAI => Lab::OpenAI,
        };
    }
}

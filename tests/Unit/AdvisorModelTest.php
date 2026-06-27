<?php

use App\Enums\AdvisorModel;
use Laravel\Ai\Enums\Lab;

test('each model maps to the correct provider lab', function () {
    expect(AdvisorModel::ClaudeSonnet46->lab())->toBe(Lab::Anthropic)
        ->and(AdvisorModel::ClaudeOpus48->lab())->toBe(Lab::Anthropic)
        ->and(AdvisorModel::Gpt54->lab())->toBe(Lab::OpenAI)
        ->and(AdvisorModel::Gpt55->lab())->toBe(Lab::OpenAI);
});

test('prompt target keys the model by its provider lab value', function () {
    expect(AdvisorModel::ClaudeOpus48->promptTarget())
        ->toBe(['anthropic' => 'claude-opus-4-8'])
        ->and(AdvisorModel::Gpt55->promptTarget())
        ->toBe(['openai' => 'gpt-5.5']);
});

test('labels are human friendly', function () {
    expect(AdvisorModel::ClaudeSonnet46->label())->toBe('Claude Sonnet 4.6')
        ->and(AdvisorModel::Gpt54->label())->toBe('GPT-5.4');
});

test('options are grouped by provider', function () {
    $options = AdvisorModel::options();

    expect($options)->toHaveCount(2)
        ->and($options[0]['provider'])->toBe('Anthropic')
        ->and($options[0]['models'])->toHaveCount(2)
        ->and($options[0]['models'][0])->toBe([
            'value' => 'claude-sonnet-4-6',
            'label' => 'Claude Sonnet 4.6',
        ])
        ->and($options[1]['provider'])->toBe('OpenAI')
        ->and($options[1]['models'])->toHaveCount(2);
});

<?php

use App\Enums\AdvisorModel;
use Laravel\Ai\Enums\Lab;

test('each model maps to the correct provider lab', function () {
    expect(AdvisorModel::ClaudeSonnet5->lab())->toBe(Lab::Anthropic)
        ->and(AdvisorModel::ClaudeOpus5->lab())->toBe(Lab::Anthropic)
        ->and(AdvisorModel::Gpt56Terra->lab())->toBe(Lab::OpenAI)
        ->and(AdvisorModel::Gpt56Sol->lab())->toBe(Lab::OpenAI);
});

test('prompt target keys the model by its provider lab value', function () {
    expect(AdvisorModel::ClaudeOpus5->promptTarget())
        ->toBe(['anthropic' => 'claude-opus-5'])
        ->and(AdvisorModel::Gpt56Terra->promptTarget())
        ->toBe(['openai' => 'gpt-5.6-terra']);
});

test('labels are human friendly', function () {
    expect(AdvisorModel::ClaudeSonnet5->label())->toBe('Claude Sonnet 5')
        ->and(AdvisorModel::Gpt56Terra->label())->toBe('GPT-5.6 Terra');
});

test('options are grouped by provider', function () {
    $options = AdvisorModel::options();

    expect($options)->toHaveCount(2)
        ->and($options[0]['provider'])->toBe('Anthropic')
        ->and($options[0]['models'])->toHaveCount(2)
        ->and($options[0]['models'][0])->toBe([
            'value' => 'claude-sonnet-5',
            'label' => 'Claude Sonnet 5',
        ])
        ->and($options[1]['provider'])->toBe('OpenAI')
        ->and($options[1]['models'])->toHaveCount(2);
});

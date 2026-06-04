<?php

namespace App\Ai\Agents;

use App\Ai\Concerns\AnalyzesHouseholdFinances;
use Laravel\Ai\Attributes\MaxSteps;
use Laravel\Ai\Attributes\MaxTokens;
use Laravel\Ai\Attributes\Provider;
use Laravel\Ai\Attributes\Timeout;
use Laravel\Ai\Concerns\RemembersConversations;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Contracts\Conversational;
use Laravel\Ai\Contracts\HasTools;
use Laravel\Ai\Enums\Lab;
use Laravel\Ai\Promptable;
use Stringable;

/**
 * Conversational financial advisor. Shares the persona and tools of the
 * {@see FinancialAnalyst} but answers follow-up questions in a chat, persisting
 * history per user via the {@see RemembersConversations} trait.
 */
#[Provider(Lab::Anthropic)]
#[MaxSteps(15)]
#[MaxTokens(4000)]
#[Timeout(180)]
class FinancialAdvisor implements Agent, Conversational, HasTools
{
    use AnalyzesHouseholdFinances;
    use Promptable;
    use RemembersConversations;

    public function instructions(): Stringable|string
    {
        return $this->personaInstructions()."\n\n".<<<'PROMPT'
        NAČIN POGOVORA:
        Pogovarjaš se z lastnikom gospodinjstva. Odgovarjaj jedrnato in v pogovornem
        tonu. Ko vprašanje zahteva številke, jih najprej pridobi z orodji in šele nato
        odgovori. Če je smiselno, predlagaj naslednje vprašanje ali konkreten ukrep.
        PROMPT;
    }

    /**
     * @return array<int, object>
     */
    public function tools(): iterable
    {
        return $this->financialTools();
    }
}

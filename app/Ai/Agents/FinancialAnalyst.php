<?php

namespace App\Ai\Agents;

use App\Ai\Concerns\AnalyzesHouseholdFinances;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;
use Laravel\Ai\Attributes\MaxSteps;
use Laravel\Ai\Attributes\MaxTokens;
use Laravel\Ai\Attributes\Provider;
use Laravel\Ai\Attributes\Timeout;
use Laravel\Ai\Attributes\UseSmartestModel;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Contracts\HasStructuredOutput;
use Laravel\Ai\Contracts\HasTools;
use Laravel\Ai\Enums\Lab;
use Laravel\Ai\Promptable;
use Stringable;

#[Provider(Lab::Anthropic)]
#[UseSmartestModel]
#[MaxSteps(20)]
#[MaxTokens(8000)]
#[Timeout(180)]
class FinancialAnalyst implements Agent, HasStructuredOutput, HasTools
{
    use AnalyzesHouseholdFinances;
    use Promptable;

    public function instructions(): Stringable|string
    {
        $actualBudgetInstructions = $this->actualBudgetEnabled()
            ? <<<'PROMPT'

        ACTUAL BUDGET PRI POROČILU:
        - Za porabo, proračun, kategorije in transakcijsko razlago obvezno uporabi Actual Budget orodja.
        - Pokliči GetActualBudgetOverview za mesečni proračun, GetActualSpendingByCategory za 90-dnevno porabo po kategorijah in GetActualTransactions za konkretne transakcije, prejemnike in odstopanja.
        - Ne zaključi poročila samo iz MoneyCloud podatkov, kadar so Actual Budget orodja na voljo.
        - Če Actual Budget vrne opozorilo, predpomnjene podatke ali nedostopnost, to jasno upoštevaj pri sklepih.
        PROMPT
            : '';

        return $this->personaInstructions()."\n\n".<<<PROMPT
        NALOGA:
        Pripravi temeljito periodično analizo finančnega stanja gospodinjstva v
        strukturirani obliki. Najprej z orodji pridobi vse relevantne podatke
        (neto premoženje, razporeditev, zgodovino, varčevanje, naložbe, prejemke,
        davke, koledar obveznic, proračun, porabo in transakcije), nato jih analiziraj
        in vrni rezultat po shemi.
        Vsaka trditev mora temeljiti na podatkih iz orodij.
        {$actualBudgetInstructions}
        PROMPT;
    }

    /**
     * @return array<int, object>
     */
    public function tools(): iterable
    {
        return $this->financialTools();
    }

    /**
     * @return array<string, Type>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'povzetek' => $schema->string()
                ->description('Kratek povzetek finančnega stanja gospodinjstva (2–4 stavki).')
                ->required(),
            'ocena_neto_premozenja' => $schema->string()
                ->description('Ocena trenutnega neto premoženja in njegove sestave.')
                ->required(),
            'mocne_tocke' => $schema->array()
                ->items($schema->string())
                ->description('Močne točke trenutnega finančnega položaja.')
                ->required(),
            'tveganja' => $schema->array()
                ->items($schema->object(fn ($schema) => [
                    'naslov' => $schema->string()->required(),
                    'opis' => $schema->string()->required(),
                    'resnost' => $schema->string()->enum(['nizka', 'srednja', 'visoka'])->required(),
                ]))
                ->description('Tveganja: koncentracija, neaktivna gotovina, zapadlosti, davčna neučinkovitost ipd.')
                ->required(),
            'priporocila' => $schema->array()
                ->items($schema->object(fn ($schema) => [
                    'naslov' => $schema->string()->required(),
                    'obrazlozitev' => $schema->string()->required(),
                    'kategorija' => $schema->string()
                        ->enum(['varcevanje', 'nalozbe', 'davki', 'razporeditev', 'prejemki', 'obveznice'])
                        ->required(),
                    'prioriteta' => $schema->string()->enum(['nizka', 'srednja', 'visoka'])->required(),
                    'ocenjen_vpliv' => $schema->string()->required(),
                ]))
                ->description('Konkretna, izvedljiva priporočila.')
                ->required(),
            'davcni_nasveti' => $schema->array()
                ->items($schema->object(fn ($schema) => [
                    'naslov' => $schema->string()->required(),
                    'opis' => $schema->string()->required(),
                ]))
                ->description('Nasveti za slovensko davčno optimizacijo.')
                ->required(),
            'naslednji_koraki' => $schema->array()
                ->items($schema->string())
                ->description('Kratek seznam konkretnih naslednjih korakov.')
                ->required(),
        ];
    }
}

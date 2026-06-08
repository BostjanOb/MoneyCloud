<?php

namespace App\Ai\Tools;

use App\Services\ActualBudgetContextService;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;

class GetActualBudgetOverview implements Tool
{
    public function __construct(private readonly ActualBudgetContextService $actualBudget) {}

    public function description(): Stringable|string
    {
        return 'Vrne Actual Budget pregled za zadnjih 90 dni: mesečne proračune, '
            .'porabo, stanje po kategorijah in opozorila o svežini podatkov. '
            .'Vključuje skrite kategorije in off-budget račune.';
    }

    public function handle(Request $request): Stringable|string
    {
        return json_encode($this->actualBudget->budgetOverview(), JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);
    }

    /**
     * @return array<string, mixed>
     */
    public function schema(JsonSchema $schema): array
    {
        return [];
    }
}

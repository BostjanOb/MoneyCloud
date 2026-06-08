<?php

namespace App\Ai\Tools;

use App\Services\ActualBudgetContextService;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;

class GetActualSpendingByCategory implements Tool
{
    public function __construct(private readonly ActualBudgetContextService $actualBudget) {}

    public function description(): Stringable|string
    {
        return 'Vrne porabo, prihodke, neto tok in glavne prejemnike po Actual '
            .'kategorijah za zadnjih 90 dni. Transferji so izločeni iz porabe, '
            .'skrite kategorije pa so vključene.';
    }

    public function handle(Request $request): Stringable|string
    {
        return json_encode($this->actualBudget->spendingByCategory(), JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);
    }

    /**
     * @return array<string, mixed>
     */
    public function schema(JsonSchema $schema): array
    {
        return [];
    }
}

<?php

namespace App\Ai\Tools;

use App\Services\ActualBudgetContextService;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;

class RefreshActualChatContext implements Tool
{
    public function __construct(private readonly ActualBudgetContextService $actualBudget) {}

    public function description(): Stringable|string
    {
        return 'Ročno osveži predpomnilnik Actual Budget za klepet. Uporabi samo, '
            .'ko uporabnik izrecno zahteva osvežitev Actual Budget podatkov.';
    }

    public function handle(Request $request): Stringable|string
    {
        $this->actualBudget->refreshChatContext();

        return json_encode($this->actualBudget->metadata(), JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);
    }

    /**
     * @return array<string, mixed>
     */
    public function schema(JsonSchema $schema): array
    {
        return [];
    }
}

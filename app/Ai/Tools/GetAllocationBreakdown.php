<?php

namespace App\Ai\Tools;

use App\Services\FinancialContextService;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;

class GetAllocationBreakdown implements Tool
{
    public function __construct(private readonly FinancialContextService $context) {}

    public function description(): Stringable|string
    {
        return 'Vrne razporeditev premoženja po razredih sredstev z deležem (v odstotkih) '
            .'vsakega razreda glede na celoto. Uporabi za oceno diverzifikacije, '
            .'koncentracije in morebitnega neravnovesja portfelja.';
    }

    public function handle(Request $request): Stringable|string
    {
        return json_encode($this->context->allocationBreakdown(), JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);
    }

    /**
     * @return array<string, mixed>
     */
    public function schema(JsonSchema $schema): array
    {
        return [];
    }
}

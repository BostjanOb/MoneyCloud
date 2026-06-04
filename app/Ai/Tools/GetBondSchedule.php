<?php

namespace App\Ai\Tools;

use App\Services\FinancialContextService;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;

class GetBondSchedule implements Tool
{
    public function __construct(private readonly FinancialContextService $context) {}

    public function description(): Stringable|string
    {
        return 'Vrne koledar dogodkov obveznic (datumi kuponov in zapadlosti) s številom dni do dogodka. '
            .'Uporabi za opozorila na bližajoče se kupone in zapadlosti ter načrtovanje reinvestiranja.';
    }

    public function handle(Request $request): Stringable|string
    {
        return json_encode($this->context->bondSchedule(), JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);
    }

    /**
     * @return array<string, mixed>
     */
    public function schema(JsonSchema $schema): array
    {
        return [];
    }
}

<?php

namespace App\Ai\Tools;

use App\Services\FinancialContextService;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;

class GetSavingsAccounts implements Tool
{
    public function __construct(private readonly FinancialContextService $context) {}

    public function description(): Stringable|string
    {
        return 'Vrne varčevalne račune z zneski, obrestno mero (APY), izračunanimi letnimi '
            .'in mesečnimi obrestmi ter tehtano povprečno APY. Uporabi za oceno donosnosti '
            .'gotovine in iskanje neaktivnega denarja pri nizki obrestni meri.';
    }

    public function handle(Request $request): Stringable|string
    {
        return json_encode($this->context->savingsAccounts(), JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);
    }

    /**
     * @return array<string, mixed>
     */
    public function schema(JsonSchema $schema): array
    {
        return [];
    }
}

<?php

namespace App\Ai\Tools;

use App\Services\FinancialContextService;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;

class GetTaxAnalysis implements Tool
{
    public function __construct(private readonly FinancialContextService $context) {}

    public function description(): Stringable|string
    {
        return 'Vrne analizo slovenske dohodnine za vsako aktivno osebo za zadnje vneseno leto: '
            .'davčno osnovo, olajšave (splošna in otroške), odmerjeno dohodnino, razliko ob letni '
            .'odmeri (pozitivna = doplačilo, negativna = vračilo) in projekcijo za tekoče leto. '
            .'Uporabi za nasvete glede davčne optimizacije.';
    }

    public function handle(Request $request): Stringable|string
    {
        return json_encode($this->context->taxAnalysis(), JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);
    }

    /**
     * @return array<string, mixed>
     */
    public function schema(JsonSchema $schema): array
    {
        return [];
    }
}

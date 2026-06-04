<?php

namespace App\Ai\Tools;

use App\Services\FinancialContextService;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;

class GetIncomeSummary implements Tool
{
    public function __construct(private readonly FinancialContextService $context) {}

    public function description(): Stringable|string
    {
        return 'Vrne prejemke gospodinjstva po letih (neto, bruto, prispevki, davki, bonusi) '
            .'z medletno spremembo neto prejemkov. Uporabi za analizo gibanja dohodka, '
            .'rasti plač in stopnje varčevanja.';
    }

    public function handle(Request $request): Stringable|string
    {
        return json_encode($this->context->incomeSummary(), JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);
    }

    /**
     * @return array<string, mixed>
     */
    public function schema(JsonSchema $schema): array
    {
        return [];
    }
}

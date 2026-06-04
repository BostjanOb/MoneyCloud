<?php

namespace App\Ai\Tools;

use App\Services\FinancialContextService;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;

class GetNetWorthOverview implements Tool
{
    public function __construct(private readonly FinancialContextService $context) {}

    public function description(): Stringable|string
    {
        return 'Vrne trenutno neto premoženje gospodinjstva, razdeljeno po razredih sredstev '
            .'(varčevanje, obveznice, ETF, delnice, kripto). Zneski so v EUR. '
            .'Uporabi za splošen pregled stanja premoženja.';
    }

    public function handle(Request $request): Stringable|string
    {
        return json_encode($this->context->netWorthOverview(), JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);
    }

    /**
     * @return array<string, mixed>
     */
    public function schema(JsonSchema $schema): array
    {
        return [];
    }
}

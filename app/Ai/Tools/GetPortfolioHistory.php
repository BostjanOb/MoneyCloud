<?php

namespace App\Ai\Tools;

use App\Services\FinancialContextService;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;

class GetPortfolioHistory implements Tool
{
    public function __construct(private readonly FinancialContextService $context) {}

    public function description(): Stringable|string
    {
        return 'Vrne zgodovino mesečnih posnetkov premoženja (skupaj in po razredih) '
            .'za zadnjih N mesecev, vključno z absolutno in odstotno rastjo v obdobju. '
            .'Uporabi za analizo trenda in rasti premoženja skozi čas.';
    }

    public function handle(Request $request): Stringable|string
    {
        $months = is_numeric($request['months'] ?? null) ? (int) $request['months'] : 24;

        return json_encode($this->context->portfolioHistory($months), JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);
    }

    /**
     * @return array<string, mixed>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'months' => $schema->integer()->min(1)->max(120)
                ->description('Število zadnjih mesecev za prikaz (privzeto 24).'),
        ];
    }
}

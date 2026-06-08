<?php

namespace App\Ai\Tools;

use App\Services\FinancialContextService;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;

class GetInvestmentHoldings implements Tool
{
    public function __construct(private readonly FinancialContextService $context) {}

    public function description(): Stringable|string
    {
        return 'Vrne naložbe: vrednostne papirje (ETF, delnice, obveznice) z nabavno vrednostjo, '
            .'trenutno vrednostjo, dobičkom/izgubo (pred in po davku) ter donosom v odstotkih, '
            .'in ločeno kripto imetja po simbolih, vključno z APY in ocenjenimi obrestmi po denarnicah. '
            .'Uporabi za analizo posameznih pozicij, obrestovanih kripto stanj in uspešnosti naložb.';
    }

    public function handle(Request $request): Stringable|string
    {
        return json_encode($this->context->investmentHoldings(), JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);
    }

    /**
     * @return array<string, mixed>
     */
    public function schema(JsonSchema $schema): array
    {
        return [];
    }
}

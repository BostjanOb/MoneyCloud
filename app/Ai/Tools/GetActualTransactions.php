<?php

namespace App\Ai\Tools;

use App\Services\ActualBudgetContextService;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;

class GetActualTransactions implements Tool
{
    public function __construct(private readonly ActualBudgetContextService $actualBudget) {}

    public function description(): Stringable|string
    {
        return 'Vrne obogatene raw transakcije iz Actual Budget za zadnjih 90 dni. '
            .'Podpira omejitev po računu, kategoriji in datumu. Vsaka transakcija '
            .'vsebuje originalna polja Actual, EUR znesek, ime računa, prejemnika, '
            .'kategorije in oznako za transfer.';
    }

    public function handle(Request $request): Stringable|string
    {
        $filters = [
            'account_id' => $request['account_id'] ?? null,
            'category_id' => $request['category_id'] ?? null,
            'since' => $request['since'] ?? null,
            'until' => $request['until'] ?? null,
            'limit' => $request['limit'] ?? null,
        ];

        return json_encode($this->actualBudget->transactions($filters), JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);
    }

    /**
     * @return array<string, mixed>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'account_id' => $schema->string()->description('Neobvezen Actual account id.'),
            'category_id' => $schema->string()->description('Neobvezen Actual category id.'),
            'since' => $schema->string()->description('Neobvezen začetni datum znotraj 90-dnevnega okna, YYYY-MM-DD.'),
            'until' => $schema->string()->description('Neobvezen končni datum znotraj 90-dnevnega okna, YYYY-MM-DD.'),
            'limit' => $schema->integer()->min(1)->description('Največ vrnjenih transakcij, največ 1000.'),
        ];
    }
}
